<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Http\Resources\DetailTransactionResource;
use App\Http\Resources\TransactionResource;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockSupplyItem;
use App\Models\Transaction;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function checkout(TransactionRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $member = null;
            if (!empty($request->member_id)) {
                $member = Member::findOrFail($request->member_id);
            }

            $totalPrice = 0;
            $processedProducts = [];

            // 1. Validasi Produk & Hitung Total Price
            foreach ($data['products'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if (!$product->active) {
                    throw new \Exception("Produk '{$product->name}' tidak aktif.");
                }

                $totalPrice += ($product->sale_price * $item['quantity']);
                $processedProducts[] = [
                    'product' => $product,
                    'quantity' => $item['quantity']
                ];
            }

            // 2. Validasi voucher dan hitung diskon
            $totalDiscount = 0;
            $appliedVouchers = [];

            if (!empty($data['vouchers'])) {
                $vouchers = Voucher::whereIn('id', $data['vouchers'])
                    ->lockForUpdate() // Prevent race condition for quota
                    ->get();

                $vouchers = $vouchers->sortBy(fn($v) => $v->type === 'percent' ? 1 : 0);

                foreach ($vouchers as $voucher) {
                    if (!$voucher->active) {
                        throw new \Exception("Voucher '{$voucher->code}' tidak aktif.");
                    }
                    if ($voucher->expired_at && \Carbon\Carbon::parse($voucher->expired_at)->startOfDay()->isPast()) {
                        throw new \Exception("Voucher '{$voucher->code}' sudah kedaluwarsa.");
                    }
                    if ($voucher->quota < 1) {
                        throw new \Exception("Kuota voucher '{$voucher->code}' sudah habis.");
                    }
                    if ($voucher->min_purchase && $totalPrice < $voucher->min_purchase) {
                        throw new \Exception("Total belanja tidak memenuhi syarat minimum untuk voucher '{$voucher->code}'.");
                    }

                    $currentPriceTarget = $totalPrice - $totalDiscount; // Diskon dihitung dari sisa harga
                    $discount = 0;
                    if ($voucher->type === 'percent') {
                        $discount = ($currentPriceTarget * $voucher->value) / 100;
                    } else { // fixed
                        $discount = $voucher->value;
                    }

                    $totalDiscount += $discount;
                    $appliedVouchers[] = [
                        'voucher' => $voucher,
                        'discount' => $discount
                    ];

                    $voucher->decrement('quota', 1);
                }
            }

            // Batasi diskon agar tidak melebihi total belanja
            if ($totalDiscount > $totalPrice) {
                $totalDiscount = $totalPrice;
            }

            $finalPrice = $totalPrice - $totalDiscount;

            // Validasi pembayaran
            if ($data['paid_amount'] < $finalPrice) {
                throw new \Exception("Jumlah pembayaran (paid_amount) kurang dari total tagihan (total_price: {$finalPrice}).");
            }

            // Update Member jika ada (setelah validasi lolos)
            if ($member) {
                $member->total_spent += $finalPrice;
                $member->save();
            }

            $changeAmount = $data['paid_amount'] - $finalPrice;

            // Generate unique invoice
            $invoiceNumber = 'INV-' . strtoupper(Str::random(10));
            while (Transaction::where('invoice_number', $invoiceNumber)->exists()) {
                $invoiceNumber = 'INV-' . strtoupper(Str::random(10));
            }

            $transaction = Transaction::create([
                'invoice_number' => $invoiceNumber,
                'total_price' => $finalPrice,
                'paid_amount' => $data['paid_amount'],
                'change_amount' => $changeAmount,
                'payment_method' => $data['payment_method'],
                'member_id' => $member ? $member->id : null
            ]);

            // Save voucher usages
            foreach ($appliedVouchers as $applied) {
                $transaction->vouchers()->attach($applied['voucher']->id, [
                    'discount_applied' => $applied['discount']
                ]);
            }

            // 3. Proses FIFO
            foreach ($processedProducts as $item) {
                $qtyNeeded = $item['quantity'];
                $product = $item['product'];

                // Lock for update directly on the query to prevent race condition before sum check
                $supplyBatches = StockSupplyItem::where('product_id', $product->id)
                    ->where('quantity_remaining', '>', 0)
                    ->orderBy('created_at', 'asc') // First-In, First-Out
                    ->lockForUpdate() // Lock baris ini selama transaksi DB
                    ->get();

                $totalStock = $supplyBatches->sum('quantity_remaining');

                if ($totalStock < $qtyNeeded) {
                    throw new \Exception("Stok untuk produk '{$product->name}' tidak mencukupi. Sisa stok: {$totalStock}");
                }

                // Simpan item transaksi dengan snapshot harga jual dari master saat ini
                $transItem = $transaction->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qtyNeeded,
                    'selling_price' => $product->sale_price,
                    'subtotal' => $qtyNeeded * $product->sale_price,
                ]);

                foreach ($supplyBatches as $batch) {
                    if ($qtyNeeded <= 0) break;

                    $take = min($batch->quantity_remaining, $qtyNeeded);

                    $batch->decrement('quantity_remaining', $take);
                    $qtyNeeded -= $take;

                    $transItem->cogs()->create([
                        'stock_supply_item_id' => $batch->id,
                        'quantity_deducted' => $take,
                        'purchase_price' => $batch->purchase_price,
                    ]);
                }
            }

            DB::commit();

            return new TransactionResource($transaction->loadCount('items'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses transaksi.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function index(){
        $search = request()->query('search');
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $size = request()->query("size",10);

        $query = Transaction::query();
        $query->withCount("items");

        $query->when($search, function ($query, $search) {
           $query->where("invoice_number", "like", "%{$search}%");
        });
        $query->when($startDate, function ($query, $startDate) {
            $query->where("created_at", ">=", $startDate);
        });
        $query->when($endDate, function ($query, $endDate) {
            $query->where("created_at", "<=", $endDate);
        });

        return TransactionResource::collection($query->paginate($size));
    }

    public function show(Transaction $transaction){
        $transaction->load(["items.product", "member"]);
        return new DetailTransactionResource($transaction);

    }
}
