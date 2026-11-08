<?php

namespace App\Http\Controllers\Api\Auth\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Services\Auth\SignOutService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AuthSignoutService
{
    use ApiResponse;

    public function __construct(private readonly SignOutService $signOutService)
    {
    }

    public function handle(): JsonResponse
    {
        try {
            $message = ($this->signOutService)();

            return $this->successResponse(null, $message);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Failed to sign out.', 500);
        }
    }
}
