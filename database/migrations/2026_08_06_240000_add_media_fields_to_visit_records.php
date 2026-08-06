<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->string('voice_path')->nullable()->after('follow_up_date');
            $table->string('photo_path')->nullable()->after('voice_path');
            $table->text('voice_transcript')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn(['voice_path', 'photo_path', 'voice_transcript']);
        });
    }
};
