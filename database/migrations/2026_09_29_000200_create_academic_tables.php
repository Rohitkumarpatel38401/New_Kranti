<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_category_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('duration_value');
            $table->string('duration_unit', 10);
            $table->unsignedSmallInteger('total_hours')->nullable();
            $table->json('eligibility')->nullable();
            $table->decimal('hq_registration_fee', 12, 2)->default(0);
            $table->decimal('suggested_fee', 12, 2)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_on_website')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'show_on_website', 'sort_order']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('course_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('term_no')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('theory_max')->default(0);
            $table->unsignedSmallInteger('practical_max')->default(0);
            $table->unsignedSmallInteger('pass_marks')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'subject_id']);
        });

        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('grade', 5)->unique();
            $table->json('remark')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
        Schema::dropIfExists('course_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_categories');
        Schema::dropIfExists('academic_sessions');
    }
};
