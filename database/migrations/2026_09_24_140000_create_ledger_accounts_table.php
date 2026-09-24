<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('type', 20);
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
