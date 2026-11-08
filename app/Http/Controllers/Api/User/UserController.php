<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\User\Services\UserDeleteService;
use App\Http\Controllers\Api\User\Services\UserIndexService;
use App\Http\Controllers\Api\User\Services\UserShowService;
use App\Http\Controllers\Api\User\Services\UserStoreService;
use App\Http\Controllers\Api\User\Services\UserUpdateService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api', 'active_account', 'role:admin,superadmin']);
        $this->middleware('throttle:20,1');
    }

    /**
     * Display a listing of the resource (READ all).
     */
    public function index(Request $request, UserIndexService $service): JsonResponse
    {
        return $service->handle($request);
    }

    /**
     * Store a newly created resource in storage (CREATE).
     */
    public function store(Request $request, UserStoreService $service): JsonResponse
    {
        return $service->handle($request);
    }

    /**
     * Display the specified resource (READ one).
     */
    public function show(User $user, UserShowService $service): JsonResponse
    {
        return $service->handle($user);
    }

    /**
     * Update the specified resource in storage (UPDATE).
     */
    public function update(Request $request, User $user, UserUpdateService $service): JsonResponse
    {
        return $service->handle($request, $user);
    }

    /**
     * Remove the specified resource from storage (DELETE).
     */
    public function destroy(User $user, UserDeleteService $service): JsonResponse
    {
        return $service->handle($user);
    }
}
