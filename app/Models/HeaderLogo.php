<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HeaderLogo extends Model
{
    protected $guarded = [];

    public function toLogoArray(): array
    {
        return [
            'before_hover_url' => $this->before_hover_path ? Storage::disk('public')->url($this->before_hover_path) : null,
            'after_hover_url' => $this->after_hover_path ? Storage::disk('public')->url($this->after_hover_path) : null,
        ];
    }
}
