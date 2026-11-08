<?php

namespace App\Http\Controllers\Api\Auth\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthForgotPasswordService
{
    use ApiResponse;

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($validated);

        return $this->successResponse(
            null,
            'If an account exists for that email, a password reset link has been sent.'
        );
    }
}
