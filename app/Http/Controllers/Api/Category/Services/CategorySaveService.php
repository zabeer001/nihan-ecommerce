<?php

namespace App\Http\Controllers\Api\Category\Services;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategorySaveService
{
    public function handle(Request $request, ?int $id = null): Category
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'active' => 'required|boolean',
            'usage_guide' => 'sometimes|nullable|array:heading,button_text,button_url,steps',
            'usage_guide.heading' => 'nullable|string|max:255',
            'usage_guide.button_text' => 'nullable|required_with:usage_guide.button_url|string|max:80',
            'usage_guide.button_url' => 'nullable|required_with:usage_guide.button_text|string|max:2048',
            'usage_guide.steps' => 'sometimes|array|list|max:12',
            'usage_guide.steps.*' => 'required|array:title,description',
            'usage_guide.steps.*.title' => 'required|string|max:150',
            'usage_guide.steps.*.description' => 'required|string|max:2000',
            'content_cards' => 'sometimes|nullable|array|list|max:4',
            'content_cards.*' => 'required|array:label,title,description,color',
            'content_cards.*.label' => 'required|string|max:80',
            'content_cards.*.title' => 'required|string|max:150',
            'content_cards.*.description' => 'required|string|max:500',
            'content_cards.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $guide = $validated['usage_guide'] ?? null;
        if (! empty($guide['steps']) && blank($guide['heading'] ?? null)) {
            throw ValidationException::withMessages(['usage_guide.heading' => 'Add a heading for the usage guide.']);
        }
        if (! empty($guide['button_url']) && ! preg_match('~^(?:/(?!/)|#|https?://)~i', $guide['button_url'])) {
            throw ValidationException::withMessages(['usage_guide.button_url' => 'Use a site path, an anchor, or an HTTP(S) URL.']);
        }

        $category = $id ? Category::findOrFail($id) : new Category;
        $category->fill($validated)->save();

        return $category;
    }
}
