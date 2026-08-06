<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use App\Models\Suplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $token;
    protected $suplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin->value]);
        $this->token = $this->adminUser->createToken('Token_Web')->plainTextToken;
        $this->suplier = Suplier::factory()->create();
    }

    public function test_can_create_supplier_return_and_deduct_stock()
    {
        $product = Product::factory()->create(['active' => true]);
        $stockSupply = StockSupply::factory()->create(['suplier_id' => $this->suplier->id]);

        $stockItem = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 50,
            'quantity_remaining' => 50,
        ]);

        $payload = [
            'suplier_id' => $this->suplier->id,
            'date' => '2026-08-06',
            'notes' => 'Barang reject pabrik',
            'items' => [
                [
                    'stock_supply_item_id' => $stockItem->id,
                    'quantity' => 5,
                    'reason' => 'Pecah'
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/supplier-returns', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.suplier_id', $this->suplier->id)
            ->assertJsonPath('data.items.0.quantity', 5);

        // Verify stock deducted
        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $stockItem->id,
            'quantity_remaining' => 45
        ]);

        $this->assertDatabaseHas('supplier_returns', [
            'suplier_id' => $this->suplier->id,
            'notes' => 'Barang reject pabrik'
        ]);

        $this->assertDatabaseHas('supplier_return_items', [
            'stock_supply_item_id' => $stockItem->id,
            'quantity' => 5,
            'reason' => 'Pecah'
        ]);
    }

    public function test_cannot_return_more_than_remaining_stock()
    {
        $product = Product::factory()->create(['active' => true]);
        $stockSupply = StockSupply::factory()->create(['suplier_id' => $this->suplier->id]);

        $stockItem = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 10,
            'quantity_remaining' => 10,
        ]);

        $payload = [
            'suplier_id' => $this->suplier->id,
            'date' => '2026-08-06',
            'items' => [
                [
                    'stock_supply_item_id' => $stockItem->id,
                    'quantity' => 15, // Lebih dari stok (10)
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/supplier-returns', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Gagal memproses retur ke suplier.'
            ]);

        // Verify stock NOT deducted
        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $stockItem->id,
            'quantity_remaining' => 10
        ]);

        $this->assertDatabaseCount('supplier_returns', 0);
    }
}