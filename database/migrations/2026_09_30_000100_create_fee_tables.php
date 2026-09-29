<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['enrollment_id', 'sequence']);
            $table->index(['institute_id', 'due_on']);
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no', 30);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('mode', 20);
            $table->string('reference_no', 60)->nullable();
            $table->date('paid_on');
            $table->foreignId('received_by_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->foreignId('received_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 20)->default('valid');
            $table->timestamp('cancelled_at')->nullable();
            $table->nullableMorphs('cancelled_by');
            $table->string('cancel_reason')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'receipt_no']);
            $table->index(['institute_id', 'paid_on']);
            $table->index(['enrollment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_installments');
    }
};
