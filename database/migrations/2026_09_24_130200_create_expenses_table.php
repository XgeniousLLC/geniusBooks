<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->string('currency', 3);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedInteger('attachment_size')->nullable();

            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_interval', 20)->nullable();
            $table->date('next_recurrence_on')->nullable();
            $table->timestamp('last_generated_at')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'date']);
            $table->index(['company_id', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
