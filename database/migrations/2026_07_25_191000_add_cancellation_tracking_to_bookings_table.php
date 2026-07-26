<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // Set when a booking was cancelled because staff blocked the date.
            // Lets the admin pull an exact "who do I still need to call" list
            // instead of re-deriving it from dates and chambers afterwards.
            $table->unsignedBigInteger('slot_block_id')->nullable();
            $table->boolean('patient_notified')->default(false);

            $table->foreign(['tenant_id', 'slot_block_id'])
                ->references(['tenant_id', 'id'])->on('slot_blocks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'slot_block_id']);
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'slot_block_id', 'patient_notified']);
        });
    }
};
