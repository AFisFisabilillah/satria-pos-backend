<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['token'],
            ]);
    }

    public function test_login_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_user_not_found(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_validation_fails(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_success(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Token_Web')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out.',
                'data' => null,
            ]);

        // token revoked — verify deleted from DB
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_unauthenticated(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized();
    }

    public function test_update_profile_success(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Old Name'
        ]);
        $token = $user->createToken('Token_Web')->plainTextToken;

        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/user', [
                'name' => 'New Name',
                'foto_profile' => $file,
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Profile updated successfully.',
            ])
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);

        $user->refresh();
        $this->assertNotNull($user->foto_profile);
        Storage::disk('public')->assertExists($user->foto_profile);
    }

    public function test_update_profile_validation_fails(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Token_Web')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/user', [
                'foto_profile' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['foto_profile']);
    }

    public function test_update_profile_unauthenticated(): void
    {
        $response = $this->postJson('/api/user', [
            'name' => 'New Name',
        ]);

        $response->assertUnauthorized();
    }
}
