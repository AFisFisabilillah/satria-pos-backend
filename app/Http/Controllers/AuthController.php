<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        summary: 'Login User',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@satriapos.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logged in successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: '1|xyztoken')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function login(LoginRequest $request){
        $data = $request->validated();

        $user = User::where("email", $data["email"])->first();
        if (!$user || !Hash::check($data["password"], $user->password)) {
            return response([
                "message" => "The provided credentials are incorrect.",
                "errors" => "nulls"
            ], 401);
        }
        $token = $user->createToken("Token_Web")->plainTextToken;
        return response([
            "message" => "Logged in successfully.",
            "data"=>[
                "token" =>$token
            ]
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout User',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful logout',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out.'),
                        new OA\Property(property: 'data', example: null)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function logout(Request $request){
        $request->user()->tokens()->delete();
        return response([
            "message" => "Logged out.",
            "data"=>null
        ]);
    }

    #[OA\Get(
        path: '/api/user',
        summary: 'Get Authenticated User Profile',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Super Admin'),
                                new OA\Property(property: 'email', type: 'string', example: 'admin@satriapos.com'),
                                new OA\Property(property: 'foto_profile', type: 'string', nullable: true, example: 'profiles/abc.jpg'),
                                new OA\Property(property: 'role', type: 'string', example: 'super_admin'),
                                new OA\Property(property: 'role_label', type: 'string', example: 'Super Admin')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function profile(Request $request){
        $user = $request->user();
        return new UserResource($user);
    }

    #[OA\Post(
        path: '/api/user',
        summary: 'Update User Profile',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'New Name'),
                        new OA\Property(property: 'foto_profile', type: 'string', format: 'binary')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function updateProfile(UpdateProfileRequest $request){
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('foto_profile')) {
            if ($user->foto_profile) {
                Storage::disk('public')->delete($user->foto_profile);
            }
            $data['foto_profile'] = $request->file('foto_profile')->store('profiles', 'public');
        }

        $user->update($data);

        return response([
            "message" => "Profile updated successfully.",
            "data" => new UserResource($user)
        ]);
    }
}
