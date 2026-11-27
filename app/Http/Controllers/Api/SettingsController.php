<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StorefrontSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $settings = StorefrontSetting::find(1);

        return $this->successResponse([
            'gtm_container_id' => $settings?->gtm_container_id,
            'meta_title' => $settings?->meta_title,
            'meta_pixel_id' => $settings?->meta_pixel_id,
            'meta_description' => $settings?->meta_description,
            'google_site_verification' => $settings?->google_site_verification,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gtm_container_id' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/\AGTM-[A-Z0-9]+\z/'],
            'meta_title' => 'present|nullable|string|max:255',
            'meta_pixel_id' => ['sometimes', 'nullable', 'string', 'regex:/\A[0-9]{5,30}\z/'],
            'meta_description' => 'present|nullable|string|max:1000',
            'google_site_verification' => ['present', 'nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ]);
        StorefrontSetting::updateOrCreate(['id' => 1], $data);

        return $this->show();
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:api'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return $this->successResponse(null, 'Password changed successfully.');
    }
}
