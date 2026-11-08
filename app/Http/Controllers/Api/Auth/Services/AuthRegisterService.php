<?php

namespace App\Http\Controllers\Api\Auth\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Services\Auth\RegisterUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthRegisterService
{
    use ApiResponse;

    public function __construct(
        private readonly RegisterUserService $registerUserService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $payload = ($this->registerUserService)([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            return $this->successResponse($payload, 'Registered successfully.', 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse($e->getMessage() ?: 'Failed to register.', 500);
        }
    }
}
