<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomepageContent extends Model
{
    protected $guarded = [];

    protected $casts = ['is_published' => 'boolean', 'position' => 'integer'];

    public function toStorefrontArray(): array
    {
        return [
            'id' => $this->id,
            'content_type' => $this->content_type,
            'placement' => $this->placement,
            'position' => $this->position,
            'media_type' => $this->media_type,
            'media_url' => Storage::disk('public')->url($this->media_path),
            'poster_url' => $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null,
            'alt_text' => $this->alt_text,
            'heading' => $this->heading,
            'description' => $this->description,
            'button_text' => $this->button_text,
            'button_url' => $this->button_url,
            'is_published' => $this->is_published,
        ];
    }
}
