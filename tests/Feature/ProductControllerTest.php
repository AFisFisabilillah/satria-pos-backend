<?php

namespace Tests\Feature;

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
        Product::factory()->count(15)->create(['unit_id' => $this->unit->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/products?size=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data') // Karena paginate(10)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'slug', 'description', 'image', 'active', 'sale_price', 'unit_id']
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

        $payload = [
            'name' => 'Kopi Bubuk',
            'code' => 'KP-001',
            'description' => 'Kopi robusta asli',
            'active' => 1,
            'unit_id' => $this->unit->id,
            'sale_price' => 15000,
            'image' => $file,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post('/api/products', $payload); // menggunakan post biasa untuk multipart

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Kopi Bubuk', 'code' => 'KP-001']);

        $product = Product::where('code', 'KP-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('kopi-bubuk', $product->slug);

        // Assert file exists di disk public
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_store_validation_fails_without_required_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'image', 'unit_id', 'sale_price']);
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

        $payload = [
            'name' => 'Update Kopi',
            'code' => $product->code, // Menggunakan kode yg sama untuk test 'ignore unique'
            'description' => 'Updated desc',
            'active' => 0,
            'unit_id' => $this->unit->id,
            'sale_price' => 20000,
            '_method' => 'PUT',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/products/{$product->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Update Kopi', 'sale_price' => 20000]);

        $product->refresh();
        $this->assertEquals('images/old.jpg', $product->image); // Image tidak berubah
    }

    public function test_update_product_with_new_image_deletes_old_image(): void
    {
        // Setup existing image
        $oldFile = UploadedFile::fake()->image('old.jpg');
        $oldPath = $oldFile->storeAs('images/products', 'old.jpg', 'public');

        $product = Product::factory()->create(['unit_id' => $this->unit->id, 'image' => $oldPath]);

        Storage::disk('public')->assertExists($oldPath);

        $newFile = UploadedFile::fake()->image('new.jpg');

        $payload = [
            'name' => 'Updated Product',
            'code' => 'NEW-01',
            'unit_id' => $this->unit->id,
            'sale_price' => 50000,
            'image' => $newFile,
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