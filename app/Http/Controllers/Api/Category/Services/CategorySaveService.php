<?php

namespace App\Http\Controllers\Api\Category\Services;

use App\Models\Category;
use Illuminate\Http\Request;

class CategorySaveService
{
    public function handle(Request $request, ?int $id = null): Category
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'active' => 'required|boolean',
        ]);

        $category = $id ? Category::findOrFail($id) : new Category;
        $category->fill($validated)->save();

        return $category;
    }
}
