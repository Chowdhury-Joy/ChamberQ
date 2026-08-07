<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // Off by default: delegating prescription entry is the individual
            // doctor's call, never a setting a practice inherits.
            $table->boolean('staff_may_enter_prescriptions')
                ->default(false)
                ->after('practice_type');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('staff_may_enter_prescriptions');
        });
    }
};
