<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'username' => $validated['username'],
            'name' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Hash cast automatically hashes it
            'role' => 'user',
            'minecraft_username' => $validated['minecraftUsername'] ?? '',
            'skin_url' => '',
            'coins' => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công! Chào mừng bạn đến với Đảo Tò Mò 🎉',
            'token' => $token,
            'user' => (new UserResource($user))->toArray($request),
        ], 201);
    }

    /**
     * Handle user login.
     */
    public function login(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng điền đầy đủ email và mật khẩu.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công! Chào mừng bạn trở lại 🏝️',
            'token' => $token,
            'user' => (new UserResource($user))->toArray($request),
        ], 200);
    }
}
