<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('payment_instructions')->nullable()->after('tax_registration_number');
            $table->text('invoice_footer')->nullable()->after('payment_instructions');
            $table->string('email_from_name')->nullable()->after('invoice_footer');
            $table->string('email_reply_to')->nullable()->after('email_from_name');
            $table->boolean('reminders_enabled')->default(true)->after('email_reply_to');
            $table->unsignedSmallInteger('reminder_days_before')->default(3)->after('reminders_enabled');
            $table->unsignedSmallInteger('reminder_days_overdue')->default(1)->after('reminder_days_before');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'payment_instructions',
                'invoice_footer',
                'email_from_name',
                'email_reply_to',
                'reminders_enabled',
                'reminder_days_before',
                'reminder_days_overdue',
            ]);
        });
    }
};
