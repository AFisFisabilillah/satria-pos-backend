<?php

namespace Database\Factories;

use App\Models\Suplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suplier>
 */
class SuplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'phone' => $this->faker->e164PhoneNumber(),
            'city' => $this->faker->city(),
            'province' => $this->faker->state(),
            'address' => $this->faker->streetAddress(),
            'postal_code' => $this->faker->postcode(),
        ];
    }
}
