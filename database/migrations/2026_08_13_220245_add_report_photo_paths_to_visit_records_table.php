<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos of reports the patient brought (lab printout, X-ray), separate from
 * the handwritten prescription slip (`photo_path`). Staff may attach these;
 * they may not write the typed `reports_seen` note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->json('report_photo_paths')->nullable()->after('reports_seen');
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn('report_photo_paths');
        });
    }
};
