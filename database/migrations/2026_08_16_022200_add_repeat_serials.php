<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->boolean('allows_repeat_serials')->default(false)->after('staff_may_enter_prescriptions');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('repeat_series_id')->nullable()->after('patient_id');
            $table->index(['tenant_id', 'repeat_series_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'repeat_series_id']);
            $table->dropColumn('repeat_series_id');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('allows_repeat_serials');
        });
    }
};
