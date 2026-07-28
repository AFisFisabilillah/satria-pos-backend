<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use App\Models\Suplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSupplyControllerTest extends TestCase
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

    public function test_index_returns_paginated_stock_supplies(): void
    {
        StockSupply::factory()->count(15)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/stock-supply?size=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'invoice_number', 'date', 'notes', 'suplier']
                ],
                'links',
                'meta'
            ]);
    }

    public function test_index_filters_by_search_invoice_number(): void
    {
        StockSupply::factory()->create(['invoice_number' => 'INV-001']);
        StockSupply::factory()->create(['invoice_number' => 'TEST-002']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/stock-supply?search=INV');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['invoice_number' => 'INV-001'])
            ->assertJsonMissing(['invoice_number' => 'TEST-002']);
    }

    public function test_index_filters_by_search_suplier_name(): void
    {
        $suplierA = Suplier::factory()->create(['name' => 'PT Maju Abadi']);
        $suplierB = Suplier::factory()->create(['name' => 'CV Mundur Teratur']);

        StockSupply::factory()->create(['suplier_id' => $suplierA->id, 'invoice_number' => 'A-001']);
        StockSupply::factory()->create(['suplier_id' => $suplierB->id, 'invoice_number' => 'B-001']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/stock-supply?search=Maju');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['invoice_number' => 'A-001']);
    }

    public function test_index_filters_by_date_range(): void
    {
        StockSupply::factory()->create(['date' => '2026-07-01']);
        StockSupply::factory()->create(['date' => '2026-07-15']);
        StockSupply::factory()->create(['date' => '2026-07-30']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/stock-supply?startDate=2026-07-10&endDate=2026-07-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['date' => '2026-07-15T00:00:00.000000Z']);
    }

    public function test_store_creates_stock_supply_and_items(): void
    {
        $suplier = Suplier::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $payload = [
            'suplier_id' => $suplier->id,
            'invoice_number' => 'INV-TEST-99',
            'notes' => 'Catatan tes masuk barang',
            'products' => [
                [
                    'product_id' => $product1->id,
                    'quantity_in' => 50,
                    'purchase_price' => 10000,
                    'expired_date' => '2027-01-01',
                ],
                [
                    'product_id' => $product2->id,
                    'quantity_in' => 100,
                    'purchase_price' => 20000,
                    'expired_date' => '2028-12-31',
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/stock-supply', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['invoice_number' => 'INV-TEST-99']);

        // Pastikan Stock Supply terbuat
        $this->assertDatabaseHas('stock_supplies', [
            'suplier_id' => $suplier->id,
            'invoice_number' => 'INV-TEST-99',
        ]);

        $stockSupplyId = $response->json('data.id');

        // Pastikan Stock Supply Item relasinya terbuat
        $this->assertDatabaseHas('stock_supply_items', [
            'stock_supply_id' => $stockSupplyId,
            'product_id' => $product1->id,
            'quantity_in' => 50,
            'quantity_remaining' => 50, // quantity_remaining diset sama dengan quantity_in
            'purchase_price' => 10000,
            'expired_at' => '2027-01-01',
        ]);

        $this->assertDatabaseHas('stock_supply_items', [
            'stock_supply_id' => $stockSupplyId,
            'product_id' => $product2->id,
            'quantity_in' => 100,
            'purchase_price' => 20000,
            'expired_at' => '2028-12-31',
        ]);
    }

    public function test_store_validation_fails_without_required_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/stock-supply', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['suplier_id', 'invoice_number', 'notes', 'products']);
    }

    public function test_update_modifies_stock_supply_and_calculates_remaining(): void
    {
        $suplier = Suplier::factory()->create();
        $stockSupply = StockSupply::factory()->create(['suplier_id' => $suplier->id]);

        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // Item 1 to be updated
        $item1 = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product1->id,
            'quantity_in' => 100,
            'quantity_remaining' => 80, // 20 used
        ]);

        // Item 2 to be deleted (not sent in payload)
        $item2 = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'quantity_in' => 50,
            'quantity_remaining' => 50, // unused, can be deleted
        ]);

        $payload = [
            'suplier_id' => $suplier->id,
            'invoice_number' => 'INV-UPDATED',
            'notes' => 'Updated notes',
            'products' => [
                [
                    'id' => $item1->id,
                    'product_id' => $product1->id,
                    'quantity_in' => 150, // Increased by 50
                    'purchase_price' => 12000,
                    'expired_date' => '2029-01-01',
                ],
                [
                    // New item
                    'product_id' => $product2->id,
                    'quantity_in' => 30,
                    'purchase_price' => 5000,
                    'expired_date' => '2029-12-31',
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/stock-supply/{$stockSupply->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['invoice_number' => 'INV-UPDATED']);

        $this->assertDatabaseHas('stock_supplies', ['invoice_number' => 'INV-UPDATED']);

        // Assert item 1 updated correctly
        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $item1->id,
            'quantity_in' => 150,
            'quantity_remaining' => 130, // 80 + 50 (diff)
            'purchase_price' => 12000,
        ]);

        // Assert item 2 deleted
        $this->assertDatabaseMissing('stock_supply_items', [
            'id' => $item2->id,
        ]);

        // Assert new item created
        $this->assertDatabaseHas('stock_supply_items', [
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product2->id,
            'quantity_in' => 30,
            'quantity_remaining' => 30,
        ]);
    }

    public function test_update_fails_if_quantity_remaining_becomes_negative(): void
    {
        $stockSupply = StockSupply::factory()->create();
        $product = Product::factory()->create();

        $item = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 100,
            'quantity_remaining' => 10, // 90 used
        ]);

        $payload = [
            'suplier_id' => $stockSupply->suplier_id,
            'invoice_number' => $stockSupply->invoice_number,
            'notes' => $stockSupply->notes,
            'products' => [
                [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'quantity_in' => 50, // Reduced by 50
                    'purchase_price' => 10000,
                    'expired_date' => '2029-01-01',
                ],
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/stock-supply/{$stockSupply->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Updated quantity_in is too low, some stock is already consumed.']);
    }

    public function test_update_fails_if_trying_to_delete_used_stock(): void
    {
        $stockSupply = StockSupply::factory()->create();
        $product = Product::factory()->create();

        $item = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 100,
            'quantity_remaining' => 50, // 50 used
        ]);

        // Payload empty products means deleting all items
        $payload = [
            'suplier_id' => $stockSupply->suplier_id,
            'invoice_number' => $stockSupply->invoice_number,
            'notes' => $stockSupply->notes,
            'products' => [
                // Empty = delete $item
                [
                    'product_id' => $product->id,
                    'quantity_in' => 100,
                    'purchase_price' => 10000,
                    'expired_date' => '2029-01-01',
                ]
            ] // Wait, form validation needs 'id' if updating, else it creates.
              // To test deletion, we just don't pass 'id'.
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/stock-supply/{$stockSupply->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete item because stock has already been used.']);
    }
}