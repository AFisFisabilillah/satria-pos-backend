<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\DetailProductResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        summary: 'List Products',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Search by product name', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'size', in: 'query', required: false, description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of products',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'name' => 'Kopi Arabica',
                                'code' => 'PRD-001',
                                'slug' => 'kopi-arabica',
                                'description' => 'Kopi asli arabica',
                                'image' => 'images/products/uuid.jpg',
                                'active' => true,
                                'sale_price' => 25000,
                                'total_stock' => 100,
                                'unit' => ['id' => 1, 'name' => 'Pcs'],
                                'categories' => [
                                    ['id' => 1, 'name' => 'Minuman']
                                ]
                            ]
                        ],
                        'links' => ['first' => '...', 'last' => '...'],
                        'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1]
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $search = request()->query('search');
        $size = request()->query('size', 10);

        $productQuery = Product::query()->with(["unit", "categories"])->withSum('stockSupplyItems', 'quantity_remaining');
        $productQuery->when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        });

        $products = $productQuery->paginate($size);
        return ProductResource::collection($products);
    }

    #[OA\Post(
        path: '/api/products',
        summary: 'Create Product',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name', 'code', 'unit_id', 'sale_price', 'category_ids'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'code', type: 'string'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'active', type: 'boolean', default: true),
                        new OA\Property(property: 'unit_id', type: 'integer'),
                        new OA\Property(property: 'sale_price', type: 'integer'),
                        new OA\Property(property: 'category_ids[]', type: 'array', items: new OA\Items(type: 'integer'))
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Product created',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Kopi Arabica',
                            'code' => 'PRD-001',
                            'active' => true,
                            'sale_price' => 25000,
                            'total_stock' => 0
                        ]
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(example: ['message' => 'The given data was invalid.', 'errors' => ['name' => ['The name field is required.']]]))
        ]
    )]
    public function store(ProductRequest $request)
    {
        $data = $request->validated();
        $image = $request->file('image');
        $path = 'public/empty.jpeg';
        if ($image) {
            $fileName = $request->code . "-" . Str::uuid() . "." . $image->getClientOriginalExtension();
            $path = $image->storeAs('images/products', $fileName, 'public');
        }
        $slug = Str::slug($request->name, '-');
        $product = Product::create([
            "name" => $request->name,
            "code" => $request->code,
            "slug" => $slug,
            "description" => $request->description,
            "image" => $path,
            "active" => $request->active,
            "unit_id" => $request->unit_id,
            "sale_price" => $request->sale_price,
        ]);

        $product->categories()->attach($request->category_ids);

        $product->load(['unit', 'categories'])->loadSum('stockSupplyItems', 'quantity_remaining');

        return new ProductResource($product);
    }

    #[OA\Get(
        path: '/api/products/{product}',
        summary: 'Get Product Detail',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product details',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Kopi Arabica',
                            'code' => 'PRD-001',
                            'sale_price' => 25000,
                            'total_stock' => 100,
                            'unit' => ['id' => 1, 'name' => 'Pcs'],
                            'categories' => [['id' => 1, 'name' => 'Minuman']],
                            'stock_supply_items' => [
                                [
                                    'id' => 1,
                                    'quantity_in' => 50,
                                    'quantity_remaining' => 50,
                                    'purchase_price' => 20000,
                                    'expired_at' => '2026-12-31'
                                ]
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(Product $product)
    {
        $product->load(['unit', 'categories', 'stockSupplyItems', 'stockSupplyItems.stockSupply', 'stockSupplyItems.stockSupply.suplier'])->loadSum('stockSupplyItems', 'quantity_remaining');
        return new DetailProductResource($product);
    }


    #[OA\Post(
        path: '/api/products/{product}',
        summary: 'Update Product',
        description: 'Use POST method with _method=PUT for multipart/form-data update in Laravel',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'code', type: 'string'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'active', type: 'boolean'),
                        new OA\Property(property: 'unit_id', type: 'integer'),
                        new OA\Property(property: 'sale_price', type: 'integer'),
                        new OA\Property(property: 'category_ids[]', type: 'array', items: new OA\Items(type: 'integer'))
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product updated',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Kopi Arabica Updated',
                            'sale_price' => 26000
                        ]
                    ]
                )
            )
        ]
    )]
    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {

            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $image = $request->file('image');

            $fileName = Str::uuid() . '.' . $image->getClientOriginalExtension();

            $image->storeAs('images/products', $fileName, 'public');

            $data['image'] = 'images/products/' . $fileName;
        }

        $product->update($data);

        $product->categories()->sync($request->category_ids);

        $product->load(['unit', 'categories'])->loadSum('stockSupplyItems', 'quantity_remaining');

        return new ProductResource($product);
    }

    #[OA\Delete(
        path: '/api/products/{product}',
        summary: 'Delete Product',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted', content: new OA\JsonContent(example: ['message' => 'Product deleted successfully.'])),
            new OA\Response(response: 422, description: 'Cannot delete because it has stock supply', content: new OA\JsonContent(example: ['message' => 'Cannot delete product because it already has stock supply records. Please deactivate it instead.']))
        ]
    )]
    public function destroy(Product $product)
    {
        if ($product->stockSupplyItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product because it already has stock supply records. Please deactivate it instead.'
            ], 422);
        }

        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.'
        ]);
    }


    #[OA\Patch(
        path: '/api/products/{product}/toggle-active',
        summary: 'Toggle Product Active Status',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product status toggled', content: new OA\JsonContent(example: ['data' => ['id' => 1, 'active' => false]]))
        ]
    )]
    public function toggleActive(Product $product){
        $product->active = !$product->active;
        $product->save();
        return new ProductResource($product);
    }

    #[OA\Post(
        path: '/api/products/bulk-toggle-active',
        summary: 'Bulk Toggle Active Status',
        security: [['bearerAuth' => []]],
        tags: ['Product'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Products status toggled', content: new OA\JsonContent(example: ['message' => 'Products status toggled successfully.']))
        ]
    )]
    public function bulkToggleActive(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id',
        ]);

        Product::whereIn('id', $request->ids)
            ->update(['active' => \Illuminate\Support\Facades\DB::raw('NOT active')]);

        return response()->json([
            'message' => 'Products status toggled successfully.'
        ]);
    }
}
