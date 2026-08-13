<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temperature as a first-class vital on the Rx desk O/E table.
 *
 * Chamber GPs record °F. Last visit's reading is grey reference only — never
 * pre-filled — same rule as weight / BP / pulse / SpO₂.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->decimal('temperature_f', 4, 1)->nullable()->after('spo2_percent');
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn('temperature_f');
        });
    }
};
