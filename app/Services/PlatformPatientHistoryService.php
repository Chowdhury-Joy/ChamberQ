<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Prescription;
use App\Models\ScheduleSession;
use App\Support\BdNid;
use App\Support\BdPhone;
use App\Support\TenancyUrl;
use Illuminate\Support\Collection;

class PlatformPatientHistoryService
{
    /** @var list<string> */
    public const OPEN_STATUSES = ['waiting', 'called', 'skipped', 'in_chamber'];

    /**
     * Upcoming serials for this phone (household names included).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function serialsFor(PatientAccount $account): Collection
    {
        return $this->bookingsForAccount($account)
            ->whereIn('status', self::OPEN_STATUSES)
            ->sortBy([
                ['booking_date', 'asc'],
                ['serial_number', 'asc'],
            ])
            ->values()
            ->map(fn (Booking $booking) => $this->presentBooking($booking));
    }

    /**
     * Completed visits for this phone. Share-flag is ignored — these are hers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function historyFor(PatientAccount $account): Collection
    {
        $bookings = $this->bookingsForAccount($account)
            ->where('status', 'completed')
            ->sortByDesc(fn (Booking $booking) => $booking->booking_date?->toDateString().$booking->completed_at)
            ->values();

        return $bookings->map(function (Booking $booking) {
            $row = $this->presentBooking($booking);
            $visit = $booking->visitRecord;
            $prescription = $visit?->prescription;
            $hasItems = $prescription && $prescription->items->isNotEmpty();

            $row['diagnosis'] = $visit?->condition?->name ?: $visit?->diagnosis_uncoded;
            $row['prescription_id'] = $hasItems ? $prescription->id : null;
            $row['medicines'] = $hasItems
                ? $prescription->items->pluck('medicine_name')->filter()->values()->all()
                : [];

            return $row;
        });
    }

    public function prescriptionForAccount(PatientAccount $account, string $prescriptionId): Prescription
    {
        $prescription = Prescription::withoutGlobalScopes()
            ->with([
                'items' => fn ($q) => $q->withoutGlobalScopes(),
                'patient' => fn ($q) => $q->withoutGlobalScopes(),
                'visitRecord' => function ($q) {
                    $q->withoutGlobalScopes()->with([
                        'booking' => fn ($b) => $b->withoutGlobalScopes(),
                        'condition' => fn ($c) => $c->withoutGlobalScopes(),
                    ]);
                },
            ])
            ->findOrFail($prescriptionId);

        if ($prescription->items->isEmpty()) {
            abort(404);
        }

        $this->assertOwnsPrescription($account, $prescription);

        return $prescription;
    }

    private function assertOwnsPrescription(PatientAccount $account, Prescription $prescription): void
    {
        $booking = $prescription->visitRecord?->booking;
        $patient = $prescription->patient;

        $accountPhone = $account->normalizedPhone();
        $bookingPhone = BdPhone::normalize((string) ($booking?->patient_phone ?? ''));
        $patientPhone = BdPhone::normalize((string) ($patient?->phone ?? ''));

        if ($accountPhone !== '' && ($bookingPhone === $accountPhone || $patientPhone === $accountPhone)) {
            return;
        }

        // NID is accepted as proof of ownership, so it must only ever be one a
        // chamber recorded from the card in front of them — never one this
        // account typed about itself. `PatientAccount::$fillable` deliberately
        // omits it; keep it that way.
        $accountNid = BdNid::normalize($account->nid);
        $patientNid = BdNid::normalize($patient?->nid);
        if ($accountNid !== null && $patientNid !== null && $accountNid === $patientNid) {
            return;
        }

        abort(404);
    }

    /**
     * @return Collection<int, Booking>
     */
    private function bookingsForAccount(PatientAccount $account): Collection
    {
        $phones = $this->phoneVariants($account->normalizedPhone());
        $patientIds = $this->matchingPatientIds($account, $phones);

        // Fail closed. Every query below runs `withoutGlobalScopes()`, so the
        // tenant scope is not there to catch a mistake — and Laravel *drops* a
        // nested `where()` whose closure added no constraints (see
        // Builder::addNestedWhereQuery). With no phone and no patient id the
        // filter below therefore compiled to `select * from bookings`: every
        // serial, name and ticket URL of every chamber on the platform, handed
        // to whoever was signed in. An account with nothing to match on has
        // nothing to show.
        if ($phones === [] && $patientIds === []) {
            return collect();
        }

        $query = Booking::withoutGlobalScopes()
            ->with([
                'bookable' => fn ($q) => $q->withoutGlobalScopes(),
                'visitRecord' => function ($q) {
                    $q->withoutGlobalScopes()->with([
                        'condition' => fn ($c) => $c->withoutGlobalScopes(),
                        'prescription' => function ($p) {
                            $p->withoutGlobalScopes()->with([
                                'items' => fn ($i) => $i->withoutGlobalScopes(),
                            ]);
                        },
                    ]);
                },
            ]);

        $query->where(function ($q) use ($phones, $patientIds) {
            if ($phones !== []) {
                $q->whereIn('patient_phone', $phones);
            }
            if ($patientIds !== []) {
                $q->orWhereIn('patient_id', $patientIds);
            }
        });

        return $query->get();
    }

    /**
     * @param  list<string>  $phones
     * @return list<string>
     */
    private function matchingPatientIds(PatientAccount $account, array $phones): array
    {
        $ids = [];

        if ($phones !== []) {
            $ids = Patient::withoutGlobalScopes()
                ->whereIn('phone', $phones)
                ->pluck('id')
                ->all();
        }

        $nid = BdNid::normalize($account->nid);
        if ($nid !== null) {
            $nidIds = Patient::withoutGlobalScopes()
                ->where('nid', $nid)
                ->pluck('id')
                ->all();
            $ids = array_values(array_unique(array_merge($ids, $nidIds)));
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $phone): array
    {
        if ($phone === '' || ! BdPhone::isValid($phone)) {
            return [];
        }

        return array_values(array_unique(array_filter([
            $phone,
            ...BdPhone::lookupVariants($phone),
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBooking(Booking $booking): array
    {
        $bookable = $booking->bookable;
        $doctorName = null;
        $sessionName = null;

        if ($bookable instanceof ScheduleSession) {
            $bookable->loadMissing(['doctor' => fn ($q) => $q->withoutGlobalScopes()]);
            $doctorName = $bookable->doctor?->name;
            $sessionName = $bookable->session_name;
        }

        return [
            'id' => $booking->id,
            'tenant_id' => (string) $booking->tenant_id,
            'patient_name' => (string) $booking->patient_name,
            'serial_number' => $booking->serial_number,
            'status' => (string) $booking->status,
            'booking_date' => $booking->booking_date?->format('j M Y'),
            'doctor_name' => $doctorName,
            'session_name' => $sessionName,
            'ticket_url' => TenancyUrl::publicAbsolute(
                (string) $booking->tenant_id,
                '/bookings/'.$booking->id,
            ),
        ];
    }
}
