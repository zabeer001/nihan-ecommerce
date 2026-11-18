<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\HomepageContentController;
use App\Models\HomepageContent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class HomepageCarouselTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'carousel_test', 'database.connections.carousel_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('homepage_contents', function (Blueprint $table) {
            $table->id();
            foreach (['content_type', 'placement', 'media_type', 'media_path'] as $column) $table->string($column);
            foreach (['poster_path', 'alt_text', 'heading', 'description', 'button_text', 'button_url'] as $column) $table->string($column)->nullable();
            $table->integer('position');
            $table->boolean('is_published');
            $table->timestamps();
        });
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        DB::purge('carousel_test');
        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_18_000002_create_homepage_media_table.php');
    }

    private function legacy(int $position, bool $published = true, string $placement = 'hero'): HomepageContent
    {
        $path = "homepage/$placement-$position.mp4";
        $poster = "homepage/posters/$placement-$position.jpg";
        Storage::disk('public')->put($path, 'video');
        Storage::disk('public')->put($poster, 'poster');
        return HomepageContent::create([
            'content_type' => $placement === 'hero' ? 'hero_slide' : 'image_block', 'placement' => $placement,
            'media_type' => $placement === 'hero' ? 'video' : 'image', 'media_path' => $path, 'poster_path' => $poster,
            'position' => $position, 'is_published' => $published, 'heading' => "Heading $position",
        ]);
    }

    private function payload(HomepageContent $content): array
    {
        return $content->only(['placement', 'media_type', 'position', 'is_published', 'alt_text', 'heading', 'description', 'button_text', 'button_url']);
    }

    public function test_migration_groups_existing_hero_files_and_preserves_paths_order_and_publication(): void
    {
        $first = $this->legacy(1);
        $draft = $this->legacy(2, false);
        $last = $this->legacy(3);
        $this->legacy(4, true, 'collection_interlude');
        $this->migration()->up();
        $this->assertSame(2, HomepageContent::count());
        $parent = $first->fresh('media');
        $this->assertSame([$first->id, $draft->id, $last->id], $parent->media->pluck('id')->all());
        $this->assertSame('hero_carousel', $parent->content_type);
        $this->assertFalse($parent->media[1]->is_published);
        Storage::disk('public')->assertExists($parent->media->pluck('media_path')->all());
        $data = app(HomepageContentController::class)->storefront()->getData(true)['data'];
        $hero = array_values(array_filter($data, fn ($item) => $item['placement'] === 'hero'));
        $this->assertSame([$first->id, $last->id], array_column($hero, 'id'));
        $this->assertSame('Heading 3', $hero[1]['heading']);
        $admin = app(HomepageContentController::class)->index()->getData(true)['data'];
        $this->assertCount(2, $admin);
    }

    public function test_upload_adds_a_child_without_creating_another_content_record(): void
    {
        $parent = $this->legacy(1);
        $this->migration()->up();
        $parent = $parent->fresh();
        $request = Request::create('/', 'POST', [], [], ['media' => UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4')]);
        app(HomepageContentController::class)->upload($request, $parent);
        $this->assertSame(1, HomepageContent::count());
        $this->assertSame(2, $parent->media()->count());
        Storage::disk('public')->assertExists($parent->media()->get()->pluck('media_path')->all());
    }

    public function test_video_content_rejects_image_uploads(): void
    {
        $parent = $this->legacy(1);
        $this->migration()->up();
        $request = Request::create('/', 'POST', [], [], ['media' => UploadedFile::fake()->image('photo.jpg')]);
        $this->expectException(ValidationException::class);
        app(HomepageContentController::class)->upload($request, $parent->fresh());
    }

    public function test_reordering_children_and_shared_copy_are_reflected_in_storefront(): void
    {
        $first = $this->legacy(1); $second = $this->legacy(2);
        $this->migration()->up();
        $parent = $first->fresh();
        $payload = $this->payload($parent);
        $payload['heading'] = 'Shared carousel heading';
        $payload['items'] = [['id' => $second->id, 'is_published' => true], ['id' => $first->id, 'is_published' => true]];
        app(HomepageContentController::class)->update(Request::create('/', 'PUT', $payload), $parent);
        $data = app(HomepageContentController::class)->storefront()->getData(true)['data'];
        $this->assertSame([$second->id, $first->id], array_column($data, 'id'));
        $this->assertSame(['Shared carousel heading', 'Shared carousel heading'], array_column($data, 'heading'));
    }

    public function test_removing_a_file_preserves_its_parent_and_other_files(): void
    {
        $first = $this->legacy(1); $second = $this->legacy(2);
        $this->migration()->up();
        $parent = $first->fresh('media');
        $file = $parent->media->last();
        app(HomepageContentController::class)->destroyMedia($parent, $file->id);
        $this->assertNotNull($parent->fresh());
        $this->assertSame(1, $parent->media()->count());
        Storage::disk('public')->assertMissing([$file->media_path, $file->poster_path]);
        Storage::disk('public')->assertExists($parent->media->first()->media_path);
    }

    public function test_file_deletion_is_scoped_to_its_parent(): void
    {
        $first = $this->legacy(1); $other = $this->legacy(2, true, 'collection_interlude');
        $this->migration()->up();
        $this->expectException(ModelNotFoundException::class);
        app(HomepageContentController::class)->destroyMedia($first->fresh(), $other->id);
    }

    public function test_deleting_content_removes_all_its_files_and_preserves_other_content(): void
    {
        $first = $this->legacy(1); $this->legacy(2); $other = $this->legacy(3, true, 'collection_interlude');
        $this->migration()->up();
        $parent = $first->fresh('media');
        $paths = $parent->media->flatMap(fn ($file) => [$file->media_path, $file->poster_path])->all();
        app(HomepageContentController::class)->destroy($parent);
        Storage::disk('public')->assertMissing($paths);
        $this->assertSame(1, DB::table('homepage_media')->count());
        $this->assertNotNull($other->fresh());
    }

    public function test_rollback_restores_individual_rows_without_deleting_media(): void
    {
        $this->legacy(1); $this->legacy(2);
        $migration = $this->migration(); $migration->up(); $migration->down();
        $this->assertSame(2, HomepageContent::count());
        $this->assertSame(2, HomepageContent::where('content_type', 'hero_slide')->count());
        Storage::disk('public')->assertExists(HomepageContent::pluck('media_path')->all());
    }

    public function test_collection_content_can_switch_upload_type_and_preserve_existing_media(): void
    {
        $parent = $this->legacy(1, true, 'collection_interlude');
        $this->migration()->up();
        $parent = $parent->fresh();
        $payload = $this->payload($parent);
        $payload['media_type'] = 'video';
        app(HomepageContentController::class)->update(Request::create('/', 'PUT', $payload), $parent);
        $parent = $parent->fresh();
        $this->assertSame('video', $parent->media_type);
        $request = Request::create('/', 'POST', [], [], ['media' => UploadedFile::fake()->create('collection.mp4', 10, 'video/mp4')]);
        app(HomepageContentController::class)->upload($request, $parent);
        $this->assertSame(['image', 'video'], $parent->media()->get()->pluck('media_type')->all());
        $data = app(HomepageContentController::class)->storefront()->getData(true)['data'];
        $this->assertSame(['image', 'video'], array_column($data, 'media_type'));
        $this->assertSame(['collection_interlude', 'collection_interlude'], array_column($data, 'placement'));
    }

    public function test_second_hero_carousel_cannot_be_created(): void
    {
        $first = $this->legacy(1); $this->migration()->up();
        $this->expectException(ValidationException::class);
        app(HomepageContentController::class)->store(Request::create('/', 'POST', $this->payload($first->fresh())));
    }
}
