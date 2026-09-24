<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->string('number', 50);
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->string('currency', 3);
            $table->boolean('tax_inclusive')->default(false);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 4)->nullable();

            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 4)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();

            $table->unsignedBigInteger('line_subtotal')->default(0);
            $table->unsignedBigInteger('line_discount')->default(0);
            $table->unsignedBigInteger('line_tax')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
