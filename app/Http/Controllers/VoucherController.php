<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateVoucherRequest;
use App\Http\Requests\VoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class VoucherController extends Controller
{
    #[OA\Get(
        path: '/api/voucher',
        summary: 'List Vouchers',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'minExpired', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'maxExpired', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'minQuota', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'maxQuota', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of vouchers',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'name' => 'Diskon Akhir Tahun',
                                'code' => 'DISC20',
                        'links' => ['first' => '...', 'last' => '...'],
                        'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1]
                    ]]]
                )
            )
        ]
    )]
    public function index()
    {
        $search = request()->query('search');
        $active = request()->query('active');
        $maxExpired = request()->query('maxExpired');
        $minExpired = request()->query('minExpired');
        $minQuota = request()->query('minQuota');
        $maxQuota = request()->query('maxQuota');
        $size = request()->query('size',10);

        $query = Voucher::query();
        $query->when($active, function ($q, $active) {
            return $q->where('active', $active);
        });
        $query->when($maxExpired, function ($q, $maxExpired) {
            return $q->where('expired_at', '<=', $maxExpired);
        });

        $query->when($minExpired, function ($q, $minExpired) {
            return $q->where('expired_at', '>=', $minExpired);
        });
        $query->when($minQuota, function ($q, $minQuota) {
            return $q->where('quota', '>=', $minQuota);
        });
        $query->when($maxQuota, function ($q, $maxQuota) {
            return $q->where('quota', '<=', $maxQuota);
        });
        $query->when($search, function ($q, $search) {
            return $q->where('code', 'like', '%' . $search )->orWhere("name","=", $search);
        });

        return VoucherResource::collection($query->paginate($size));
    }

    #[OA\Post(
        path: '/api/voucher',
        summary: 'Create Voucher',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'code', 'type', 'value', 'quota', 'expired_at'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['fixed', 'percent']),
                    new OA\Property(property: 'value', type: 'integer'),
                    new OA\Property(property: 'min_purchase', type: 'integer', default: 0),
                    new OA\Property(property: 'quota', type: 'integer'),
                    new OA\Property(property: 'expired_at', type: 'string', format: 'date'),
                    new OA\Property(property: 'active', type: 'boolean', default: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Voucher created',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Diskon Akhir Tahun',
                            'code' => 'DISC20',
                            'type' => 'fixed',
                            'value' => 20000,
                            'min_purchase' => 100000,
                            'quota' => 50,
                            'expired_at' => '2026-12-31',
                            'active' => true
                        ]
                    ]
                )
            )
        ]
    )]
    public function store(VoucherRequest $request)
    {
        return new VoucherResource(Voucher::create($request->validated()));
    }

    #[OA\Get(
        path: '/api/voucher/{voucher}',
        summary: 'Get Voucher Detail',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'voucher', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Voucher details',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Diskon Akhir Tahun',
                            'code' => 'DISC20',
                            'type' => 'fixed',
                            'value' => 20000,
                            'min_purchase' => 100000,
                            'quota' => 50,
                            'expired_at' => '2026-12-31',
                            'active' => true
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(Voucher $voucher)
    {
        return new VoucherResource($voucher);
    }

    #[OA\Put(
        path: '/api/voucher/{voucher}',
        summary: 'Update Voucher',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'voucher', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', enum: ['fixed', 'percent']),
                    new OA\Property(property: 'value', type: 'integer'),
                    new OA\Property(property: 'min_purchase', type: 'integer'),
                    new OA\Property(property: 'quota', type: 'integer'),
                    new OA\Property(property: 'expired_at', type: 'string', format: 'date'),
                    new OA\Property(property: 'active', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Voucher updated',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Diskon Akhir Tahun',
                            'code' => 'DISC20',
                            'type' => 'fixed',
                            'value' => 20000,
                            'min_purchase' => 100000,
                            'quota' => 50,
                            'expired_at' => '2026-12-31',
                            'active' => false
                        ]
                    ]
                )
            )
        ]
    )]
    public function update(UpdateVoucherRequest $request, Voucher $voucher)
    {
        $voucher->update($request->validated());

        return new VoucherResource($voucher);
    }

    #[OA\Delete(
        path: '/api/voucher/{voucher}',
        summary: 'Delete Voucher',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'voucher', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Voucher deleted')
        ]
    )]
    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return response()->json([
            "status"=>"success",
            "message"=>"succes delete voucher"
        ]);
    }

    #[OA\Patch(
        path: '/api/voucher/{voucher}/toggle-active',
        summary: 'Toggle Voucher Active Status',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'voucher', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Voucher status toggled')
        ]
    )]
    public function toggleActive(Voucher $voucher){
        $voucher->active= !$voucher->active;
        $voucher->save();
        return response()->json([
            "status"=>"success",
            "message"=>"succes active voucher"
        ]);
    }

    #[OA\Patch(
        path: '/api/voucher/bulk-toggle-active',
        summary: 'Bulk Toggle Active Status for Vouchers',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
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
            new OA\Response(response: 200, description: 'Vouchers status toggled')
        ]
    )]
    public function bulkToggleActive(Request $request){
        $request->validate([
            "ids" => "required",
            "id.*" => "exists:vouchers,id",
        ]);

        Voucher::whereIn('id', $request->ids)
            ->update(['active' => DB::raw('NOT active')]);

        return response()->json([
            'message' => 'Products status toggled successfully.'
        ]);
    }
}
