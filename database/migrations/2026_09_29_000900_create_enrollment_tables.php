<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->string('enrollment_no', 30)->nullable()->unique();
            $table->date('admission_date');
            $table->decimal('course_fee', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('net_fee', 12, 2);
            $table->decimal('hq_fee_snapshot', 12, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->date('completed_on')->nullable();
            $table->foreignId('debit_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'course_id', 'academic_session_id']);
            $table->index(['student_id', 'course_id', 'status']);
        });

        Schema::create('enrollment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason')->nullable();
            $table->nullableMorphs('actor');
            $table->timestamp('changed_at');

            $table->index(['enrollment_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_status_histories');
        Schema::dropIfExists('enrollments');
    }
};
