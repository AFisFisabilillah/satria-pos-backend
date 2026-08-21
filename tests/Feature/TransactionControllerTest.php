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
        $this->user = User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin->value]);
        $this->token = $this->user->createToken('Token_Web')->plainTextToken;
    }

    public function test_checkout_processes_transaction_and_fifo_stock_deduction(): void
    {
        // Setup Product with price 10.000
        $product = Product::factory()->create(['active' => true, 'sale_price' => 10000]);

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
        $product = Product::factory()->create(['active' => true, 'sale_price' => 10000]);
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

    public function test_index_sorts_by_created_at_ascending(): void
    {
        $older = Transaction::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = Transaction::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/transactions?sort_by=created_at&sort_direction=asc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($older->id, $data[0]['id']);
        $this->assertEquals($newer->id, $data[1]['id']);
    }

    public function test_index_sorts_by_total_price_ascending(): void
    {
        $cheap = Transaction::factory()->create(['total_price' => 5000]);
        $expensive = Transaction::factory()->create(['total_price' => 50000]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/transactions?sort_by=total_price&sort_direction=asc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($cheap->id, $data[0]['id']);
        $this->assertEquals($expensive->id, $data[1]['id']);
    }

    public function test_show_returns_transaction_detail_with_items_relation(): void
    {
        $product = Product::factory()->create(['active' => true]);
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

    public function test_checkout_applies_multiple_vouchers_correctly(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 100000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $voucherFixed = \App\Models\Voucher::factory()->create([
            'type' => 'fixed',
            'value' => 20000,
            'active' => true,
            'quota' => 10,
            'expired_at' => now()->addDays(5),
            'min_purchase' => 50000,
        ]);

        $voucherPercent = \App\Models\Voucher::factory()->create([
            'type' => 'percent',
            'value' => 10, // 10%
            'active' => true,
            'quota' => 10,
            'expired_at' => now()->addDays(5),
            'min_purchase' => 50000,
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'vouchers' => [$voucherFixed->id, $voucherPercent->id],
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.total_price', 72000);

        $this->assertDatabaseHas('transaction_voucher', [
            'voucher_id' => $voucherFixed->id,
            'discount_applied' => 20000,
        ]);

        $this->assertDatabaseHas('transaction_voucher', [
            'voucher_id' => $voucherPercent->id,
            'discount_applied' => 8000,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucherFixed->id,
            'quota' => 9,
        ]);
    }

    public function test_checkout_fails_if_voucher_inactive(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 100000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'active' => false,
            'quota' => 10,
            'expired_at' => now()->addDays(5),
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'vouchers' => [$voucher->id],
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error', "Voucher '{$voucher->code}' tidak aktif.");
    }

    public function test_checkout_fails_if_voucher_expired(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 100000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'active' => true,
            'quota' => 10,
            'expired_at' => now()->subDays(1),
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'vouchers' => [$voucher->id],
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error', "Voucher '{$voucher->code}' sudah kedaluwarsa.");
    }

    public function test_checkout_fails_if_voucher_quota_empty(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 100000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'active' => true,
            'quota' => 0,
            'expired_at' => now()->addDays(5),
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'vouchers' => [$voucher->id],
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error', "Kuota voucher '{$voucher->code}' sudah habis.");
    }

    public function test_checkout_fails_if_min_purchase_not_met(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 50000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'active' => true,
            'quota' => 10,
            'expired_at' => now()->addDays(5),
            'min_purchase' => 100000,
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'vouchers' => [$voucher->id],
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error', "Total belanja tidak memenuhi syarat minimum untuk voucher '{$voucher->code}'.");
    }

    public function test_checkout_caps_discount_at_total_price(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 50000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'type' => 'fixed',
            'value' => 100000,
            'active' => true,
            'quota' => 10,
            'expired_at' => now()->addDays(5),
            'min_purchase' => 0,
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 0,
            'vouchers' => [$voucher->id],
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.total_price', 0);
    }

    public function test_checkout_works_without_member(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 15000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $payload = [
            'payment_method' => 'cash',
            'paid_amount' => 15000,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('transactions', [
            'member_id' => null,
            'total_price' => 15000
        ]);
    }

    public function test_checkout_updates_member_total_spent(): void
    {
        $product = Product::factory()->create(['active' => true, 'sale_price' => 15000]);
        $supply = StockSupply::factory()->create();
        StockSupplyItem::factory()->create([
            'stock_supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity_remaining' => 10,
        ]);

        $member = \App\Models\Member::factory()->create(['total_spent' => 0]);

        $payload = [
            'member_id' => $member->id,
            'payment_method' => 'cash',
            'paid_amount' => 15000,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/checkout', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'total_spent' => 15000
        ]);
    }
}
