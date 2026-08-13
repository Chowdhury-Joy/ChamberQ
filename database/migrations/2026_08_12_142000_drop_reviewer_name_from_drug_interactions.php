<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the per-pair reviewer name.
 *
 * Owner decision, 2026-08-12: no doctor's name is to be recorded against
 * clinical content anywhere in the product. Naming an individual reviewer puts
 * personal liability on them for a list the practice ships, which is not how
 * drug references work — the publisher carries it, not a named clinician.
 *
 * The safety requirement it existed to serve does not disappear; it moves from
 * an attribution to a standing disclaimer shown with every warning
 * (`RxSafety::DISCLAIMER`), which every doctor sees every time rather than a
 * field nobody reads.
 *
 * `reviewed_at` stays: recording *that* the list was last checked, on a date,
 * carries no name and is still useful for knowing how stale it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drug_interactions', function (Blueprint $table) {
            $table->dropColumn('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('drug_interactions', function (Blueprint $table) {
            $table->string('reviewed_by', 160)->nullable()->after('reviewed_at');
        });
    }
};
