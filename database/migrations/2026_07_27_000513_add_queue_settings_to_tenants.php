<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('call_timeout_seconds')->default(10);
            $table->unsignedInteger('estimated_time_buffer_minutes')->default(30);
            $table->unsignedInteger('first_n_patients')->default(2);
            $table->unsignedInteger('first_n_arrival_offset_minutes')->default(15);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'call_timeout_seconds',
                'estimated_time_buffer_minutes',
                'first_n_patients',
                'first_n_arrival_offset_minutes',
            ]);
        });
    }
};
