<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->string('method', 20)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
