<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        summary: 'List Categories',
        security: [['bearerAuth' => []]],
        tags: ['Category'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of categories',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            ['id' => 1, 'name' => 'Minuman'],
                            ['id' => 2, 'name' => 'Makanan']
                        ]
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $search = request()->query('search');
        $size = request()->query('size',5);

        $categories = Category::when($search, function ($query, $search) {
            $query->where("name", "like", "%{$search}%");
        })->paginate($size);
        return CategoryResource::collection($categories);
    }

    #[OA\Post(
        path: '/api/categories',
        summary: 'Create Category',
        security: [['bearerAuth' => []]],
        tags: ['Category'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Category created',
                content: new OA\JsonContent(
                    example: ['data' => ['id' => 3, 'name' => 'Snack']]
                )
            )
        ]
    )]
    public function store(CategoryRequest $request)
    {
        return new CategoryResource(Category::create($request->validated()));
    }

    #[OA\Delete(
        path: '/api/categories/{category}',
        summary: 'Delete Category',
        security: [['bearerAuth' => []]],
        tags: ['Category'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category deleted')
        ]
    )]
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json();
    }
}
