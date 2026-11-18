<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductReviewController extends Controller
{
    use ApiResponse;

    public function homepage(): JsonResponse
    {
        $reviews = ProductReview::where('status', 'published')
            ->whereHas('product', fn ($query) => $query->where('status', 'active')
                ->whereHas('categories', fn ($query) => $query->where('active', true))
                ->whereHas('variants', fn ($query) => $query->where('active', true)))
            ->with('product:id,name,slug')->latest('id')->paginate(9);
        return response()->json(['data' => $reviews->getCollection()->map(fn (ProductReview $review) => [
            ...$review->toPublicArray(),
            'product_name' => $review->product->name, 'product_slug' => $review->product->slug,
        ]), 'current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage()]);
    }

    public function storefront(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedProduct($slug);
        $query = $product->reviews()->where('status', 'published');
        $count = (clone $query)->count();
        $average = $count ? round((float) (clone $query)->avg('rating'), 1) : null;
        $reviews = $query->latest('id')->paginate(10);
        return response()->json([
            'data' => $reviews->getCollection()->map->toPublicArray(),
            'current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(),
            'summary' => ['count' => $count, 'average' => $average],
        ]);
    }

    public function submit(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedProduct($slug);
        $product->reviews()->create([...$this->validated($request), 'status' => 'pending', 'source' => 'customer']);
        return $this->successResponse(message: 'Thank you. Your review will appear after approval.', status: 201);
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['pending', 'published', 'rejected'])]]);
        return $this->successResponse($product->reviews()->when($filters['status'] ?? null,
            fn ($query, $status) => $query->where('status', $status))->latest('id')->paginate(15));
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $review = $product->reviews()->create([...$this->validated($request, true), 'source' => 'admin']);
        return $this->successResponse($review, 'Review added.', 201);
    }

    public function update(Request $request, Product $product, int $review): JsonResponse
    {
        $item = $product->reviews()->findOrFail($review);
        $item->update($this->validated($request, true));
        return $this->successResponse($item, 'Review saved.');
    }

    public function destroy(Product $product, int $review): JsonResponse
    {
        $product->reviews()->findOrFail($review)->delete();
        return $this->successResponse(message: 'Review deleted.');
    }

    private function publishedProduct(string $slug): Product
    {
        return Product::where('slug', $slug)->where('status', 'active')
            ->whereHas('categories', fn ($query) => $query->where('active', true))
            ->whereHas('variants', fn ($query) => $query->where('active', true))->firstOrFail();
    }

    private function validated(Request $request, bool $admin = false): array
    {
        return $request->validate([
            'author_name' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:5', 'max:3000'],
            ...($admin ? ['status' => ['required', Rule::in(['pending', 'published', 'rejected'])]] : []),
        ]);
    }
}
