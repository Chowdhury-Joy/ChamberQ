<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('chamber_id');
            $table->unsignedBigInteger('doctor_id');
            $table->tinyInteger('day_of_week'); // 0 (Sunday) to 6 (Saturday)
            $table->string('session_name'); // e.g. morning/evening
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_cap');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Since we need cross-table foreign keys to be composite as per the prompt:
            $table->foreign(['tenant_id', 'chamber_id'])->references(['tenant_id', 'id'])->on('chambers')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'doctor_id'])->references(['tenant_id', 'id'])->on('doctors')->cascadeOnDelete();
            
            $table->unique(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sessions');
    }
};
