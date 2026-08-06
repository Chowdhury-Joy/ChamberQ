<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignUuid('condition_id')->nullable()->constrained('conditions')->nullOnDelete();
            $table->string('diagnosis_uncoded')->nullable();
            $table->text('advice')->nullable();
            $table->text('tests_advised')->nullable();
            $table->text('reports_seen')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('booking_id');
            $table->index(['tenant_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_records');
    }
};
