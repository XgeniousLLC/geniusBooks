<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('status', 20)->default('disconnected');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('external_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
