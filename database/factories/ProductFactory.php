<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'code' => $this->faker->unique()->word() . '-' . $this->faker->randomNumber(4),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->text(),
            'image' => $this->faker->word() . '.jpg',
            'active' => $this->faker->boolean(),
            'sale_price' => $this->faker->randomNumber(5),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'unit_id' => Unit::factory(),
        ];
    }
}
