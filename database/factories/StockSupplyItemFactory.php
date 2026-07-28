<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockSupply;
use App\Models\StockSupplyItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class StockSupplyItemFactory extends Factory
{
    protected $model = StockSupplyItem::class;

    public function definition(): array
    {
        return [
            'quantity_in' => $this->faker->randomNumber(),
            'quantity_remaining' => $this->faker->randomNumber(),
            'purchase_price' => $this->faker->randomFloat(),
            'expired_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'stock_supply_id' => StockSupply::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
