<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamber_cash_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('direction');
            $table->unsignedInteger('amount');
            $table->string('category');
            $table->string('method')->nullable();
            $table->uuid('booking_id')->nullable();
            $table->unsignedBigInteger('chamber_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreign('chamber_id')->references('id')->on('chambers')->nullOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'occurred_on', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamber_cash_entries');
    }
};
