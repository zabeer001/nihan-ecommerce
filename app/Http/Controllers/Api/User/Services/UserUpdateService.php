<?php

namespace App\Http\Controllers\Api\User\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserUpdateService
{
    public function handle(Request $request, User $user): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'sometimes|required|string|in:admin,user,editor,superadmin',
            'password' => 'nullable|string|min:8|confirmed',
            'is_frozen' => 'sometimes|boolean',
        ]);

        if (
            array_key_exists('is_frozen', $validatedData)
            && $validatedData['is_frozen']
            && (int) ($request->user()?->id ?? auth('api')->id()) === (int) $user->id
        ) {
            throw ValidationException::withMessages([
                'is_frozen' => ['You cannot freeze your own account.'],
            ]);
        }

        if (empty($validatedData['password'])) {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully.',
            'data' => $user->only(['id', 'name', 'email', 'role', 'is_frozen']),
        ]);
    }
}
