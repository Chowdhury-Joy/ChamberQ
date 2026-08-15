<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid('booking_id');
            $table->char('endpoint_hash', 64);
            $table->text('endpoint');
            $table->string('p256dh');
            $table->string('auth_token');
            $table->string('last_stage')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'booking_id'])
                ->references(['tenant_id', 'id'])
                ->on('bookings')
                ->cascadeOnDelete();
            // Hash, not the URL: MySQL cannot unique-index a TEXT column, and
            // FCM endpoints are longer than a safe utf8mb4 varchar unique key.
            $table->unique(['tenant_id', 'endpoint_hash'], 'booking_push_endpoint_unique');
            $table->index(['tenant_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_push_subscriptions');
    }
};
