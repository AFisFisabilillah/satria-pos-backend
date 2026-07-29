<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TransactionFactory extends Factory{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'invoice_number' => $this->faker->word(),//
'total_price' => $this->faker->randomNumber(),
'paid_amount' => $this->faker->randomNumber(),
'change_amount' => $this->faker->randomNumber(),
'payment_method' => $this->faker->word(),
'created_at' => Carbon::now(),
'updated_at' => Carbon::now(),
        ];
    }
}
