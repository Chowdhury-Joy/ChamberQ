<?php

namespace App\Services;

use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\ScheduleSession;
use App\Models\Tenant;
use App\Support\SafeUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DoctorDirectoryService
{
    /**
     * Public shop-window cards for every ChamberQ doctor whose clinic takes
     * online serials (Front door + billing open).
     *
     * @return Collection<int, array{
     *     id: int,
     *     tenant_id: string,
     *     name: string,
     *     specialty: string,
     *     practice_type: string,
     *     qualifications: ?string,
     *     photo_url: ?string,
     *     fee_label: ?string,
     *     chambers: list<array{name: string, address: ?string}>,
     *     book_url: string
     * }>
     */
    public function listings(?string $query = null, ?string $practiceType = null): Collection
    {
        $tenantIds = Tenant::query()
            ->get()
            ->filter(fn (Tenant $tenant) => $tenant->acceptsBookings())
            ->pluck('id')
            ->all();

        if ($tenantIds === []) {
            return collect();
        }

        $tenants = Tenant::query()->whereIn('id', $tenantIds)->get()->keyBy('id');

        $doctors = Doctor::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->orderBy('name')
            ->get();

        if ($doctors->isEmpty()) {
            return collect();
        }

        $sessions = ScheduleSession::withoutGlobalScopes()
            ->whereIn('doctor_id', $doctors->pluck('id')->all())
            ->get();

        $chambers = Chamber::withoutGlobalScopes()
            ->whereIn('id', $sessions->pluck('chamber_id')->unique()->filter()->all())
            ->get()
            ->keyBy('id');

        $chambersByDoctor = [];
        foreach ($sessions as $session) {
            $chamber = $chambers->get($session->chamber_id);
            if (! $chamber) {
                continue;
            }
            $chambersByDoctor[$session->doctor_id][$chamber->id] = [
                'name' => (string) $chamber->name,
                'address' => filled($chamber->address) ? (string) $chamber->address : null,
            ];
        }

        $needle = Str::lower(trim((string) $query));
        $practiceType = filled($practiceType) ? (string) $practiceType : null;

        return $doctors
            ->map(function (Doctor $doctor) use ($tenants, $chambersByDoctor) {
                $tenant = $tenants->get($doctor->tenant_id);
                $chamberRows = array_values($chambersByDoctor[$doctor->id] ?? []);
                $photo = filled($doctor->photo_url)
                    ? SafeUrl::href((string) $doctor->photo_url, '')
                    : '';

                $fee = $doctor->default_fee_taka;
                $feeLabel = is_numeric($fee) && (int) $fee > 0
                    ? '৳'.number_format((int) $fee)
                    : null;

                return [
                    'id' => (int) $doctor->id,
                    'tenant_id' => (string) $doctor->tenant_id,
                    'clinic' => $tenant?->displayName() ?? (string) $doctor->tenant_id,
                    'name' => (string) $doctor->name,
                    'specialty' => $doctor->practiceTypeLabel(),
                    'practice_type' => (string) ($doctor->practice_type ?? Doctor::PRACTICE_GENERAL),
                    'qualifications' => filled($doctor->qualifications) ? (string) $doctor->qualifications : null,
                    'photo_url' => $photo !== '' ? $photo : null,
                    'fee_label' => $feeLabel,
                    'chambers' => $chamberRows,
                    'book_url' => '/'.$doctor->tenant_id.'/book?doctor='.$doctor->id,
                ];
            })
            ->filter(function (array $card) use ($needle, $practiceType) {
                if ($practiceType && $card['practice_type'] !== $practiceType) {
                    return false;
                }

                if ($needle === '') {
                    return true;
                }

                $haystack = Str::lower(implode(' ', [
                    $card['name'],
                    $card['specialty'],
                    $card['clinic'],
                    (string) $card['qualifications'],
                    collect($card['chambers'])->pluck('name')->implode(' '),
                    collect($card['chambers'])->pluck('address')->implode(' '),
                ]));

                return str_contains($haystack, $needle);
            })
            ->values();
    }
}
