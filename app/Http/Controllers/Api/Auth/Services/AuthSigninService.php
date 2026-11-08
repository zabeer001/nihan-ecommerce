<?php

namespace App\Http\Controllers\Api\Auth\Services;

use App\Exceptions\FrozenAccountException;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Services\Auth\SignInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthSigninService
{
    use ApiResponse;

    public function __construct(
        private readonly SignInService $signInService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $payload = ($this->signInService)([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);
            return $this->successResponse($payload, 'Signed in successfully.');
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse(
                $e->getMessage() ?: 'Failed to sign in.',
                $e instanceof FrozenAccountException ? 403 : 401
            );
        }
    }
}
