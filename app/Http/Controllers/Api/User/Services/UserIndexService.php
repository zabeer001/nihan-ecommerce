<?php

namespace App\Http\Controllers\Api\User\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserIndexService
{
    public function handle(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('name')->orderBy('id');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', (string) $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->where('is_frozen', $request->input('status') === 'frozen');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20, ['id', 'name', 'email', 'role'])->through(fn (User $user) => [
                ...$user->toArray(),
                'can_change_role' => $request->user()->canChangeRoleOf($user),
            ]),
        ]);
    }
}
