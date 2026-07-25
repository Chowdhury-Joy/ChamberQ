<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid('booking_id');
            $table->string('gateway');            // bkash / nagad / sslcommerz
            $table->string('transaction_id');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status')->default('pending'); // pending / verified / failed
            $table->json('payload')->nullable();
            // Set ONLY by the server-side webhook handler after signature or
            // server-to-server verification succeeds. Never by a frontend callback.
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'booking_id'])
                ->references(['tenant_id', 'id'])->on('bookings')->cascadeOnDelete();

            // Idempotency backstop: gateways retry as normal behaviour, so a
            // duplicate delivery must collide here rather than double-credit.
            $table->unique(['gateway', 'transaction_id']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
