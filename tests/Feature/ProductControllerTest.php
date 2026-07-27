<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;
    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('Token_Web')->plainTextToken;
        $this->unit = Unit::factory()->create();
    }

    public function test_index_returns_paginated_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['unit_id' => $this->unit->id]);
        $product->categories()->attach($category);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/products?size=10');

        $response->assertOk()
            ->assertJsonCount(1, 'data') // Karena paginate(10)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'slug', 'description', 'image', 'active', 'sale_price', 'unit_id', 'categories']
                ],
                'links',
                'meta'
            ]);
    }

    public function test_index_filters_products_by_search(): void
    {
        Product::factory()->create(['name' => 'Beras Organik']);
        Product::factory()->create(['name' => 'Gula Pasir']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/products?search=beras');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Beras Organik'])
            ->assertJsonMissing(['name' => 'Gula Pasir']);
    }

    public function test_store_creates_new_product_with_image(): void
    {
        $file = UploadedFile::fake()->image('product.jpg');
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $payload = [
            'name' => 'Kopi Bubuk',
            'code' => 'KP-001',
            'description' => 'Kopi robusta asli',
            'active' => 1,
            'unit_id' => $this->unit->id,
            'sale_price' => 15000,
            'image' => $file,
            'category_ids' => [$category1->id, $category2->id],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post('/api/products', $payload); // menggunakan post biasa untuk multipart

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Kopi Bubuk', 'code' => 'KP-001']);

        $product = Product::where('code', 'KP-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('kopi-bubuk', $product->slug);

        // Assert categories attached
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $category1->id,
        ]);
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $category2->id,
        ]);

        // Assert file exists di disk public
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_store_validation_fails_without_required_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'image', 'unit_id', 'sale_price', 'category_ids']);
    }

    public function test_store_validation_fails_with_invalid_image(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post('/api/products', [
                'name' => 'Test',
                'code' => 'TEST',
                'image' => $file,
                'unit_id' => $this->unit->id,
                'sale_price' => 1000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_show_returns_product_details(): void
    {
        $product = Product::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => $product->name, 'code' => $product->code]);
    }

    public function test_update_product_without_image(): void
    {
        $product = Product::factory()->create(['unit_id' => $this->unit->id, 'image' => 'images/old.jpg']);
        $oldCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $product->categories()->attach($oldCategory);

        $payload = [
            'name' => 'Update Kopi',
            'code' => $product->code, // Menggunakan kode yg sama untuk test 'ignore unique'
            'description' => 'Updated desc',
            'active' => 0,
            'unit_id' => $this->unit->id,
            'sale_price' => 20000,
            'category_ids' => [$newCategory->id],
            '_method' => 'PUT',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/products/{$product->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Update Kopi', 'sale_price' => 20000]);

        $product->refresh();
        $this->assertEquals('images/old.jpg', $product->image); // Image tidak berubah

        // Assert old category removed and new attached
        $this->assertDatabaseMissing('category_product', [
            'product_id' => $product->id,
            'category_id' => $oldCategory->id,
        ]);
        $this->assertDatabaseHas('category_product', [
            'product_id' => $product->id,
            'category_id' => $newCategory->id,
        ]);
    }

    public function test_update_product_with_new_image_deletes_old_image(): void
    {
        // Setup existing image
        $oldFile = UploadedFile::fake()->image('old.jpg');
        $oldPath = $oldFile->storeAs('images/products', 'old.jpg', 'public');

        $product = Product::factory()->create(['unit_id' => $this->unit->id, 'image' => $oldPath]);
        $category = Category::factory()->create();

        Storage::disk('public')->assertExists($oldPath);

        $newFile = UploadedFile::fake()->image('new.jpg');

        $payload = [
            'name' => 'Updated Product',
            'code' => 'NEW-01',
            'unit_id' => $this->unit->id,
            'sale_price' => 50000,
            'image' => $newFile,
            'category_ids' => [$category->id],
            '_method' => 'PUT',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/products/{$product->id}", $payload);

        $response->assertOk();

        // Old image deleted
        Storage::disk('public')->assertMissing($oldPath);

        // New image stored
        $product->refresh();
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_destroy_deletes_product(): void
    {
        $product = Product::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/products/{$product->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }
}