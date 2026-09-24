<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('due_reminder_sent_at')->nullable()->after('cancelled_at');
            $table->timestamp('overdue_reminder_sent_at')->nullable()->after('due_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['due_reminder_sent_at', 'overdue_reminder_sent_at']);
        });
    }
};
