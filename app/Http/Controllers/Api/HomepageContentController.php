<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HomepageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomepageContentController extends Controller
{
    use ApiResponse;

    public function storefront(): JsonResponse
    {
        $contents = HomepageContent::query()->where('is_published', true)
            ->orderBy('placement')->orderBy('position')->orderBy('id')->get();

        return $this->successResponse($contents->map->toStorefrontArray()->values());
    }

    public function index(): JsonResponse
    {
        $contents = HomepageContent::query()->orderBy('placement')->orderBy('position')->orderBy('id')->get();
        return $this->successResponse($contents->map->toStorefrontArray()->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $content = new HomepageContent();
        $this->saveContent($content, $request, $data);
        return $this->successResponse($content->toStorefrontArray(), 'Homepage content created.', 201);
    }

    public function update(Request $request, HomepageContent $homepageContent): JsonResponse
    {
        $data = $this->validated($request, $homepageContent);
        $this->saveContent($homepageContent, $request, $data);
        return $this->successResponse($homepageContent->toStorefrontArray(), 'Homepage content updated.');
    }

    public function destroy(HomepageContent $homepageContent): JsonResponse
    {
        foreach ([$homepageContent->media_path, $homepageContent->poster_path] as $path) {
            if ($path) Storage::disk('public')->delete($path);
        }
        $homepageContent->delete();
        return $this->successResponse(message: 'Homepage content deleted.');
    }

    private function validated(Request $request, ?HomepageContent $existing = null): array
    {
        $rules = [
            'content_type' => ['required', Rule::in(['hero_slide', 'image_block'])],
            'placement' => ['required', Rule::in(['hero', 'collection_interlude'])],
            'position' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_published' => ['required', 'boolean'],
            'media_type' => ['required', Rule::in(['video', 'image'])],
            'media' => [$existing ? 'sometimes' : 'required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,webm', 'max:61440'],
            'poster' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:2048'],
        ];
        if ($request->input('media_type') === 'image') $rules['alt_text'] = ['required', 'string', 'max:255'];
        $data = $request->validate($rules);
        if (($data['placement'] === 'hero') !== ($data['content_type'] === 'hero_slide')) {
            abort(422, 'Hero placement requires a hero slide, and collection placement requires an image block.');
        }
        if ($data['placement'] === 'collection_interlude' && $data['media_type'] !== 'image') {
            abort(422, 'Collection image content must use image media.');
        }
        if ($existing && $data['media_type'] !== $existing->media_type && ! $request->hasFile('media')) {
            throw ValidationException::withMessages(['media' => 'Upload a new file when changing the media type.']);
        }
        if ($request->hasFile('media')) {
            $extension = strtolower($request->file('media')->getClientOriginalExtension());
            $image = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
            if (($data['media_type'] === 'image') !== $image) {
                throw ValidationException::withMessages(['media' => 'The uploaded file must match the selected media type.']);
            }
        }
        if (! empty($data['button_url']) && ! preg_match('~^(?:/(?!/)|#|https?://)~i', $data['button_url'])) {
            throw ValidationException::withMessages(['button_url' => 'Use a site path, an anchor, or an HTTP(S) URL.']);
        }
        return $data;
    }

    private function saveContent(HomepageContent $content, Request $request, array $data): void
    {
        unset($data['media'], $data['poster']);
        $oldMedia = $content->media_path;
        $oldPoster = $content->poster_path;
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('homepage', 'public');
            abort_if($path === false, 500, 'Could not store homepage media.');
            $data['media_path'] = $path;
        }
        if ($request->hasFile('poster')) {
            $path = $request->file('poster')->store('homepage/posters', 'public');
            abort_if($path === false, 500, 'Could not store the homepage poster.');
            $data['poster_path'] = $path;
        }
        $content->fill($data)->save();
        if ($oldMedia && $oldMedia !== $content->media_path) Storage::disk('public')->delete($oldMedia);
        if ($oldPoster && $oldPoster !== $content->poster_path) Storage::disk('public')->delete($oldPoster);
    }
}
