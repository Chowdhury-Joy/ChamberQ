<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('phone');
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->date('age_recorded_at')->nullable();
            $table->string('sex', 16)->nullable();
            $table->text('allergies')->nullable();
            $table->text('conditions')->nullable();
            $table->text('medicines')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignUuid('patient_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('patients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
        });

        Schema::dropIfExists('patients');
    }
};
