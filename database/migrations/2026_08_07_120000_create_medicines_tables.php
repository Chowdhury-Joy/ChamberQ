<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('brand_name');
            $table->string('generic_name')->nullable();
            $table->string('default_strength', 80)->nullable();
            $table->string('form', 32)->nullable();
            $table->json('aliases');
            $table->string('category', 64)->nullable();
            $table->timestamps();

            $table->index('brand_name');
        });

        Schema::create('medicine_usages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('medicine_name', 120);
            $table->string('generic_name', 120)->nullable();
            $table->string('last_dose', 80)->nullable();
            $table->string('last_frequency', 80)->nullable();
            $table->string('last_duration', 80)->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id', 'medicine_name']);
            $table->index(['tenant_id', 'user_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_usages');
        Schema::dropIfExists('medicines');
    }
};
