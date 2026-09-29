<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->json('label')->nullable();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->string('image_path')->nullable();
            $table->json('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('badge', 10)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('show_in_ticker')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'published_at']);
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('category', 50)->nullable();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('faculty_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('designation')->nullable();
            $table->string('qualification')->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('course_name')->nullable();
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('quote')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('category', 50)->nullable();
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['downloads', 'testimonials', 'faculty_members', 'gallery_items', 'notices', 'sliders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
