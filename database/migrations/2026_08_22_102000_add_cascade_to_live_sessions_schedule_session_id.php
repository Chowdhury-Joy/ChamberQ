<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropForeign(['schedule_session_id']);
            $table->foreign('schedule_session_id')
                ->references('id')
                ->on('schedule_sessions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropForeign(['schedule_session_id']);
            $table->foreign('schedule_session_id')
                ->references('id')
                ->on('schedule_sessions');
        });
    }
};
