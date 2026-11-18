<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_content_id')->constrained('homepage_contents')->cascadeOnDelete();
            $table->string('media_type', 16);
            $table->string('media_path');
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->string('alt_text')->nullable();
            $table->string('heading')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->timestamps();
            $table->index(['homepage_content_id', 'position']);
        });

        DB::transaction(function () {
            $rows = DB::table('homepage_contents')->orderBy('position')->orderBy('id')->get();
            $hero = $rows->firstWhere('placement', 'hero');
            foreach ($rows as $row) {
                $parentId = $row->placement === 'hero' ? $hero->id : $row->id;
                DB::table('homepage_media')->insert([
                    'id' => $row->id, 'homepage_content_id' => $parentId,
                    'media_type' => $row->media_type, 'media_path' => $row->media_path,
                    'poster_path' => $row->poster_path, 'position' => $row->position,
                    'is_published' => $row->is_published, 'alt_text' => $row->alt_text,
                    'heading' => $row->heading, 'description' => $row->description,
                    'button_text' => $row->button_text, 'button_url' => $row->button_url,
                    'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                ]);
            }
            if ($hero) {
                DB::table('homepage_contents')->where('placement', 'hero')->where('id', '!=', $hero->id)->delete();
                DB::table('homepage_contents')->where('id', $hero->id)->update([
                    'content_type' => 'hero_carousel',
                    'is_published' => $rows->where('placement', 'hero')->contains(fn ($row) => (bool) $row->is_published),
                ]);
            }
        });
        Schema::table('homepage_contents', fn (Blueprint $table) => $table->dropColumn(['media_path', 'poster_path']));
    }

    public function down(): void
    {
        Schema::table('homepage_contents', function (Blueprint $table) {
            $table->string('media_path')->default('');
            $table->string('poster_path')->nullable();
        });
        DB::transaction(function () {
            foreach (DB::table('homepage_contents')->get() as $parent) {
                $files = DB::table('homepage_media')->where('homepage_content_id', $parent->id)->orderBy('position')->orderBy('id')->get();
                foreach ($files as $index => $file) {
                    $row = (array) $parent;
                    unset($row['id']);
                    foreach (['media_type', 'media_path', 'poster_path', 'position', 'alt_text', 'heading', 'description', 'button_text', 'button_url'] as $field) $row[$field] = $file->$field;
                    $row['is_published'] = $parent->is_published && $file->is_published;
                    if ($parent->placement === 'hero') $row['content_type'] = 'hero_slide';
                    if ($index === 0) DB::table('homepage_contents')->where('id', $parent->id)->update($row);
                    else DB::table('homepage_contents')->insert($row);
                }
            }
        });
        Schema::dropIfExists('homepage_media');
    }
};
