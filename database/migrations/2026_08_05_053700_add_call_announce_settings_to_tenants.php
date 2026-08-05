<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('call_announce_mode')->default('chime_and_voice')->after('call_audio_path');
            $table->string('call_announce_locale', 5)->default('en')->after('call_announce_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['call_announce_mode', 'call_announce_locale']);
        });
    }
};
