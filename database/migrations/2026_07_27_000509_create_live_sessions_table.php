<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('schedule_session_id');
            $table->date('session_date');

            $table->string('status')->default('scheduled');
            $table->unsignedInteger('delay_minutes')->default(0);

            $table->uuid('current_booking_id')->nullable();
            $table->timestamp('current_called_at')->nullable();

            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason')->nullable();
            $table->unsignedInteger('estimated_pause_minutes')->default(0);
            $table->unsignedInteger('total_pause_minutes')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('schedule_session_id')->references('id')->on('schedule_sessions')->cascadeOnDelete();
            $table->foreign('current_booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->unique(['tenant_id', 'schedule_session_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
