<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['fixed', 'percent']);

        return [
            'name' => $this->faker->words(2, true),
            'code' => $this->faker->unique()->word(),
            'type' => $type,
            'value' => $type === 'percent'
                ? fake()->numberBetween(1, 100)
                : fake()->numberBetween(1000, 9000),
            'min_purchase' => $this->faker->numberBetween(1, 100),
            'quota' => $this->faker->numberBetween(1, 100),
            'expired_at' => $this->faker->date(),
            'active' => $this->faker->boolean(49),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
