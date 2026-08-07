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

            // Single-column FK on purpose. This was a composite
            // (tenant_id, slot_block_id) → slot_blocks(tenant_id, id) with
            // nullOnDelete, which MySQL rejects outright ("1830 Column
            // 'tenant_id' cannot be NOT NULL: needed in a foreign key
            // constraint SET NULL") — and rightly so: SET NULL applies to every
            // column in the key, so deleting a slot block would have nulled the
            // booking's own tenant_id and severed it from its practice. Only
            // slot_block_id should be forgotten. Cross-tenant references are not
            // a risk here because slot_blocks.id is a globally unique
            // auto-increment PK and SlotBlockService only ever writes ids it
            // resolved inside the tenant scope.
            $table->foreign('slot_block_id')
                ->references('id')->on('slot_blocks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['slot_block_id']);
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'slot_block_id', 'patient_notified']);
        });
    }
};
