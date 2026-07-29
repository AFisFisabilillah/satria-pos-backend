<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\StockSupplyItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function checkout(TransactionRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                'invoice_number' => 'INV-' . strtoupper(Str::random(10)),
                'total_price' => $data['total_price'],
                'paid_amount' => $data['paid_amount'],
                'change_amount' => $data['change_amount'],
                'payment_method' => $data['payment_method'],
            ]);

            foreach ($data['products'] as $item) {
                $qtyNeeded = $item['quantity'];

                $product = Product::findOrFail($item['product_id']);

                // Validasi Cek Ketersediaan Sisa Stok (Keseluruhan)
                $totalStock = StockSupplyItem::where('product_id', $product->id)
                    ->sum('quantity_remaining');

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

                // Logika FIFO & Snapshot Harga Modal (Purchase Price)
                $supplyBatches = StockSupplyItem::where('product_id', $product->id)
                    ->where('quantity_remaining', '>', 0)
                    ->orderBy('created_at', 'asc') // First-In, First-Out (Terlama dipotong duluan)
                    ->lockForUpdate() // Cegah race condition
                    ->get();

                foreach ($supplyBatches as $batch) {
                    if ($qtyNeeded <= 0) break;

//                    Mengambil nilai terkecil dari quantity
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

            return new TransactionResource($transaction->load(['items.product', 'items.cogs']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses transaksi.',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
