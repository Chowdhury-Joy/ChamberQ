<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short SMS/WhatsApp ticket path /t/{token}, same idea as prescription /p/{token}.
 * UUID /bookings/{id} stays as the durable backup. Token is minted on first share.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('ticket_token', 32)->nullable();
            $table->timestamp('ticket_token_expires_at')->nullable();
            $table->unique('ticket_token', 'bookings_ticket_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_ticket_token_unique');
            $table->dropColumn(['ticket_token', 'ticket_token_expires_at']);
        });
    }
};
