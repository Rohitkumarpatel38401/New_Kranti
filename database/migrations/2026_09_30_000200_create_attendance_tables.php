<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('taken_by_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'date']);
            $table->index(['institute_id', 'date']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('status', 10);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'enrollment_id']);
            $table->index(['enrollment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
