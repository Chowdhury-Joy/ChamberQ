<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor's own Advice and History chips for the Rx desk.
 *
 * The desk shipped with a fixed five advice lines and nine history codes. They
 * are a starting point, not a clinic's vocabulary — the same list of shortcuts
 * a doctor curates for medicines is what these need to be.
 *
 * `default_key` is what lets a row stand in for one of the built-ins: an edit
 * or a hide of a shipped chip writes a row carrying its key, so the built-in
 * list stays code (translatable, upgradeable) and only the doctor's departures
 * from it live in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_chips', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('default_key', 64)->nullable();
            $table->string('label', 120);
            $table->string('text_bn', 255)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // NULL default_key repeats freely (every custom chip has one), so
            // this constrains exactly what it should: one override per built-in.
            $table->unique(['tenant_id', 'user_id', 'kind', 'default_key']);
            $table->index(['tenant_id', 'user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_chips');
    }
};
