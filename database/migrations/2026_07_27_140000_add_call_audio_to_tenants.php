<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('call_audio_preset')->default('chime')->after('first_n_arrival_offset_minutes');
            $table->string('call_audio_path')->nullable()->after('call_audio_preset');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['call_audio_preset', 'call_audio_path']);
        });
    }
};
