<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $superAdmin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a super admin user for testing
        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        // Create a cashier user for testing role permissions
        $this->cashier = User::factory()->create([
            'role' => UserRole::Cashier,
        ]);
    }

    public function test_super_admin_can_view_users_list(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->superAdmin)->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'foto_profile'
                    ]
                ]
            ]);
    }

    public function test_cashier_cannot_view_users_list(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_create_user(): void
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'role' => 'cashier',
        ];

        $response = $this->actingAs($this->superAdmin)->postJson('/api/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => UserRole::Cashier,
        ]);
    }

    public function test_create_user_validation_fails(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/users', [
            'name' => '', // Empty name
            'email' => 'not-an-email',
            'password' => 'short', // Too short password
            'role' => 'invalid_role', // Invalid role
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_super_admin_can_view_specific_user(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Cashier,
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/users/' . $user->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_super_admin_can_update_user(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Cashier,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'super_admin'
        ];

        $response = $this->actingAs($this->superAdmin)->putJson('/api/users/' . $user->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => UserRole::SuperAdmin,
        ]);
    }

    public function test_super_admin_can_update_user_password(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Cashier,
            'password' => Hash::make('oldpassword')
        ]);

        $updateData = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'role' => $user->role->value
        ];

        $response = $this->actingAs($this->superAdmin)->putJson('/api/users/' . $user->id, $updateData);

        $response->assertStatus(200);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_super_admin_can_delete_user(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Cashier,
        ]);

        $response = $this->actingAs($this->superAdmin)->deleteJson('/api/users/' . $user->id);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Berhasil menghapus user');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->superAdmin)->deleteJson('/api/users/' . $this->superAdmin->id);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Tidak dapat menghapus akun yang sedang digunakan');

        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
        ]);
    }
}
