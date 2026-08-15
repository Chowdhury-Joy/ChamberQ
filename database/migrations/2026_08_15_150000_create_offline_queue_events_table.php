<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_queue_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->timestamp('applied_at');
            $table->index(['tenant_id', 'live_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_queue_events');
    }
};
