<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('chamber_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'chamber_id'])->references(['tenant_id', 'id'])->on('chambers')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'doctor_id'])->references(['tenant_id', 'id'])->on('doctors')->cascadeOnDelete();
            
            $table->unique(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_blocks');
    }
};
