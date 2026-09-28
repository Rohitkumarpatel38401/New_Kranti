<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_main')->default(false);
            $table->unsignedTinyInteger('main_marker')->nullable()
                ->storedAs('case when is_main = 1 then 1 else null end')
                ->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 20)->nullable()->index();
            $table->string('alt_phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('registration_no', 100)->nullable()->unique();
            $table->string('gst_no', 20)->nullable()->unique();
            $table->string('pan_no', 20)->nullable();
            $table->date('established_on')->nullable();
            $table->date('franchise_start_on')->nullable();
            $table->date('franchise_expiry_on')->nullable()->index();
            $table->boolean('wallet_exempt')->default(false);
            $table->unsignedBigInteger('approved_by_admin_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutes');
    }
};
