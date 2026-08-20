<?php

namespace App\Services;

use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\Tenant;
use App\Support\BdPhone;
use App\Support\SafeUrl;
use App\Support\TakaWords;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PharmacyInvoiceService
{
    public const MIN_ROWS = 8;

    public function assignIfNeeded(PharmacySale $sale): void
    {
        if ($sale->receipt_number !== null) {
            return;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                DB::transaction(function () use ($sale): void {
                    $locked = PharmacySale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

                    if ($locked->receipt_number !== null) {
                        $sale->receipt_number = $locked->receipt_number;

                        return;
                    }

                    PharmacySale::query()
                        ->whereNotNull('receipt_number')
                        ->lockForUpdate()
                        ->get(['id']);

                    $max = PharmacySale::query()
                        ->whereNotNull('receipt_number')
                        ->max('receipt_number');

                    $locked->receipt_number = ((int) $max) + 1;
                    $locked->save();
                    $sale->receipt_number = $locked->receipt_number;
                });

                return;
            } catch (UniqueConstraintViolationException) {
                $sale->refresh();
                if ($sale->receipt_number !== null) {
                    return;
                }
            }
        }

        throw new \RuntimeException('Could not assign a medicine voucher number. Try again.');
    }

    /**
     * @return array{
     *     sale: PharmacySale,
     *     tenant: ?Tenant,
     *     chamber: ?Chamber,
     *     clinicName: string,
     *     logoUrl: ?string,
     *     thankYouBrand: string,
     *     address: ?string,
     *     phones: list<string>,
     *     ink: string,
     *     lines: list<PharmacySaleItem|null>,
     *     catalogueTotal: int,
     *     discount: int,
     *     netPayable: int,
     *     amountInWords: string,
     *     receivedBy: ?string
     * }
     */
    public function viewData(PharmacySale $sale): array
    {
        $this->assignIfNeeded($sale);
        $sale->refresh();
        $sale->load(['items.item.chamber', 'recordedBy']);

        $tenant = tenant();
        $chamber = $sale->items->first()?->item?->chamber;
        $catalogueTotal = (int) $sale->items->sum('line_total_taka');
        $netPayable = $sale->waived ? 0 : (int) $sale->amount;
        $discount = max(0, $catalogueTotal - $netPayable);

        $lines = $sale->items->values()->all();
        while (count($lines) < self::MIN_ROWS) {
            $lines[] = null;
        }

        return [
            'sale' => $sale,
            'tenant' => $tenant,
            'chamber' => $chamber,
            'clinicName' => $tenant?->displayName() ?? config('app.name'),
            'logoUrl' => $this->logoUrl($tenant),
            'thankYouBrand' => $this->thankYouBrand($tenant),
            'address' => filled($chamber?->address) ? (string) $chamber->address : null,
            'phones' => $this->phones($tenant, $chamber),
            'ink' => $this->ink($tenant),
            'lines' => $lines,
            'catalogueTotal' => $catalogueTotal,
            'discount' => $discount,
            'netPayable' => $netPayable,
            'amountInWords' => TakaWords::english($netPayable),
            'receivedBy' => $sale->recordedBy?->name,
        ];
    }

    public static function formatTaka(int $taka): string
    {
        return number_format($taka).'/-';
    }

    private function logoUrl(?Tenant $tenant): ?string
    {
        $href = SafeUrl::href($tenant?->logo_url, '');

        return $href !== '' ? $href : null;
    }

    private function thankYouBrand(?Tenant $tenant): string
    {
        $name = trim((string) ($tenant?->displayName() ?? ''));
        if ($name === '') {
            return (string) config('app.name');
        }

        if (preg_match('/^(.+?)\s+[—–-]\s+/u', $name, $match) === 1 && mb_strlen(trim($match[1])) <= 24) {
            return trim($match[1]);
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private function phones(?Tenant $tenant, ?Chamber $chamber): array
    {
        $raw = [
            $chamber?->contact,
            $tenant?->contact_phone,
            $tenant?->whatsapp_number,
        ];

        $seen = [];
        $out = [];
        foreach ($raw as $value) {
            if (! filled($value)) {
                continue;
            }
            $display = $this->displayPhone((string) $value);
            $key = BdPhone::normalize((string) $value);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $display;
            if (count($out) >= 3) {
                break;
            }
        }

        return $out;
    }

    private function displayPhone(string $raw): string
    {
        $digits = BdPhone::normalize($raw);
        if (preg_match('/^(01\d{3})(\d{6})$/', $digits, $match) === 1) {
            return $match[1].'-'.$match[2];
        }

        return trim($raw);
    }

    private function ink(?Tenant $tenant): string
    {
        return $tenant?->cssThemeColor() ?? '#123a7a';
    }

    /**
     * @return list<string>
     */
    public static function tickedMethods(PharmacySale $sale): array
    {
        if ($sale->waived) {
            return [];
        }

        if ($sale->method === ChamberCashEntry::METHOD_MIXED) {
            $keys = [];
            if ((int) $sale->cash_taka > 0) {
                $keys[] = ChamberCashEntry::METHOD_CASH;
            }
            if (filled($sale->mobile_method)) {
                $keys[] = (string) $sale->mobile_method;
            }

            return $keys;
        }

        return [(string) $sale->method];
    }
}
