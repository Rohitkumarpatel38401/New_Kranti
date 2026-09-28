<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('source', 30);
            $table->nullableMorphs('source_record');
            $table->string('description');
            $table->nullableMorphs('created_by');
            $table->timestamp('created_at');

            $table->index(['institute_id', 'created_at']);
        });

        // Institutes created before wallets existed.
        $now = now();
        DB::table('institutes')->whereNotIn('id', DB::table('wallets')->select('institute_id'))->pluck('id')
            ->each(fn ($id) => DB::table('wallets')->insert(['institute_id' => $id, 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]));
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
