<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }

        $user->load('employee.department', 'employee.shift');

        if (! $user->employee || $user->employee->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'This account is inactive or has no employee profile.']);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('users')->sendResetLink($data);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('employee.department', 'employee.shift'));
    }
}
