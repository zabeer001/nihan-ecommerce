<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HomepageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomepageContentController extends Controller
{
    use ApiResponse;

    public function storefront(): JsonResponse
    {
        $contents = HomepageContent::with('media')->where('is_published', true)
            ->orderBy('placement')->orderBy('position')->orderBy('id')->get();
        return $this->successResponse($contents->flatMap->toStorefrontSlides()->values());
    }

    public function index(): JsonResponse
    {
        return $this->successResponse(HomepageContent::with('media')->orderBy('placement')->orderBy('position')->orderBy('id')->get()->map->toAdminArray());
    }

    public function store(Request $request): JsonResponse
    {
        $content = HomepageContent::create($this->validated($request));
        return $this->successResponse($content->toAdminArray(), 'Homepage content created.', 201);
    }

    public function update(Request $request, HomepageContent $homepageContent): JsonResponse
    {
        $data = $this->validated($request, $homepageContent);
        $items = $request->validate([
            'items' => ['sometimes', 'array', 'list'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.is_published' => ['required', 'boolean'],
        ])['items'] ?? null;
        DB::transaction(function () use ($homepageContent, $data, $items) {
            $homepageContent->newQuery()->whereKey($homepageContent->id)->lockForUpdate()->firstOrFail();
            if ($items !== null) {
                $ids = array_map('intval', array_column($items, 'id'));
                $existing = $homepageContent->media()->pluck('id')->map(fn ($id) => (int) $id)->all();
                sort($ids); sort($existing);
                if ($ids !== $existing) throw ValidationException::withMessages(['items' => 'The file list changed. Reload this page before saving.']);
                foreach ($items as $position => $item) {
                    $homepageContent->media()->whereKey($item['id'])->update(['position' => $position, 'is_published' => $item['is_published']]);
                }
            }
            $homepageContent->fill($data);
            foreach (['alt_text', 'heading', 'description', 'button_text', 'button_url'] as $field) {
                if ($homepageContent->isDirty($field)) $homepageContent->media()->update([$field => null]);
            }
            $homepageContent->save();
        });
        return $this->successResponse($homepageContent->fresh('media')->toAdminArray(), 'Homepage content saved.');
    }

    public function upload(Request $request, HomepageContent $homepageContent): JsonResponse
    {
        $video = $homepageContent->media_type === 'video';
        $request->validate(['media' => ['required', 'file', $video ? 'mimes:mp4,webm' : 'mimes:jpg,jpeg,png,webp', 'max:61440']]);
        $file = $request->file('media');
        $extensions = $video ? ['mp4', 'webm'] : ['jpg', 'jpeg', 'png', 'webp'];
        if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
            throw ValidationException::withMessages(['media' => 'The uploaded file must match the content media type.']);
        }
        $path = $file->store('homepage', 'public');
        abort_if($path === false, 500, 'Could not store homepage media.');
        try {
            $media = DB::transaction(function () use ($homepageContent, $path) {
                $homepageContent->newQuery()->whereKey($homepageContent->id)->lockForUpdate()->firstOrFail();
                return $homepageContent->media()->create([
                    'media_type' => $homepageContent->media_type, 'media_path' => $path,
                    'position' => ($homepageContent->media()->max('position') ?? -1) + 1,
                    'is_published' => true,
                ]);
            });
        } catch (Throwable $error) {
            Storage::disk('public')->delete($path);
            throw $error;
        }
        return $this->successResponse($media->toMediaArray(), 'File uploaded.', 201);
    }

    public function destroyMedia(HomepageContent $homepageContent, int $media): JsonResponse
    {
        $file = $homepageContent->media()->findOrFail($media);
        $paths = array_filter([$file->media_path, $file->poster_path]);
        $file->delete();
        Storage::disk('public')->delete($paths);
        return $this->successResponse(message: 'File removed.');
    }

    public function destroy(HomepageContent $homepageContent): JsonResponse
    {
        $paths = $homepageContent->media->flatMap(fn ($file) => [$file->media_path, $file->poster_path])->filter()->all();
        $homepageContent->delete();
        Storage::disk('public')->delete($paths);
        return $this->successResponse(message: 'Homepage content deleted.');
    }

    private function validated(Request $request, ?HomepageContent $existing = null): array
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(['hero', 'collection_interlude'])],
            'position' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_published' => ['required', 'boolean'],
            'media_type' => ['required', Rule::in(['video', 'image'])],
            'alt_text' => [$request->input('media_type') === 'image' ? 'required' : 'nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:2048'],
        ]);
        if ($existing && $data['placement'] !== $existing->placement) {
            throw ValidationException::withMessages(['placement' => 'Placement cannot be changed for existing content.']);
        }
        if ($data['placement'] === 'hero' && HomepageContent::where('placement', 'hero')->when($existing, fn ($query) => $query->whereKeyNot($existing->id))->exists()) {
            throw ValidationException::withMessages(['placement' => 'A hero carousel already exists. Add files to that carousel.']);
        }
        if (! empty($data['button_url']) && ! preg_match('~^(?:/(?!/)|#|https?://)~i', $data['button_url'])) {
            throw ValidationException::withMessages(['button_url' => 'Use a site path, an anchor, or an HTTP(S) URL.']);
        }
        $data['content_type'] = $data['placement'] === 'hero' ? 'hero_carousel' : 'image_block';
        return $data;
    }
}
