<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('notify_invoice_sent')->default(true)->after('reminder_days_overdue');
            $table->boolean('notify_payment_received')->default(true)->after('notify_invoice_sent');
            $table->boolean('notify_invoice_due')->default(true)->after('notify_payment_received');
            $table->boolean('notify_invoice_overdue')->default(true)->after('notify_invoice_due');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'notify_invoice_sent',
                'notify_payment_received',
                'notify_invoice_due',
                'notify_invoice_overdue',
            ]);
        });
    }
};
