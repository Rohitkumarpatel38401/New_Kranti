<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable');
            $table->string('type', 20);
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city', 100);
            $table->string('district', 100);
            $table->string('state', 100);
            $table->string('pincode', 10)->index();
            $table->char('country_code', 2)->default('IN');
            $table->timestamps();

            $table->unique(['addressable_type', 'addressable_id', 'type']);
            $table->index(['state', 'district']);
        });

        Schema::create('institute_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('owner_phone', 20)->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_photo_path')->nullable();
            $table->string('owner_qualification')->nullable();
            $table->json('about')->nullable();
            $table->unsignedInteger('area_sqft')->nullable();
            $table->unsignedSmallInteger('computers_count')->nullable();
            $table->unsignedSmallInteger('classrooms_count')->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->timestamps();
        });

        Schema::create('institute_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('number', 100)->nullable();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('verification_status', 20)->default('pending');
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'type']);
            $table->index(['verification_status']);
        });

        Schema::create('institute_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('changed_at');

            $table->index(['institute_id', 'changed_at']);
            $table->index(['to_status', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_status_histories');
        Schema::dropIfExists('institute_documents');
        Schema::dropIfExists('institute_profiles');
        Schema::dropIfExists('addresses');
    }
};
