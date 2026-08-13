<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-drug dosing defaults — layer 2 of the prefill chain.
 *
 * `medicines` could only ever supply `default_strength`, so the picker filled
 * frequency and duration from two literals hardcoded in PHP (`'1+1+1'`,
 * `'5 days'`). That is wrong for most drugs: a PPI is `1+0+0` before food, an
 * antihistamine `0+0+1` at night.
 *
 * These columns are deliberately NOT populated by `medicines:load`. The
 * catalogue is BDDrugBank (a product list — brand, strength, form,
 * manufacturer), which carries no dosing, and no source does: dosing is a
 * clinical judgement, not a property of the product. So these are filled by
 * `dosing-defaults:load` from a separate sheet a doctor signs off, and a
 * BDDrugBank refresh cannot overwrite them.
 *
 * All three stay nullable and are left null wherever no reviewed default
 * exists. Layer 3 is blank, never a guess — see `decisions.md` 2026-08-11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('default_frequency', 40)->nullable()->after('default_strength');
            $table->string('default_duration', 40)->nullable()->after('default_frequency');
            // A key from App\Support\PrescriptionTiming, never free text.
            $table->string('default_timing', 40)->nullable()->after('default_duration');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['default_frequency', 'default_duration', 'default_timing']);
        });
    }
};
