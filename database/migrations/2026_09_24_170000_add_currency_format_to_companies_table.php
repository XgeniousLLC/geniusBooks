<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('currency_symbol', 10)->nullable()->after('currency');
            $table->string('currency_position', 10)->default('prefix')->after('currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['currency_symbol', 'currency_position']);
        });
    }
};
