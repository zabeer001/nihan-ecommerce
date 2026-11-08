<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Auth\Services\AuthForgotPasswordService;
use App\Http\Controllers\Api\Auth\Services\AuthRefreshService;
use App\Http\Controllers\Api\Auth\Services\AuthRegisterService;
use App\Http\Controllers\Api\Auth\Services\AuthResetPasswordService;
use App\Http\Controllers\Api\Auth\Services\AuthSigninService;
use App\Http\Controllers\Api\Auth\Services\AuthSignoutService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct()
    {
        // Refresh validates expired tokens against the JWT refresh window itself.
        $this->middleware('auth:api')->only(['signout']);
        $this->middleware('throttle:20,1');
    }

    public function register(Request $request, AuthRegisterService $service): JsonResponse
    {
        return $service->handle($request);
    }

    public function forgotPassword(Request $request, AuthForgotPasswordService $service): JsonResponse
    {
        return $service->handle($request);
    }

    public function resetPassword(Request $request, AuthResetPasswordService $service): JsonResponse
    {
        return $service->handle($request);
    }

    // sign in

    /**
     * @OA\Post(
     *     path="/api/auth/signin",
     *     tags={"Auth"},
     *     summary="Authenticate a user",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function signin(Request $request, AuthSigninService $service): JsonResponse
    {
        return $service->handle($request);
    }

    // refresh

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Auth"},
     *     summary="Refresh an existing JWT",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Refreshed token issued",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string"),
     *             @OA\Property(property="expires_in", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function refresh(AuthRefreshService $service): JsonResponse
    {
        return $service->handle();
    }

    // signout
    /**
     * @OA\Post(
     *     path="/api/auth/signout",
     *     tags={"Auth"},
     *     summary="Invalidate the current JWT",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Signed out successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function signout(AuthSignoutService $service): JsonResponse
    {
        return $service->handle();
    }

    // frontend

}
