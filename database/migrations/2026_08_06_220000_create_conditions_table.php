<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->json('aliases');
            $table->string('category', 64)->nullable();
            $table->string('icd10_unverified', 16)->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('condition_usages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('condition_id')->constrained('conditions')->cascadeOnDelete();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id', 'condition_id']);
            $table->index(['tenant_id', 'user_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condition_usages');
        Schema::dropIfExists('conditions');
    }
};
