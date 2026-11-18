<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductReviewsTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'review_test', 'database.connections.review_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug'); $table->string('status'); $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('active'); $table->timestamps();
        });
        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id'); $table->foreignId('product_id');
        });
        Schema::create('variants', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_id'); $table->boolean('active'); $table->timestamps();
        });
        (require database_path('migrations/2026_11_18_000003_create_product_reviews_table.php'))->up();
        $this->product = Product::create(['name' => 'Diffuser', 'slug' => 'diffuser', 'status' => 'active']);
        $category = \App\Models\Category::create(['name' => 'Diffusers', 'active' => true]);
        $this->product->categories()->attach($category);
        $this->product->variants()->create(['active' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('review_test');
        parent::tearDown();
    }

    private function payload(array $extra = []): array
    {
        return [...['author_name' => 'Reviewer', 'rating' => 5, 'title' => 'Great product', 'body' => 'Works well in my car.'], ...$extra];
    }

    private function staff(bool $write = true): void
    {
        $user = new class extends User {
            public array $reviewPermissions = [];
            public function permissionNames(): array { return $this->reviewPermissions; }
        };
        $user->forceFill(['id' => 1, 'name' => 'Staff', 'role' => 'editor', 'is_frozen' => false]);
        $user->reviewPermissions = $write ? ['products.view', 'products.update'] : ['products.view'];
        $this->actingAs($user, 'api');
    }

    public function test_customer_submission_is_pending_and_cannot_publish_or_impersonate_an_admin(): void
    {
        $this->postJson('/api/storefront/products/diffuser/reviews', $this->payload(['status' => 'published', 'source' => 'admin', 'product_id' => 999]))->assertCreated();
        $review = $this->product->reviews()->firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertSame('customer', $review->source);
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('summary.count', 0);
    }

    public function test_only_published_reviews_and_their_ratings_are_public(): void
    {
        foreach ([['published', 5], ['published', 3], ['pending', 1], ['rejected', 1]] as [$status, $rating]) {
            $this->product->reviews()->create($this->payload(['status' => $status, 'rating' => $rating, 'source' => 'admin']));
        }
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.count', 2)->assertJsonPath('summary.average', 4)
            ->assertJsonMissingPath('data.0.source')->assertJsonMissingPath('data.0.status');
    }

    public function test_admin_can_manually_add_edit_publish_and_delete_a_product_review(): void
    {
        $this->staff();
        $base = "/api/admin/products/{$this->product->id}/reviews";
        $id = $this->postJson($base, $this->payload(['status' => 'published']))->assertCreated()->assertJsonPath('data.source', 'admin')->json('data.id');
        $this->putJson("$base/$id", $this->payload(['status' => 'pending', 'rating' => 4]))->assertOk()->assertJsonPath('data.rating', 4);
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertJsonCount(0, 'data');
        $this->putJson("$base/$id", $this->payload(['status' => 'published']))->assertOk();
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertJsonCount(1, 'data');
        $this->deleteJson("$base/$id")->assertOk();
        $this->assertSame(0, $this->product->reviews()->count());
    }

    public function test_admin_routes_require_authentication_and_product_update_permission(): void
    {
        $base = "/api/admin/products/{$this->product->id}/reviews";
        $this->postJson($base, $this->payload(['status' => 'published']))->assertUnauthorized();
        $this->staff(false);
        $this->getJson($base)->assertOk();
        $this->postJson($base, $this->payload(['status' => 'published']))->assertForbidden();
    }

    public function test_customer_review_can_be_approved_without_changing_its_source(): void
    {
        $this->postJson('/api/storefront/products/diffuser/reviews', $this->payload())->assertCreated();
        $this->staff();
        $review = $this->product->reviews()->firstOrFail();
        $this->putJson("/api/admin/products/{$this->product->id}/reviews/{$review->id}", $this->payload(['status' => 'published', 'source' => 'admin']))
            ->assertOk()->assertJsonPath('data.source', 'customer');
    }

    public function test_reviews_are_scoped_to_their_product(): void
    {
        $other = Product::create(['name' => 'Refill', 'slug' => 'refill', 'status' => 'draft']);
        $review = $other->reviews()->create($this->payload(['status' => 'published']));
        $this->staff();
        $base = "/api/admin/products/{$this->product->id}/reviews/{$review->id}";
        $this->putJson($base, $this->payload(['status' => 'published']))->assertNotFound();
        $this->deleteJson($base)->assertNotFound();
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertJsonCount(0, 'data');
    }

    public function test_invalid_ratings_and_empty_reviews_are_rejected(): void
    {
        $this->postJson('/api/storefront/products/diffuser/reviews', $this->payload(['rating' => 6, 'body' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['rating', 'body']);
        $this->assertSame(0, $this->product->reviews()->count());
    }

    public function test_draft_products_do_not_accept_or_expose_reviews(): void
    {
        $this->product->update(['status' => 'draft']);
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertNotFound();
        $this->postJson('/api/storefront/products/diffuser/reviews', $this->payload())->assertNotFound();
    }

    public function test_homepage_shows_published_reviews_for_available_products_only(): void
    {
        $ids = [];
        for ($i = 0; $i < 4; $i++) $ids[] = $this->product->reviews()->create($this->payload(['status' => 'published']))->id;
        $this->product->reviews()->create($this->payload(['status' => 'pending']));
        $this->product->reviews()->create($this->payload(['status' => 'rejected']));
        $draft = Product::create(['name' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
        $draft->reviews()->create($this->payload(['status' => 'published']));
        $response = $this->getJson('/api/storefront/reviews')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $ids[3])->assertJsonPath('data.0.product_slug', 'diffuser')
            ->assertJsonMissingPath('data.0.status')->assertJsonMissingPath('data.0.source');
        $this->assertSame(array_reverse($ids), array_column($response->json('data'), 'id'));
        $this->product->categories()->update(['active' => false]);
        $this->getJson('/api/storefront/reviews')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_all_homepage_reviews_can_be_reached_through_pagination(): void
    {
        for ($i = 0; $i < 12; $i++) $this->product->reviews()->create($this->payload(['status' => 'published']));
        $first = $this->getJson('/api/storefront/reviews')->assertOk()->assertJsonCount(9, 'data')->assertJsonPath('last_page', 2)->json('data');
        $second = $this->getJson('/api/storefront/reviews?page=2')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('current_page', 2)->json('data');
        $this->assertCount(12, array_unique(array_column([...$first, ...$second], 'id')));
    }

    public function test_public_reviews_are_paginated(): void
    {
        for ($i = 0; $i < 12; $i++) $this->product->reviews()->create($this->payload(['status' => 'published']));
        $this->getJson('/api/storefront/products/diffuser/reviews')->assertJsonCount(10, 'data')->assertJsonPath('last_page', 2)->assertJsonPath('summary.count', 12);
        $this->getJson('/api/storefront/products/diffuser/reviews?page=2')->assertJsonCount(2, 'data');
    }
}
