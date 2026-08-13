<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->text('chief_complaint')->nullable()->after('clinical_notes');
            $table->text('history')->nullable()->after('chief_complaint');
            $table->text('on_examination')->nullable()->after('history');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('indication', 160)->nullable()->after('generic_name');
            $table->string('timing', 40)->nullable()->after('duration');
            $table->string('instructions', 255)->nullable()->after('timing');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn(['indication', 'timing', 'instructions']);
        });

        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn(['chief_complaint', 'history', 'on_examination']);
        });
    }
};
