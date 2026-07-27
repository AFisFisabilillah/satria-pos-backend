<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

    public function logout(Request $request){
        $request->user()->tokens()->delete();
        return response([
            "message" => "Logged out.",
            "data"=>null
        ]);
    }

    public function profile(Request $request){
        $user = $request->user();
        return new UserResource($user);
    }
}
