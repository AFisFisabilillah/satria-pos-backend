<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
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
        $size= $request->query("size", 5);
        $unit = Unit::when($search, function (Builder $query) use ($search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->paginate($size);
        return UnitResource::collection($unit);
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
                    example: ['id' => 3, 'name' => 'Lusin']
                )
            )
        ]
    )]
    public function store(UnitRequest $request)
    {
        $unit = Unit::firstOrCreate(['name' => $request->name]);
        return new UnitResource($unit);
    }

    #[OA\Post(
        path: '/api/units/{unit}',
        summary: 'Update Unit',
        security: [['bearerAuth' => []]],
        tags: ['Unit'],
        parameters: [
            new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Unit created',
                content: new OA\JsonContent(
                    example: ["data" => [
                        'id' => 1, 'name' => 'Pcs'
                    ]]
                )
            )
        ]
    )]
    public function update(Unit $unit, UnitRequest $request){
        $unit->name = $request->name;
        $unit->save();
        return new UnitResource($unit);
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
