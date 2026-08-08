<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->decimal('weight_kg', 5, 1)->nullable()->after('diagnosis_uncoded');
            $table->unsignedSmallInteger('bp_systolic')->nullable()->after('weight_kg');
            $table->unsignedSmallInteger('bp_diastolic')->nullable()->after('bp_systolic');
            $table->text('clinical_notes')->nullable()->after('bp_diastolic');
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn(['weight_kg', 'bp_systolic', 'bp_diastolic', 'clinical_notes']);
        });
    }
};
