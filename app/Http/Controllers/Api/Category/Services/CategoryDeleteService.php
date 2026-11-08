<?php

namespace App\Http\Controllers\Api\Category\Services;

use App\Models\Category;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CategoryDeleteService
{
    public function handle(int $id): Response
    {
        $category = Category::findOrFail($id);
        if ($category->products()->exists()) {
            throw ValidationException::withMessages(['category' => 'Category is in use.']);
        }
        $category->delete();

        return response()->noContent();
    }
}
