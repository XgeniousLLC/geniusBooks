<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->string('number', 50);
            $table->date('issue_date');
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('applied');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
