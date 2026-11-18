<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HomepageMedia extends Model
{
    protected $table = 'homepage_media';
    protected $guarded = [];
    protected $casts = ['position' => 'integer', 'is_published' => 'boolean'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(HomepageContent::class, 'homepage_content_id');
    }

    public function toMediaArray(): array
    {
        return [
            'id' => $this->id, 'media_type' => $this->media_type,
            'media_url' => Storage::disk('public')->url($this->media_path),
            'poster_url' => $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null,
            'position' => $this->position, 'is_published' => $this->is_published,
            'alt_text' => $this->alt_text,
        ];
    }
}
