<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('country', 2)->nullable();

            $table->string('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');

            $table->unsignedTinyInteger('financial_year_start_month')->default(1);
            $table->unsignedTinyInteger('financial_year_start_day')->default(1);

            $table->string('tax_registration_number')->nullable();
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('default_tax_rate', 8, 4)->default(0);

            $table->string('invoice_prefix', 20)->default('INV-');
            $table->unsignedTinyInteger('invoice_number_padding')->default(4);
            $table->unsignedSmallInteger('default_payment_terms_days')->default(15);

            $table->boolean('is_active')->default(true);
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
