<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $table->date('exam_date');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('total_obtained')->nullable();
            $table->unsignedInteger('total_max')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade', 5)->nullable();
            $table->string('result', 10)->nullable();
            $table->string('return_remarks')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'status']);
        });

        Schema::create('exam_result_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_subject_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('theory')->nullable();
            $table->unsignedInteger('practical')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->boolean('is_absent')->default(false);
            $table->timestamps();

            $table->unique(['exam_result_id', 'course_subject_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('certificate_no', 30)->unique();
            $table->ulid('verification_token')->unique();
            $table->date('issued_on');
            $table->foreignId('issued_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 20)->default('issued');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('exam_result_marks');
        Schema::dropIfExists('exam_results');
    }
};
