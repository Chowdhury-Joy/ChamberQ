<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->json('extra_fees')->nullable()->after('default_fee_taka');
        });

        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->string('fee_type')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->dropColumn('fee_type');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('extra_fees');
        });
    }
};
