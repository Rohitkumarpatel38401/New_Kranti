<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 30)->unique();
            $table->string('applicant_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('proposed_center_name');
            $table->unsignedSmallInteger('computers_count')->nullable();
            $table->unsignedInteger('area_sqft')->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_remarks')->nullable();
            $table->foreignId('converted_institute_id')->nullable()->constrained('institutes')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_applications');
    }
};
