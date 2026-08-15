<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the phone lookups that run *across* tenants.
 *
 * The existing keys are all tenant-first — `patients (tenant_id, phone)`,
 * `bookings (tenant_id, booking_date, status)` — which is right for chamber
 * screens but useless to the platform-wide queries, because `tenant_id` is the
 * leftmost column and those queries do not supply it:
 *
 * - `PlatformPatientHistoryService` builds the whole `/me` locker from
 *   `bookings.patient_phone` and `patients.phone` with `withoutGlobalScopes()`.
 * - `PatientOtpService::guessName()` reads both on every OTP login.
 * - `CrossTenantClinicalHistoryService::findMatchingPatients()` reads
 *   `patients.phone` behind the Consult Screen poll.
 *
 * Every one of those was a full scan of a table that grows with the platform,
 * not with the chamber — `guessName()` added a filesort on top. Phone is listed
 * first so the same index serves the tenant-scoped patient portal
 * (`tenant_id = ? AND patient_phone IN (…)`) as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['patient_phone', 'tenant_id'], 'bookings_phone_tenant_index');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->index(['phone', 'tenant_id'], 'patients_phone_tenant_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_phone_tenant_index');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_phone_tenant_index');
        });
    }
};
