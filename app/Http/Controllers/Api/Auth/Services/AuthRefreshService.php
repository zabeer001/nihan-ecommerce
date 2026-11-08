<?php

namespace App\Http\Controllers\Api\Auth\Services;

use App\Exceptions\FrozenAccountException;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Throwable;

class AuthRefreshService
{
    use ApiResponse;

    public function __construct(private readonly RefreshTokenService $refreshTokenService) {}

    public function handle(): JsonResponse
    {
        try {
            $payload = ($this->refreshTokenService)();

            return $this->successResponse($payload, 'Token refreshed successfully.');
        } catch (FrozenAccountException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (JWTException|AuthenticationException $e) {
            return $this->errorResponse('Your session has expired. Please sign in again.', 401);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to refresh session right now. Please retry.', 500);
        }
    }
}
