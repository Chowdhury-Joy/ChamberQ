<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('patient_booking_horizon_days')->default(60);
            $table->timestamps();
        });

        DB::table('platform_settings')->insert([
            'id' => 1,
            'patient_booking_horizon_days' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
