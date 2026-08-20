<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->unsignedInteger('receipt_number')->nullable()->after('note');
            $table->unique(
                ['tenant_id', 'occurred_on', 'receipt_number'],
                'chamber_cash_entries_receipt_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->dropUnique('chamber_cash_entries_receipt_unique');
            $table->dropColumn('receipt_number');
        });
    }
};
