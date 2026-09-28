<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->string('registration_no', 30)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->index();
            $table->string('password')->nullable();
            $table->boolean('can_login')->default(false);
            $table->boolean('must_change_password')->default(true);
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('admission_source', 20);
            $table->date('joined_on');
            $table->foreignId('created_by_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'status', 'joined_on']);
            $table->index(['institute_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
