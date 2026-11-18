<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HeaderLogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HeaderLogoController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        return $this->successResponse((HeaderLogo::find(1) ?? new HeaderLogo)->toLogoArray());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'before_hover' => ['required_without:after_hover', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'after_hover' => ['required_without:before_hover', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);
        $paths = [];
        $oldPaths = [];
        try {
            foreach (['before_hover', 'after_hover'] as $slot) {
                if ($request->hasFile($slot)) {
                    $paths[$slot.'_path'] = $request->file($slot)->store('header-logos', 'public');
                    abort_if($paths[$slot.'_path'] === false, 500, 'Could not store logo.');
                }
            }
            $logo = DB::transaction(function () use ($paths, &$oldPaths) {
                HeaderLogo::firstOrCreate(['id' => 1]);
                $logo = HeaderLogo::whereKey(1)->lockForUpdate()->firstOrFail();
                foreach ($paths as $field => $path) {
                    if ($logo->$field) $oldPaths[] = $logo->$field;
                }
                $logo->fill($paths)->save();
                return $logo;
            });
        } catch (Throwable $error) {
            Storage::disk('public')->delete(array_values(array_filter($paths)));
            throw $error;
        }
        Storage::disk('public')->delete($oldPaths);

        return $this->successResponse($logo->toLogoArray(), 'Header logos saved.');
    }
}
