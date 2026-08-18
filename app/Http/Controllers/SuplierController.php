<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuplierRequest;
use App\Http\Requests\UpdateSuplierRequest;
use App\Http\Resources\DetailSupplierResource;
use App\Http\Resources\SuplierResource;
use App\Models\Suplier;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SuplierController extends Controller
{
    #[OA\Get(
        path: '/api/supliers',
        summary: 'List Suppliers',
        security: [['bearerAuth' => []]],
        tags: ['Supplier'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of suppliers',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            ['id' => 1, 'name' => 'PT Suplier ABC', 'phone' => '08123456789', 'address' => 'Jl. Merdeka No 1', "city"=>"Bekasi", "province"=>"jawa barat", "postal_code"=>"17152"]
                        ],
                        'links' => ['first' => '...', 'last' => '...'],
                        'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1]
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $search = $request->query('search');
        $size = $request->query('size', 10);

        $query = Suplier::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $supliers = $query->paginate($size);

        return SuplierResource::collection($supliers);
    }

    #[OA\Post(
        path: '/api/supliers',
        summary: 'Create Supplier',
        security: [['bearerAuth' => []]],
        tags: ['Supplier'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'phone', 'address'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'province', type: 'string'),
                    new OA\Property(property: 'postal_code', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Supplier created',
                content: new OA\JsonContent(
                    example: ['data' => ['id' => 1, 'name' => 'PT Suplier ABC', 'phone' => '08123456789', 'address' => 'Jl. Merdeka No 1',"city"=>"Bekasi", "province"=>"jawa barat", "postal_code"=>"17152"]]
                )
            )
        ]
    )]
    public function store(StoreSuplierRequest $request)
    {
        $suplier = Suplier::create($request->validated());

        return new SuplierResource($suplier);
    }

    #[OA\Get(
        path: '/api/supliers/{suplier}',
        summary: 'Get Supplier Details',
        security: [['bearerAuth' => []]],
        tags: ['Supplier'],
        parameters: [
            new OA\Parameter(name: 'suplier', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Supplier details',
                content: new OA\JsonContent(
                    example: ['data' => ['id' => 1, 'name' => 'PT Suplier ABC', 'phone' => '08123456789', 'address' => 'Jl. Merdeka No 1',"city"=>"Bekasi", "province"=>"jawa barat", "postal_code"=>"17152"]]
                )
            )
        ]
    )]
    public function show(Suplier $suplier)
    {
        return new DetailSupplierResource($suplier->load(['stockSupplies','stockSupplies.stockSupplyItem']));
    }

    #[OA\Put(
        path: '/api/supliers/{suplier}',
        summary: 'Update Supplier',
        security: [['bearerAuth' => []]],
        tags: ['Supplier'],
        parameters: [
            new OA\Parameter(name: 'suplier', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'province', type: 'string'),
                    new OA\Property(property: 'postal_code', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Supplier updated',
                content: new OA\JsonContent(
                    example: ['data' => ['id' => 1, 'name' => 'PT Suplier ABC', 'phone' => '08123456789', 'address' => 'Jl. Merdeka No 1 Updated', "city"=>"Bekasi", "province"=>"jawa barat", "postal_code"=>"17152"]]
                )
            )
        ]
    )]
    public function update(UpdateSuplierRequest $request, Suplier $suplier)
    {
        $suplier->update($request->validated());

        return new SuplierResource($suplier);
    }

    #[OA\Delete(
        path: '/api/supliers/{suplier}',
        summary: 'Delete Supplier',
        security: [['bearerAuth' => []]],
        tags: ['Supplier'],
        parameters: [
            new OA\Parameter(name: 'suplier', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Supplier deleted')
        ]
    )]
    public function destroy(Suplier $suplier)
    {
        $suplier->delete();

        return response()->json([
            "message" => "success delete suplier"
        ]);
    }

    #[OA\Post(
        path: '/api/supliers/bulk-delete',
        summary: 'Bulk Delete Suppliers',
        security: [['bearerAuth' => []]],
        tags: ['Supplier'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Suppliers deleted')
        ]
    )]
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:supliers,id',
        ]);

        Suplier::whereIn('id', $request->ids)->delete();

        return response()->json([
            "message" => "success delete suplier"
        ]);
    }
}
