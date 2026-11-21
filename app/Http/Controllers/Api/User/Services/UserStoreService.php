<?php

namespace App\Http\Controllers\Api\User\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserStoreService
{
    public function handle(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role_id' => 'required|integer',
            'password' => 'required|string|min:8|confirmed',
            'is_frozen' => 'sometimes|boolean',
        ]);

        $user = DB::transaction(function () use ($request, $validatedData) {
            $role = Role::query()->where('guard_name', 'api')->lockForUpdate()->findOrFail($validatedData['role_id']);
            abort_unless($request->user()->canAssignRole($role), 403);

            unset($validatedData['role_id']);
            $validatedData['role'] = $role->name;

            return User::create($validatedData);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully.',
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_frozen']),
        ], 201);
    }
}
