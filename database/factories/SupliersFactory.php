<?php

namespace Database\Factories;

use App\Models\Supliers;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SupliersFactory extends Factory
{
    protected $model = Supliers::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'city' => $this->faker->city(),
            'province' => $this->faker->word(),
            'address' => $this->faker->word(),
            'postal_code' => $this->faker->postcode(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
