<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberControllerTest extends TestCase
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

    public function test_index_returns_paginated_members(): void
    {
        Member::factory()->count(15)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/members?size=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'member_code', 'phone', 'email', 'total_spent', 'active']
                ]
            ]);
    }

    public function test_index_filters_by_name(): void
    {
        Member::factory()->create(['name' => 'Budi Santoso']);
        Member::factory()->create(['name' => 'Andi Wijaya']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/members?name=budi');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Budi Santoso'])
            ->assertJsonMissing(['name' => 'Andi Wijaya']);
    }

    public function test_index_filters_by_member_code(): void
    {
        Member::factory()->create(['member_code' => 'MEM-001']);
        Member::factory()->create(['member_code' => 'MEM-002']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/members?member_code=001');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['member_code' => 'MEM-001']);
    }

    public function test_index_filters_by_active_status(): void
    {
        Member::factory()->create(['active' => true, 'name' => 'Active Member']);
        Member::factory()->create(['active' => false, 'name' => 'Inactive Member']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/members?active=0');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Inactive Member']);
    }

    public function test_index_filters_by_total_spent_range(): void
    {
        Member::factory()->create(['total_spent' => 50000]);
        Member::factory()->create(['total_spent' => 150000]);
        Member::factory()->create(['total_spent' => 300000]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/members?min_total_spent=100000&max_total_spent=200000');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['total_spent' => 150000]);
    }

    public function test_store_creates_new_member(): void
    {
        $payload = [
            'name' => 'Siti Aminah',
            'phone' => '0812345678',
            'email' => 'siti@example.com'
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/members', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Siti Aminah', 'total_spent' => 0, 'active' => true]);

        // Verifikasi bahwa member_code ter-generate otomatis (dimulai dengan MEM-)
        $this->assertStringStartsWith('MEM-', $response->json('data.member_code'));

        $this->assertDatabaseHas('members', ['name' => 'Siti Aminah']);
    }

    public function test_show_returns_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/members/{$member->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => $member->name]);
    }

    public function test_update_modifies_member(): void
    {
        $member = Member::factory()->create(['name' => 'Old Name']);

        $payload = [
            'name' => 'New Name',
            'active' => false
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/members/{$member->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name', 'active' => false]);

        $this->assertDatabaseHas('members', ['id' => $member->id, 'name' => 'New Name']);
    }

    public function test_destroy_deletes_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/members/{$member->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
    }
}