<?php

namespace App\Http\Controllers\Api\User\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class UserDeleteService
{
    public function handle(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canDeleteUser($user), 403, 'This account cannot be deleted.');

        try {
            $user->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            return response()->json([
                'status' => 'error',
                'message' => 'This user has linked records and cannot be deleted.',
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully.',
        ]);
    }
}
