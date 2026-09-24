<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);
            $table->string('direction', 3);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('description');
            $table->date('occurred_on');

            $table->nullableMorphs('source');
            $table->uuid('transfer_group')->nullable();
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'occurred_on']);
            $table->index(['company_id', 'bank_account_id']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
