<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin->value]);
        $this->token = $this->user->createToken('Token_Web')->plainTextToken;
    }

    public function test_index_returns_paginated_units_filtered_by_search(): void
    {
        Unit::factory()->create(['name' => 'Kilogram']);
        Unit::factory()->create(['name' => 'Gram']);
        Unit::factory()->create(['name' => 'Liter']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/units?search=gram');

        $response->assertOk()
            ->assertJsonCount(2, 'data') // Kilogram, Gram
            ->assertJsonFragment(['name' => 'Kilogram'])
            ->assertJsonFragment(['name' => 'Gram'])
            ->assertJsonMissing(['name' => 'Liter']);
    }

    public function test_index_unauthenticated(): void
    {
        $response = $this->getJson('/api/units');
        $response->assertUnauthorized();
    }

    public function test_store_creates_new_unit(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/units', [
                'name' => 'Meter',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Meter']);

        $this->assertDatabaseHas('units', [
            'name' => 'Meter',
        ]);
    }

    public function test_store_validation_fails_without_name(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/units', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_unit(): void
    {
        $unit = Unit::factory()->create(['name' => 'Pcs']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/units/{$unit->id}", ['name' => 'Box']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Box']);

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Box']);
    }

    public function test_update_unit_validation_fails_without_name(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/units/{$unit->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_unit_returns_404_if_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson('/api/units/999', ['name' => 'Box']);

        $response->assertNotFound();
    }

    public function test_destroy_deletes_unit(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/units/{$unit->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('units', [
            'id' => $unit->id,
        ]);
    }

    public function test_destroy_returns_404_if_unit_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/units/999");

        $response->assertNotFound();
    }
}