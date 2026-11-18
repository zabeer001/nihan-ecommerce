<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryUsageGuideTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'guide_test', 'database.connections.guide_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('active');
            $table->json('content_cards')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_11_18_000005_add_usage_guide_to_categories.php'))->up();
        $user = new class extends User {
            public function permissionNames(): array { return ['categories.update', 'categories.create']; }
        };
        $user->forceFill(['id' => 1, 'role' => 'admin', 'is_frozen' => false]);
        $this->actingAs($user, 'api');
    }

    protected function tearDown(): void
    {
        DB::purge('guide_test');
        parent::tearDown();
    }

    private function guide(string $heading = 'Usage guide'): array
    {
        return ['heading' => $heading, 'button_text' => 'Browse', 'button_url' => '/products', 'steps' => [
            ['title' => 'First step', 'description' => 'First instruction.'],
            ['title' => 'Second step', 'description' => 'Second instruction.'],
        ]];
    }

    public function test_each_category_saves_its_own_guide_and_storefront_returns_saved_steps_in_order(): void
    {
        foreach (['Diffuser', 'Refills'] as $name) {
            $guide = $this->guide("How to use $name");
            $this->postJson('/api/admin/categories', ['name' => $name, 'active' => true, 'usage_guide' => $guide])->assertCreated();
        }
        $response = $this->getJson('/api/storefront/categories')->assertOk();
        $response->assertJsonPath('data.0.usage_guide.heading', 'How to use Diffuser')
            ->assertJsonPath('data.1.usage_guide.heading', 'How to use Refills')
            ->assertJsonPath('data.0.usage_guide.steps.0.title', 'First step')
            ->assertJsonPath('data.0.usage_guide.button_url', '/products');
        $first = Category::where('name', 'Diffuser')->firstOrFail();
        $guide = $first->usage_guide;
        $guide['steps'] = array_reverse($guide['steps']);
        $this->putJson("/api/admin/categories/{$first->id}", ['name' => $first->name, 'active' => true, 'usage_guide' => $guide])->assertOk();
        $this->assertSame('Second step', $first->fresh()->usage_guide['steps'][0]['title']);
    }

    public function test_guide_can_be_cleared_and_inactive_categories_stay_private(): void
    {
        $category = Category::create(['name' => 'Hidden', 'active' => false, 'usage_guide' => $this->guide()]);
        $this->getJson('/api/storefront/categories')->assertJsonCount(0, 'data');
        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Hidden', 'active' => true, 'usage_guide' => null])->assertOk();
        $this->getJson('/api/storefront/categories')->assertJsonPath('data.0.usage_guide', null);
    }

    public function test_guide_requires_a_heading_complete_steps_and_safe_button_links(): void
    {
        $guide = $this->guide();
        $guide['heading'] = '';
        $this->postJson('/api/admin/categories', ['name' => 'Diffuser', 'active' => true, 'usage_guide' => $guide])
            ->assertUnprocessable()->assertJsonValidationErrors('usage_guide.heading');
        $guide = $this->guide(); $guide['button_url'] = 'javascript:alert(1)';
        $this->postJson('/api/admin/categories', ['name' => 'Diffuser', 'active' => true, 'usage_guide' => $guide])
            ->assertUnprocessable()->assertJsonValidationErrors('usage_guide.button_url');
        $guide = $this->guide(); $guide['steps'][0]['description'] = '';
        $this->postJson('/api/admin/categories', ['name' => 'Diffuser', 'active' => true, 'usage_guide' => $guide])
            ->assertUnprocessable()->assertJsonValidationErrors('usage_guide.steps.0.description');
        $this->assertSame(0, Category::count());
    }

    public function test_categories_without_a_guide_can_still_be_saved(): void
    {
        $this->postJson('/api/admin/categories', ['name' => 'No guide', 'active' => true])->assertCreated();
        $this->assertNull(Category::firstOrFail()->usage_guide);
    }
}
