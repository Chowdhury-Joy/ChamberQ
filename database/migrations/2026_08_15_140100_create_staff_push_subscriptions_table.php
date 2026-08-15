<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint_hash', 64);
            $table->string('endpoint', 512);
            $table->string('p256dh');
            $table->string('auth_token');
            $table->string('last_buzz_key')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'endpoint_hash']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_push_subscriptions');
    }
};
