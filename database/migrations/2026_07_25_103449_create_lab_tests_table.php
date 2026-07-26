<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();

            // Clinically important and shown prominently on the ticket page:
            // e.g. "12 hours fasting". Never machine-translated.
            $table->text('preparation_instructions')->nullable();

            $table->string('sample_type')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('turnaround_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
