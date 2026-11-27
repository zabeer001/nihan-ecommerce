<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontSetting extends Model
{
    protected $fillable = ['gtm_container_id', 'meta_pixel_id', 'meta_title', 'meta_description', 'google_site_verification'];
}
