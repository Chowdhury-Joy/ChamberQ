<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // A doctor's login and their prescribing profile were unlinked, so
            // anything asking "which doctor is this?" outside a booking had to
            // guess. Solo could guess (one doctor); a clinic could not.
            // No FK constraint: SQLite cannot add one to an existing table, and
            // User::deleting clears the link instead.
            $table->unsignedBigInteger('user_id')->nullable()->after('name');
            $table->unique(['tenant_id', 'user_id']);
        });

        $this->backfillUnambiguousLinks();
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->dropColumn('user_id');
        });
    }

    /**
     * Link only where there is exactly one doctor profile and exactly one
     * doctor login in the tenant — that pairing is certain. Clinics with
     * several of either are left blank for an admin to set by hand, because
     * guessing would put one doctor's name on another's prescriptions.
     */
    private function backfillUnambiguousLinks(): void
    {
        $tenantIds = DB::table('doctors')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $doctorIds = DB::table('doctors')->where('tenant_id', $tenantId)->pluck('id');

            if ($doctorIds->count() !== 1) {
                continue;
            }

            $userIds = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('role', 'doctor')
                ->pluck('id');

            if ($userIds->count() !== 1) {
                continue;
            }

            DB::table('doctors')
                ->where('id', $doctorIds->first())
                ->update(['user_id' => $userIds->first()]);
        }
    }
};
