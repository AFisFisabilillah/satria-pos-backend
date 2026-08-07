<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        summary: 'List Users',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of users',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            [
                                'id' => 1,
                                'name' => 'Admin User',
                                'email' => 'admin@satriapos.com',
                                'role' => 'super_admin',
                                'role_label' => 'Super Admin'
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $users = User::all();

        return UserResource::collection($users);
    }

    #[OA\Post(
        path: '/api/users',
        summary: 'Create User',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'role', type: 'string', enum: ['super_admin', 'cashier'], default: 'cashier')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 2,
                            'name' => 'Cashier Satu',
                            'email' => 'cashier@satriapos.com',
                            'role' => 'cashier',
                            'role_label' => 'Cashier'
                        ]
                    ]
                )
            )
        ]
    )]
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return new UserResource($user);
    }

    #[OA\Get(
        path: '/api/users/{user}',
        summary: 'Get User Details',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Admin User',
                            'email' => 'admin@satriapos.com',
                            'role' => 'super_admin',
                            'role_label' => 'Super Admin'
                        ]
                    ]
                )
            )
        ]
    )]
    public function show(User $user)
    {
        return new UserResource($user);
    }

    #[OA\Put(
        path: '/api/users/{user}',
        summary: 'Update User',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true),
                    new OA\Property(property: 'role', type: 'string', enum: ['super_admin', 'cashier'])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated',
                content: new OA\JsonContent(
                    example: [
                        'data' => [
                            'id' => 1,
                            'name' => 'Admin Updated',
                            'email' => 'admin@satriapos.com',
                            'role' => 'super_admin',
                            'role_label' => 'Super Admin'
                        ]
                    ]
                )
            )
        ]
    )]
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return new UserResource($user);
    }

    #[OA\Delete(
        path: '/api/users/{user}',
        summary: 'Delete User',
        security: [['bearerAuth' => []]],
        tags: ['User Management'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 403, description: 'Cannot delete current user')
        ]
    )]
    public function destroy(User $user): JsonResponse
    {
        // Prevent users from deleting themselves
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun yang sedang digunakan'
            ], 403);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
