<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the prescription share link's two security properties — unguessability
 * and expiry — out of the URL and into the row.
 *
 * A temporary signed URL carries its own expiry and signature as query string,
 * which ran ~181 characters: longer than a whole GSM segment, so the
 * prescription SMS cost two credits while clinics are sold "1 credit = 1
 * message". A stored random token gives the same guarantees in ~10 characters,
 * and gains one the signed URL never had: the link can be revoked by clearing
 * the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // Nullable: existing rows have no token, and one is only minted the
            // first time a prescription is actually shared.
            $table->string('share_token', 32)->nullable()->unique();
            $table->timestamp('share_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'share_token_expires_at']);
        });
    }
};
