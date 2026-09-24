<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('number', 50);
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->boolean('tax_inclusive')->default(false);

            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 4)->nullable();

            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
