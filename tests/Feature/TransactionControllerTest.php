<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('Token_Web')->plainTextToken;
    }

    public function test_checkout_processes_transaction_and_fifo_stock_deduction(): void
    {
        // Setup Product with price 10.000
        $product = Product::factory()->create(['sale_price' => 10000]);

        // Setup 2 Batches of Stock (FIFO)
        $supply = StockSupply::factory()->create();

        // Batch 1 (Older): qty 10, purchase price 5000
        $batch1 = StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_in' => 10,
            'quantity_remaining' => 10,
            'purchase_price' => 5000,
            'created_at' => now()->subDays(2),
        ]);

        // Batch 2 (Newer): qty 20, purchase price 6000
        $batch2 = StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_in' => 20,
            'quantity_remaining' => 20,
            'purchase_price' => 6000,
            'created_at' => now()->subDay(),
        ]);

        // User buys 15 items.
        // Expected FIFO behavior:
        // - Batch 1 (all 10 taken) -> remaining: 0
        // - Batch 2 (5 taken)      -> remaining: 15

        $payload = [
            
            'paid_amount' => 150000,
            
            'payment_method' => 'cash',
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 15,
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload); // Endpoint checkout

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'invoice_number', 'total_price', 'items_count'
                ]
            ])->assertJsonFragment(['items_count' => 1]);

        // Verify Header Transaction created
        $transaction = Transaction::first();
        $this->assertNotNull($transaction);
        $this->assertEquals(150000, $transaction->total_price);

        // Verify Transaction Items
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'selling_price' => 10000,
            'subtotal' => 150000,
        ]);

        // Verify FIFO Stock deduction
        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $batch1->id,
            'quantity_remaining' => 0, // Habis
        ]);

        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $batch2->id,
            'quantity_remaining' => 15, // Sisa 20 - 5 = 15
        ]);

        // Verify COGS Recorded with Snapshots
        $transItem = $transaction->items->first();

        $this->assertDatabaseHas('transaction_item_cogs', [
            'transaction_item_id' => $transItem->id,
            'stock_supply_item_id' => $batch1->id,
            'quantity_deducted' => 10,
            'purchase_price' => 5000,
        ]);

        $this->assertDatabaseHas('transaction_item_cogs', [
            'transaction_item_id' => $transItem->id,
            'stock_supply_item_id' => $batch2->id,
            'quantity_deducted' => 5,
            'purchase_price' => 6000,
        ]);
    }

    public function test_checkout_fails_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['sale_price' => 10000]);
        $supply = StockSupply::factory()->create();

        // Stock only has 5
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 5,
        ]);

        $payload = [
            
            'paid_amount' => 100000,
            
            'payment_method' => 'cash',
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10, // Trying to buy 10
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Gagal memproses transaksi.'
            ]);

        // Verify rollback transaction not created
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);

        // Verify stock remains untouched
        $this->assertDatabaseHas('stock_supply_items', [
            'product_id' => $product->id,
            'quantity_remaining' => 5,
        ]);
    }

    public function test_index_returns_paginated_transactions_with_items_count(): void
    {
        $transaction = Transaction::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/transactions?size=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'invoice_number', 'total_price', 'items_count']
                ],
                'links',
                'meta'
            ]);
    }

    public function test_show_returns_transaction_detail_with_items_relation(): void
    {
        $product = Product::factory()->create();
        $transaction = Transaction::factory()->create();
        \App\Models\TransactionItem::factory()->create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'invoice_number', 'total_price', 'items' => [
                        '*' => ['id', 'quantity', 'selling_price', 'subtotal', 'product']
                    ]
                ]
            ]);
    }
}
