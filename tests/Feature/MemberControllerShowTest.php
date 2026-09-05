<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberControllerShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_member_details_with_transactions()
    {
        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin
        ]);

        $member = Member::factory()->create([
            'name' => 'John Doe',
            'member_code' => 'MEM-12345678',
            'phone' => '08123456789',
            'email' => 'john@example.com',
            'total_spent' => 500000,
            'active' => true,
        ]);

        Transaction::factory()->count(2)->create([
            'member_id' => $member->id
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/members/' . $member->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'member_code',
                    'phone',
                    'email',
                    'total_spent',
                    'active',
                    'transaction' => [
                        '*' => [
                            'id',
                            'invoice_number',
                            'total_price',
                            'paid_amount',
                            'change_amount',
                            'payment_method',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertEquals('John Doe', $response->json('data.name'));
        $this->assertCount(2, $response->json('data.transaction'));
    }

    public function test_cannot_show_member_unauthenticated()
    {
        $member = Member::factory()->create();

        $response = $this->getJson('/api/members/' . $member->id);

        $response->assertStatus(401);
    }

    public function test_returns_404_for_non_existent_member()
    {
        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/members/9999');

        $response->assertStatus(404);
    }
}