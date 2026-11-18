<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageContent extends Model
{
    protected $guarded = [];
    protected $casts = ['is_published' => 'boolean', 'position' => 'integer'];

    public function media(): HasMany
    {
        return $this->hasMany(HomepageMedia::class)->orderBy('position')->orderBy('id');
    }

    public function toAdminArray(): array
    {
        return [...$this->attributesToArray(), 'media' => $this->media->map->toMediaArray()->values()->all()];
    }

    public function toStorefrontSlides(): array
    {
        return $this->media->where('is_published', true)->map(function (HomepageMedia $file) {
            $data = [
                ...$file->toMediaArray(), 'content_id' => $this->id,
                'content_type' => $this->placement === 'hero' ? 'hero_slide' : 'image_block',
                'placement' => $this->placement,
            ];
            foreach (['alt_text', 'heading', 'description', 'button_text', 'button_url'] as $field) {
                $data[$field] = $file->$field ?? $this->$field;
            }
            return $data;
        })->values()->all();
    }
}
