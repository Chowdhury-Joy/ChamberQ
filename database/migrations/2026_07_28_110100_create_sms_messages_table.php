<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('to', 20);
            $table->text('body');
            $table->string('purpose')->default('booking_confirmation');
            $table->string('status'); // sent, failed, skipped_no_balance, skipped_disabled
            $table->unsignedTinyInteger('credits')->default(1);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
