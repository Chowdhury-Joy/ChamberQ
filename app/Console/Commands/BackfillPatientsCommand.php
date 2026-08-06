<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\PatientService;
use App\Support\BdPhone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPatientsCommand extends Command
{
    protected $signature = 'patients:backfill {--dry-run : Preview changes without writing}';

    protected $description = 'Match existing bookings to patient records by phone and name';

    public function handle(PatientService $patientService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run — no database writes.');
        }

        $peopleCreated = 0;
        $bookingsLinked = 0;
        $bookingsUnmatched = 0;
        $suspicious = [];

        $tenants = Tenant::query()->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $bookings = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('patient_id')
                ->orderBy('created_at')
                ->get();

            /** @var array<string, array<string, Patient>> $patientCache phone => normalized name => patient */
            $patientCache = [];

            foreach ($bookings as $booking) {
                $phone = BdPhone::normalize($booking->patient_phone);

                if ($phone === '' || ! BdPhone::isValid($phone)) {
                    $bookingsUnmatched++;
                    $this->warn("Unmatched booking {$booking->id}: invalid phone \"{$booking->patient_phone}\"");

                    continue;
                }

                $name = trim($booking->patient_name);
                $normalizedName = $patientService->normalizeName($name);

                if ($normalizedName === '') {
                    $bookingsUnmatched++;
                    $this->warn("Unmatched booking {$booking->id}: empty patient name");

                    continue;
                }

                $patientCache[$phone] ??= [];

                if (! isset($patientCache[$phone][$normalizedName])) {
                    $existing = Patient::query()
                        ->where('phone', $phone)
                        ->get()
                        ->first(fn (Patient $patient) => $patientService->normalizeName($patient->name) === $normalizedName);

                    if ($existing) {
                        $patientCache[$phone][$normalizedName] = $existing;
                    } elseif ($dryRun) {
                        $peopleCreated++;
                        $patientCache[$phone][$normalizedName] = new Patient([
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                    } else {
                        $patient = Patient::create([
                            'name' => $name,
                            'phone' => $phone,
                        ]);
                        $peopleCreated++;
                        $patientCache[$phone][$normalizedName] = $patient;
                    }
                }

                $patient = $patientCache[$phone][$normalizedName];

                if (! $dryRun && $patient->exists) {
                    $booking->update(['patient_id' => $patient->id]);
                }

                $bookingsLinked++;
            }

            $namesByPhone = [];
            foreach ($patientCache as $phone => $byName) {
                $namesByPhone[$phone] = array_map(
                    fn (Patient $patient) => $patient->name,
                    array_values($byName)
                );
            }

            foreach ($patientService->findSuspiciousNamePairs(collect($namesByPhone)) as $pair) {
                $suspicious[] = $pair;
            }

            tenancy()->end();
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['People created', $peopleCreated],
                ['Bookings linked', $bookingsLinked],
                ['Bookings unmatched', $bookingsUnmatched],
                ['Suspicious name pairs', count($suspicious)],
            ]
        );

        if ($suspicious !== []) {
            $this->newLine();
            $this->warn('Suspicious groupings (similar names on one phone — review for possible duplicates):');
            $this->table(
                ['Phone', 'Name A', 'Name B', 'Similarity %'],
                array_map(fn (array $row) => [
                    $row['phone'],
                    $row['name_a'],
                    $row['name_b'],
                    $row['similarity'],
                ], $suspicious)
            );
        }

        return self::SUCCESS;
    }
}
