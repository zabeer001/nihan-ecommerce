<?php

namespace App\Http\Controllers\Api\User\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserShowService
{
    public function handle(User $user): JsonResponse
    {
        $user->loadCount([
            'collectionTapes as collection_items_count',
            'tapes as catalog_entries_count',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $user->only([
                'id',
                'name',
                'email',
                'role',
                'is_frozen',
                'collection_items_count',
                'catalog_entries_count',
                'created_at',
            ]),
        ]);
    }
}
