<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('terms');
            $table->string('recurrence_interval', 20)->nullable()->after('is_recurring');
            $table->date('next_recurrence_on')->nullable()->after('recurrence_interval');
            $table->timestamp('last_generated_at')->nullable()->after('next_recurrence_on');
            $table->foreignId('recurrence_parent_id')->nullable()->after('last_generated_at')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn(['is_recurring', 'recurrence_interval', 'next_recurrence_on', 'last_generated_at']);
        });
    }
};
