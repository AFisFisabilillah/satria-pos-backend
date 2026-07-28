<?php

namespace Database\Factories;

use App\Models\StockSupply;
use App\Models\Suplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class StockSupplyFactory extends Factory
{
    protected $model = StockSupply::class;

    public function definition(): array
    {
        return [
            'invoice_number' => $this->faker->unique()->word() . '-' . $this->faker->randomNumber(5),
            'date' => Carbon::now(),
            'notes' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'suplier_id' => Suplier::factory(),
        ];
    }
}
