<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class MemberController extends Controller
{
    #[OA\Get(
        path: '/api/members',
        summary: 'List Members',
        security: [['bearerAuth' => []]],
        tags: ['Member'],
        parameters: [
            new OA\Parameter(name: 'name', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'member_code', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'min_total_spent', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'max_total_spent', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of members',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'name' => 'John Doe',
                                'member_code' => 'MEM-12345678',
                                'phone' => '08123456789',
                                'email' => 'john@example.com',
                                'total_spent' => 500000,
                                'active' => true
                            ]
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
        $name = $request->query('name');
        $memberCode = $request->query('member_code');
        $active = $request->query('active');
        $minTotalSpent = $request->query('min_total_spent');
        $maxTotalSpent = $request->query('max_total_spent');
        $size = $request->query('size', 10);

        $query = Member::query();

        $query->when($name, function ($q, $name) {
            $q->where('name', 'like', "%{$name}%");
        });

        $query->when($memberCode, function ($q, $memberCode) {
            $q->where('member_code', 'like', "%{$memberCode}%");
        });

        $query->when($active !== null, function ($q) use ($active) {
            $q->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($minTotalSpent !== null, function ($q) use ($minTotalSpent) {
            $q->where('total_spent', '>=', $minTotalSpent);
        });

        $query->when($maxTotalSpent !== null, function ($q) use ($maxTotalSpent) {
            $q->where('total_spent', '<=', $maxTotalSpent);
        });

        return MemberResource::collection($query->paginate($size));
    }

    #[OA\Post(
        path: '/api/members',
        summary: 'Create Member',
        security: [['bearerAuth' => []]],
        tags: ['Member'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08123456789'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'john@example.com'),
                    new OA\Property(property: 'active', type: 'boolean', default: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Member created',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'member_code' => 'MEM-ABCDEFGH',
                            'phone' => '08123456789',
                            'email' => 'john@example.com',
                            'total_spent' => 0,
                            'active' => true
                        ]
                    ]
                )
            )
        ]
    )]
    public function store(MemberRequest $request)
    {
        $member = Member::create(array_merge($request->validated(), [
            'member_code' => 'MEM-' . strtoupper(Str::random(8)),
            'total_spent' => $request->total_spent ?? 0,
            'active' => $request->has('active') ? $request->active : true,
        ]));

        return new MemberResource($member);
    }

    #[OA\Get(
        path: '/api/members/{member}',
        summary: 'Get Member Details',
        security: [['bearerAuth' => []]],
        tags: ['Member'],
        parameters: [
            new OA\Parameter(name: 'member', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Member details',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'John Doe',
                            'member_code' => 'MEM-ABCDEFGH',
                            'phone' => '08123456789',
                            'email' => 'john@example.com',
                            'total_spent' => 500000,
                            'active' => true
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(Member $member)
    {
        return new MemberResource($member);
    }

    #[OA\Put(
        path: '/api/members/{member}',
        summary: 'Update Member',
        security: [['bearerAuth' => []]],
        tags: ['Member'],
        parameters: [
            new OA\Parameter(name: 'member', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'active', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Member updated',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'John Doe Updated',
                            'member_code' => 'MEM-ABCDEFGH',
                            'phone' => '08123456789',
                            'email' => 'john@example.com',
                            'total_spent' => 500000,
                            'active' => false
                        ]
                    ]
                )
            )
        ]
    )]
    public function update(MemberRequest $request, Member $member)
    {
        $member->update($request->validated());

        return new MemberResource($member);
    }

    #[OA\Delete(
        path: '/api/members/{member}',
        summary: 'Delete Member',
        security: [['bearerAuth' => []]],
        tags: ['Member'],
        parameters: [
            new OA\Parameter(name: 'member', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Member deleted')
        ]
    )]
    public function destroy(Member $member)
    {
        $member->delete();

        return response()->json([
            'message' => 'Member deleted successfully.'
        ]);
    }

    public function toggleActive(Member $member){
        $member->active = !$member->active;
        $member->save();
        return new MemberResource($member);
    }

    public function bulkToggleMember(Request $request ){
        $request->validate([
            "ids" => "required|array",
            "ids.*" => "required|exists:members,id",
        ]);

        Member::whereIn("id", $request->ids)->update([
            "active" => \DB::raw("NOT active"),
        ]);
        return response()->json([
            "message" => "Member activated."
        ]);
    }
}
