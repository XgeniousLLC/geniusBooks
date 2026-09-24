<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('online_payments_enabled')->default(false)->after('sms_notifications_enabled');
            $table->text('stripe_secret_key')->nullable()->after('online_payments_enabled');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
            $table->foreignId('stripe_deposit_account_id')->nullable()->after('stripe_webhook_secret')
                ->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stripe_deposit_account_id');
            $table->dropColumn(['online_payments_enabled', 'stripe_secret_key', 'stripe_webhook_secret']);
        });
    }
};
