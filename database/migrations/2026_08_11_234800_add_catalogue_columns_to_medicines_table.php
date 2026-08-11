<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `medicines` for the full Bangladesh catalogue.
 *
 * The catalogue went from a curated 460 to 24,491 SKUs (16,029 brands), so
 * the table now carries one row per brand + strength + form rather than one
 * per brand — `brand_name` was already indexed rather than unique, so no
 * constraint had to change, but the lookup index does.
 *
 * `priority` is the safety mechanism that replaces exclusion: everything is
 * present, but a chamber doctor sees pinned and curated brands before the
 * long tail, and parenteral/chemo SKUs last.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('indications', 160)->nullable()->after('category');
            $table->string('manufacturer', 160)->nullable()->after('indications');
            $table->boolean('is_essential')->default(false)->after('manufacturer');
            // 0 pinned · 1 curated · 2 essential · 3 standard · 4 specialist.
            $table->unsignedTinyInteger('priority')->default(3)->after('is_essential');
        });

        Schema::table('medicines', function (Blueprint $table) {
            // The loader upserts on this triple, once per row, 24k times.
            $table->index(['brand_name', 'default_strength', 'form'], 'medicines_sku_index');
            // Search ranks by tier then filters by essentials.
            $table->index(['priority', 'brand_name'], 'medicines_priority_index');
            $table->index('generic_name', 'medicines_generic_index');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('medicines_sku_index');
            $table->dropIndex('medicines_priority_index');
            $table->dropIndex('medicines_generic_index');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['indications', 'manufacturer', 'is_essential', 'priority']);
        });
    }
};
