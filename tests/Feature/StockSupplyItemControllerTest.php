<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSupplyItemControllerTest extends TestCase
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

    public function test_destroy_deletes_item_when_stock_is_unused(): void
    {
        $stockSupply = StockSupply::factory()->create();
        $product = Product::factory()->create();

        $item = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 50,
            'quantity_remaining' => 50, // unused
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/stock-supply-items/{$item->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Item deleted successfully.']);

        // Assert hard deleted
        $this->assertDatabaseMissing('stock_supply_items', [
            'id' => $item->id,
        ]);
    }

    public function test_destroy_fails_when_stock_is_used(): void
    {
        $stockSupply = StockSupply::factory()->create();
        $product = Product::factory()->create();

        $item = StockSupplyItem::factory()->create([
            'stock_supply_id' => $stockSupply->id,
            'product_id' => $product->id,
            'quantity_in' => 50,
            'quantity_remaining' => 49, // 1 item used
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/stock-supply-items/{$item->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete item because stock has already been used.']);

        // Assert still exists
        $this->assertDatabaseHas('stock_supply_items', [
            'id' => $item->id,
        ]);
    }

    public function test_destroy_returns_404_if_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/stock-supply-items/9999");

        $response->assertNotFound();
    }
}