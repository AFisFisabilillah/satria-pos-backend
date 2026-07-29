<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TransactionItemFactory extends Factory
{
    protected $model = TransactionItem::class;

    public function definition(): array
    {
        return [
            'quantity' => $this->faker->randomNumber(),//
            'selling_price' => $this->faker->randomNumber(),
            'subtotal' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'transaction_id' => Transaction::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
