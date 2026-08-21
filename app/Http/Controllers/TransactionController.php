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
use OpenApi\Attributes as OA;

class TransactionController extends Controller
{
    #[OA\Post(
        path: '/api/checkout',
        summary: 'Checkout / Create Transaction',
        description: 'Processes a new transaction. Includes FIFO stock reduction and optional voucher validation.',
        security: [['bearerAuth' => []]],
        tags: ['Transaction'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['paid_amount', 'payment_method', 'products'],
                properties: [
                    new OA\Property(property: 'member_id', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'paid_amount', type: 'integer', example: 150000),
                    new OA\Property(property: 'payment_method', type: 'string', example: 'cash'),
                    new OA\Property(
                        property: 'products',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 5),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2)
                            ]
                        )
                    ),
                    new OA\Property(property: 'vouchers', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Transaction created successfully',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'invoice_number' => 'INV-ABC123XYZ',
                            'total_price' => 120000,
                            'paid_amount' => 150000,
                            'change_amount' => 30000,
                            'payment_method' => 'cash',
                            'items_count' => 2
                        ]
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error or insufficient stock',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Gagal memproses transaksi.',
                        'error' => 'Stok untuk produk "Kopi" tidak mencukupi. Sisa stok: 1'
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: '/api/transactions',
        summary: 'List Transactions',
        security: [['bearerAuth' => []]],
        tags: ['Transaction'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Search invoice', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transactions list',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'invoice_number' => 'INV-ABC123XYZ',
                                'total_price' => 120000,
                                'paid_amount' => 150000,
                                'change_amount' => 30000,
                                'payment_method' => 'cash',
                                'created_at' => '2026-08-06T10:00:00.000000Z',
                                'items_count' => 2
                            ]
                        ],
                        'links' => ['first' => '...', 'last' => '...'],
                        'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1]
                    ]
                )
            )
        ]
    )]
    public function index(){
        $search = request()->query('search');
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');
        $maxTotal = request()->query('max_total');
        $minTotal = request()->query('min_total');
        $payment = request()->query('payment_method');

        $size = request()->query("size",10);
        $sortBy = request()->query("sort_by", "created_at");
        $sortDirection = request()->query("sort_direction", "desc");

        $allowedSorts = ['created_at', 'total_price'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $direction = $sortDirection === 'asc' ? \SortDirection::Ascending : \SortDirection::Descending;

        $query = Transaction::query();
        $query->withCount("items");

        $query
            ->when($search, function ($query, $search) {
                $query->where("invoice_number", "like", "%{$search}%");})
            ->when($startDate, function ($query, $startDate) {
                $query->where("created_at", ">=", $startDate);})
            ->when($endDate, function ($query, $endDate) {
                $query->where("created_at", "<=", $endDate);})
            ->when($minTotal, function ($query, $minTotal) {
                $query->where("total_price", ">=", $minTotal);})
            ->when($maxTotal, function ($query, $maxTotal) {
                $query->where("total_price", "<=", $maxTotal);})
            ->when($payment, function ($query, $payment) {
                $query->where("payment_method", "=", "{$payment}");
            });

        return TransactionResource::collection($query->orderBy("transactions.{$sortBy}", $direction)->paginate($size));
    }

    #[OA\Get(
        path: '/api/transactions/{transaction}',
        summary: 'Get Transaction Detail',
        security: [['bearerAuth' => []]],
        tags: ['Transaction'],
        parameters: [
            new OA\Parameter(name: 'transaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transaction details with items and member',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'invoice_number' => 'INV-ABC123XYZ',
                            'total_price' => 120000,
                            'paid_amount' => 150000,
                            'change_amount' => 30000,
                            'payment_method' => 'cash',
                            'items' => [
                                [
                                    'id' => 1,
                                    'product_id' => 5,
                                    'quantity' => 2,
                                    'selling_price' => 60000,
                                    'subtotal' => 120000,
                                    'product' => ['id' => 5, 'name' => 'Kopi']
                                ]
                            ],
                            'member' => [
                                'id' => 1,
                                'name' => 'John Doe',
                                'member_code' => 'MEM-12345',
                                'total_spent' => 500000
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(Transaction $transaction){
        $transaction->load(["items.product", "member"]);
        return new DetailTransactionResource($transaction);

    }
}
