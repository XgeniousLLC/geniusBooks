<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matched_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->uuid('import_batch')->nullable();

            $table->date('date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->bigInteger('amount'); // signed: positive = in, negative = out
            $table->string('currency', 3);

            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'bank_account_id']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
