<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use App\Models\Suplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $token;
    protected $stockSupplyItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $this->token = $this->adminUser->createToken('Token_Web')->plainTextToken;

        $suplier = Suplier::factory()->create();
        $product = Product::factory()->create(['active' => true]);
        $stockSupply = StockSupply::factory()->create(['suplier_id' => $suplier->id]);

        $this->stockSupplyItem = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 50,
            'quantity_remaining' => 50,
        ]);
    }

    public function test_can_create_stock_adjustment_and_deduct_remaining_stock(): void
    {
        $payload = [
            'stock_supply_item_id' => $this->stockSupplyItem->id,
            'quantity' => 10,
            'type' => 'damaged',
            'reason' => 'Kemasan rusak',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/stock-adjustments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.stock_supply_item_id', $this->stockSupplyItem->id)
            ->assertJsonPath('data.user_id', $this->adminUser->id)
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.type', 'damaged')
            ->assertJsonPath('data.reason', 'Kemasan rusak');

        $this->assertDatabaseHas('stock_adjustments', [
            'stock_supply_item_id' => $this->stockSupplyItem->id,
            'user_id' => $this->adminUser->id,
            'quantity' => 10,
            'type' => 'damaged',
            'reason' => 'Kemasan rusak',
        ]);

        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $this->stockSupplyItem->id,
            'quantity_remaining' => 40,
        ]);
    }

    public function test_cannot_create_stock_adjustment_exceeding_remaining_stock(): void
    {
        $payload = [
            'stock_supply_item_id' => $this->stockSupplyItem->id,
            'quantity' => 100, // Exceeds remaining stock (50)
            'type' => 'expired',
            'reason' => 'Sudah kadaluarsa',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/stock-adjustments', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Quantity adjustment exceeds remaining stock quantity.',
            ]);

        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $this->stockSupplyItem->id,
            'quantity_remaining' => 50,
        ]);

        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_can_list_stock_adjustments(): void
    {
        StockAdjustment::create([
            'stock_supply_item_id' => $this->stockSupplyItem->id,
            'user_id' => $this->adminUser->id,
            'quantity' => 5,
            'type' => 'lost',
            'reason' => 'Hilang di gudang',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/stock-adjustments');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', 5);
    }

    public function test_can_show_stock_adjustment(): void
    {
        $adjustment = StockAdjustment::create([
            'stock_supply_item_id' => $this->stockSupplyItem->id,
            'user_id' => $this->adminUser->id,
            'quantity' => 5,
            'type' => 'other',
            'reason' => 'Sampel gratis',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/stock-adjustments/{$adjustment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $adjustment->id)
            ->assertJsonPath('data.reason', 'Sampel gratis');
    }
}

