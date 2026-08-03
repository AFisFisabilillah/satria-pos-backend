<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('Token_Web')->plainTextToken;
    }

    public function test_index_returns_paginated_vouchers(): void
    {
        Voucher::factory()->count(15)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/vouchers?size=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'type',
                        'value',
                        'min_purchase',
                        'quota',
                        'expired_at',
                        'active',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_index_can_search_vouchers(): void
    {
        Voucher::factory()->create(['name' => 'Discount 2024']);
        Voucher::factory()->create(['name' => 'Promo Ramadhan']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/vouchers?search=2024');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        
        $this->assertEquals('Discount 2024', $response->json('data.0.name'));
    }

    public function test_index_can_filter_by_active_status(): void
    {
        Voucher::factory()->create(['active' => true]);
        Voucher::factory()->create(['active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/vouchers?active=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_new_voucher(): void
    {
        $payload = [
            'code' => 'NEWVOUCHER',
            'type' => 'fixed',
            'value' => 50000,
            'min_purchase' => 100000,
            'quota' => 100,
            'expired_at' => now()->addDays(10)->format('Y-m-d'),
            'active' => true,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/vouchers', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'NEWVOUCHER');

        $this->assertDatabaseHas('vouchers', [
            'code' => 'NEWVOUCHER',
            'value' => 50000
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/vouchers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'type', 'value', 'quota']);
    }
    
    public function test_store_validates_percent_type_value_limit(): void
    {
        $payload = [
            'code' => 'PERCENT110',
            'type' => 'percent',
            'value' => 110,
            'quota' => 10,
            'expired_at' => now()->addDays(10)->format('Y-m-d'),
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/vouchers', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_show_returns_voucher_details(): void
    {
        $voucher = Voucher::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/vouchers/{$voucher->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $voucher->id);
    }

    public function test_update_modifies_existing_voucher(): void
    {
        $voucher = Voucher::factory()->create([
            'code' => 'OLDCODE',
            'quota' => 10
        ]);

        $payload = [
            'code' => 'NEWCODE',
            'type' => 'fixed',
            'value' => 50000,
            'quota' => 20,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/vouchers/{$voucher->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.code', 'NEWCODE');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'code' => 'NEWCODE',
            'quota' => 20
        ]);
    }

    public function test_destroy_deletes_voucher(): void
    {
        $voucher = Voucher::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/vouchers/{$voucher->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('vouchers', [
            'id' => $voucher->id
        ]);
    }

    public function test_toggle_active_changes_status(): void
    {
        $voucher = Voucher::factory()->create(['active' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/vouchers/{$voucher->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'active' => false
        ]);
    }

    public function test_bulk_toggle_active_changes_multiple_statuses(): void
    {
        $voucher1 = Voucher::factory()->create(['active' => true]);
        $voucher2 = Voucher::factory()->create(['active' => false]);

        $payload = [
            'ids' => [$voucher1->id, $voucher2->id]
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/vouchers/bulk-toggle-active", $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Products status toggled successfully.');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher1->id,
            'active' => false
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher2->id,
            'active' => true
        ]);
    }
}
