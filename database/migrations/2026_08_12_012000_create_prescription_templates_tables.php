<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor-curated prescription packs, plus starter advice on the condition list.
 *
 * Two different things on purpose:
 *
 * `prescription_templates` holds **drugs**, and is only ever written by the
 * doctor pressing "Save as pack" on a prescription he just wrote. Nothing
 * ships pre-filled and nothing is inferred from past consultations (owner
 * decision, 2026-08-11) — a drug set attached to a diagnosis is a clinical
 * recommendation, and this product does not make those.
 *
 * `conditions.default_advice` / `default_tests` do ship with content, because
 * "drink plenty of water, avoid spicy food" and "CBC, RBS" are not a
 * recommendation about a medicine. They are proposals the doctor taps, never
 * applied on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            // The pack belongs to the doctor who wrote it, not the chamber:
            // two doctors sharing a clinic prescribe differently.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->foreignUuid('condition_id')->nullable()->constrained('conditions')->nullOnDelete();
            $table->text('advice')->nullable();
            $table->text('tests_advised')->nullable();
            $table->string('follow_up_relative', 32)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id', 'name']);
            // The pad looks packs up by the diagnosis just picked.
            $table->index(['tenant_id', 'user_id', 'condition_id']);
        });

        Schema::create('prescription_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prescription_template_id')
                ->constrained('prescription_templates')
                ->cascadeOnDelete();
            $table->string('medicine_name');
            $table->string('generic_name')->nullable();
            $table->string('dose')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->string('timing', 40)->nullable();
            $table->string('instructions', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('conditions', function (Blueprint $table) {
            $table->text('default_advice')->nullable()->after('category');
            $table->text('default_tests')->nullable()->after('default_advice');
        });
    }

    public function down(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->dropColumn(['default_advice', 'default_tests']);
        });

        Schema::dropIfExists('prescription_template_items');
        Schema::dropIfExists('prescription_templates');
    }
};
