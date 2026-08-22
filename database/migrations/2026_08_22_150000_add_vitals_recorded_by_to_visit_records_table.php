<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who measured the vitals, kept apart from who wrote the visit.
 *
 * Outdoor vitals are taken at the desk by prep staff and the doctor completes
 * the same row minutes later, overwriting `recorded_by` with their own id. That
 * left no way to say "the desk took this BP" on the doctor's pad — the only
 * attribution the row carried was already gone by the time the pad rendered.
 *
 * Nulled rather than cascaded on user delete: the reading is a clinical fact
 * that outlives the staff login that keyed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->foreignId('vitals_recorded_by')
                ->nullable()
                ->after('temperature_f')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vitals_recorded_by');
        });
    }
};
