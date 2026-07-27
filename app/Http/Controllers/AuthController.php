<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
