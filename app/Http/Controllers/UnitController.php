<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UnitController extends Controller
{
    #[OA\Get(
        path: '/api/units',
        summary: 'List Units',
        security: [['bearerAuth' => []]],
        tags: ['Unit'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of units',
                content: new OA\JsonContent(
                    example: [
                        ['id' => 1, 'name' => 'Pcs'],
                        ['id' => 2, 'name' => 'Box']
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $search = $request->search;
        return Unit::where('name', 'like', '%' . $search . '%')->limit(5)->get();
    }

    #[OA\Post(
        path: '/api/units',
        summary: 'Create Unit',
        security: [['bearerAuth' => []]],
        tags: ['Unit'],
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
                description: 'Unit created',
                content: new OA\JsonContent(
                    example: ['id' => 3, 'name' => 'Lusin', 'created_at' => '2026-08-06T10:00:00.000000Z', 'updated_at' => '2026-08-06T10:00:00.000000Z']
                )
            )
        ]
    )]
    public function store(UnitRequest $request)
    {
        return Unit::create($request->validated());
    }

    #[OA\Delete(
        path: '/api/units/{unit}',
        summary: 'Delete Unit',
        security: [['bearerAuth' => []]],
        tags: ['Unit'],
        parameters: [
            new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit deleted')
        ]
    )]
    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json();
    }
}
