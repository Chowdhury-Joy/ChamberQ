<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingredient pairs that should not normally be prescribed together.
 *
 * Tenant-agnostic, like `medicines` and `conditions` — the pharmacology does
 * not vary by chamber. Deliberately a **short curated list**, not a bulk
 * import: the measurement in `drugs:coverage-report` found that 3.7% of the
 * catalogue is drugs with no entry in any US-derived database at all
 * (doxophylline, bilastine, cilnidipine…), so a Western import would have
 * under-warned on exactly the drugs that distinguish this market. A short list
 * also fights alert fatigue, which is the failure mode that makes doctors
 * ignore warnings altogether.
 *
 * `reviewed_at` / `reviewed_by` start NULL and that is load-bearing: the list
 * is clinical content and carries no authority until a doctor signs it off.
 * `interactions:load` says so on every run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Stored lowercase and alphabetically ordered (a <= b) so a pair is
            // found regardless of which drug the doctor typed first.
            $table->string('ingredient_a', 120);
            $table->string('ingredient_b', 120);
            $table->string('severity', 16)->default('serious');
            $table->string('effect', 255);
            $table->string('action', 255)->nullable();
            $table->string('source', 160)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 160)->nullable();
            $table->timestamps();

            $table->unique(['ingredient_a', 'ingredient_b']);
            $table->index('ingredient_a');
            $table->index('ingredient_b');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_interactions');
    }
};
