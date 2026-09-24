<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('invoice_items');
    }
};
