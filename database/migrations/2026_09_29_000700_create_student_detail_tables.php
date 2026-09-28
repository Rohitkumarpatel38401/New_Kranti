<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_personal_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('dob');
            $table->string('gender', 10);
            $table->string('blood_group', 5)->nullable();
            $table->string('category', 10)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('nationality', 50)->default('Indian');
            $table->string('mother_tongue', 50)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->text('aadhaar_encrypted')->nullable();
            $table->char('aadhaar_hash', 64)->nullable()->unique();
            $table->string('whatsapp_phone', 20)->nullable();
            $table->string('alternate_phone', 20)->nullable();
            $table->string('disability')->nullable();
            $table->timestamps();
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20);
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->decimal('annual_income', 12, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'relation']);
        });

        Schema::create('student_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->string('board_university')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->string('roll_no', 50)->nullable();
            $table->string('marks_type', 12)->default('percentage');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'passing_year']);
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('verification_status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_qualifications');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('student_personal_details');
    }
};
