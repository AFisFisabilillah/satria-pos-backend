<?php

namespace Tests\Feature;

use App\Models\Suplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuplierControllerTest extends TestCase
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

    public function test_index_returns_paginated_supliers(): void
    {
        Suplier::factory()->count(15)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/supliers?size=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'phone', 'city', 'province', 'adress', 'postal_code']
                ],
                'links',
                'meta'
            ]);
    }

    public function test_index_filters_by_name(): void
    {
        Suplier::factory()->create(['name' => 'PT Maju Jaya']);
        Suplier::factory()->create(['name' => 'CV Sukses Abadi']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/supliers?search=maju');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'PT Maju Jaya'])
            ->assertJsonMissing(['name' => 'CV Sukses Abadi']);
    }

    public function test_index_filters_by_phone(): void
    {
        Suplier::factory()->create(['name' => 'Supplier A', 'phone' => '08123456789']);
        Suplier::factory()->create(['name' => 'Supplier B', 'phone' => '08987654321']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/supliers?search=12345');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Supplier A'])
            ->assertJsonMissing(['name' => 'Supplier B']);
    }

    public function test_store_creates_suplier(): void
    {
        $payload = [
            'name' => 'PT Distributor Cepat',
            'phone' => '08111222333',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'adress' => 'Jalan Jendral Sudirman No. 1',
            'postal_code' => '12190',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/supliers', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'PT Distributor Cepat']);

        $this->assertDatabaseHas('supliers', [
            'name' => 'PT Distributor Cepat',
            'phone' => '08111222333',
        ]);
    }

    public function test_store_validation_fails(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/supliers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'city', 'province', 'adress', 'postal_code']);
    }

    public function test_show_returns_suplier(): void
    {
        $suplier = Suplier::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/supliers/{$suplier->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => $suplier->name]);
    }

    public function test_update_modifies_suplier(): void
    {
        $suplier = Suplier::factory()->create(['name' => 'Old Name']);

        $payload = [
            'name' => 'New Name',
            'phone' => '08111222333',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'adress' => 'Jalan Baru',
            'postal_code' => '12345',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/supliers/{$suplier->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('supliers', [
            'id' => $suplier->id,
            'name' => 'New Name',
        ]);
    }

    public function test_destroy_deletes_suplier(): void
    {
        $suplier = Suplier::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/supliers/{$suplier->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('supliers', [
            'id' => $suplier->id,
        ]);
    }

    public function test_bulk_delete_removes_multiple_supliers(): void
    {
        $suplier1 = Suplier::factory()->create();
        $suplier2 = Suplier::factory()->create();
        $suplier3 = Suplier::factory()->create(); // Keep this one

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/supliers/bulk-delete', [
                'ids' => [$suplier1->id, $suplier2->id]
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('supliers', ['id' => $suplier1->id]);
        $this->assertDatabaseMissing('supliers', ['id' => $suplier2->id]);
        $this->assertDatabaseHas('supliers', ['id' => $suplier3->id]);
    }

    public function test_bulk_delete_validates_ids(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/supliers/bulk-delete', [
                'ids' => [999, 1000] // IDs that don't exist
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0', 'ids.1']);
    }

    public function test_suplier_deletion_sets_stock_supply_suplier_id_to_null(): void
    {
        $suplier = Suplier::factory()->create();

        // Create a stock supply tied to this suplier
        $stockSupply = \App\Models\StockSupply::factory()->create([
            'suplier_id' => $suplier->id
        ]);

        $this->assertDatabaseHas('stock_supplies', [
            'id' => $stockSupply->id,
            'suplier_id' => $suplier->id
        ]);

        // Hit the delete API endpoint
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/supliers/{$suplier->id}");

        $response->assertOk();

        // The suplier should be deleted
        $this->assertDatabaseMissing('supliers', [
            'id' => $suplier->id
        ]);

        // The stock supply should STILL exist, but suplier_id should be NULL
        $this->assertDatabaseHas('stock_supplies', [
            'id' => $stockSupply->id,
            'suplier_id' => null
        ]);
    }
}
