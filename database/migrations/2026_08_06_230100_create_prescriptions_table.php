<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('visit_record_id')->constrained('visit_records')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('prescribed_by')->constrained('users');
            $table->text('advice')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('visit_record_id');
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->string('medicine_name');
            $table->string('generic_name')->nullable();
            $table->string('dose')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
