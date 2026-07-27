<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('Token_Web')->plainTextToken;
    }

    public function test_index_returns_categories_without_search(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name']
                ]
            ]);
    }

    public function test_index_returns_categories_filtered_by_search(): void
    {
        Category::factory()->create(['name' => 'Makanan']);
        Category::factory()->create(['name' => 'Minuman']);
        Category::factory()->create(['name' => 'Snack']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/categories?search=makan');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Makanan'])
            ->assertJsonMissing(['name' => 'Minuman'])
            ->assertJsonMissing(['name' => 'Snack']);
    }

    public function test_index_unauthenticated(): void
    {
        $response = $this->getJson('/api/categories');
        $response->assertUnauthorized();
    }

    public function test_store_creates_new_category(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/categories', [
                'name' => 'Elektronik',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Elektronik']);

        $this->assertDatabaseHas('categories', [
            'name' => 'Elektronik',
        ]);
    }

    public function test_store_validation_fails_without_name(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_destroy_deletes_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_destroy_returns_404_if_category_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/categories/999");

        $response->assertNotFound();
    }
}