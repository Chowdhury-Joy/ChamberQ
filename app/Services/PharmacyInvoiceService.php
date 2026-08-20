<?php

namespace App\Services;

use App\Models\Chamber;
use App\Models\ChamberCashEntry;
use App\Models\Doctor;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\Tenant;
use App\Support\SafeUrl;
use App\Support\TakaWords;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PharmacyInvoiceService
{
    public const VOUCHER_ONLINE = 'online';

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
     *     doctor: ?Doctor,
     *     patient: mixed,
     *     lines: list<PharmacySaleItem>,
     *     catalogueTotal: int,
     *     discount: int,
     *     netPayable: int,
     *     amountInWords: string,
     *     receivedBy: ?string,
     *     logoUrl: ?string
     * }
     */
    public function viewData(PharmacySale $sale): array
    {
        $this->assignIfNeeded($sale);
        $sale->refresh();
        $sale->load([
            'items.item.chamber',
            'recordedBy',
            'patient',
            'booking.bookable',
            'cashEntry.doctor',
            'prescription.prescribedBy',
            'prescription.visitRecord.booking.bookable',
        ]);

        $tenant = tenant();
        $chamber = $sale->items->first()?->item?->chamber;
        $catalogueTotal = (int) $sale->items->sum('line_total_taka');
        $netPayable = $sale->waived ? 0 : (int) $sale->amount;
        $discount = $sale->waived ? 0 : max(0, $catalogueTotal - $netPayable);

        return [
            'sale' => $sale,
            'tenant' => $tenant,
            'chamber' => $chamber,
            'doctor' => $this->doctorFor($sale),
            'patient' => $sale->patient,
            'lines' => $sale->items->values()->all(),
            'catalogueTotal' => $catalogueTotal,
            'discount' => $discount,
            'netPayable' => $netPayable,
            'amountInWords' => TakaWords::english($netPayable),
            'receivedBy' => $sale->recordedBy?->name,
            'logoUrl' => $this->logoUrl($tenant),
        ];
    }

    private function logoUrl(?Tenant $tenant): ?string
    {
        $href = SafeUrl::href($tenant?->logo_url, '');

        return $href !== '' ? $href : null;
    }

    private function doctorFor(PharmacySale $sale): ?Doctor
    {
        if ($sale->cashEntry?->doctor) {
            return $sale->cashEntry->doctor;
        }

        $prescriber = $sale->prescription?->prescribedBy;
        if ($prescriber instanceof \App\Models\User) {
            $fromUser = Doctor::query()->where('user_id', $prescriber->id)->first();
            if ($fromUser) {
                return $fromUser;
            }
        }

        $booking = $sale->booking ?? $sale->prescription?->visitRecord?->booking;
        if ($booking) {
            return Doctor::resolveForBooking($booking);
        }

        return null;
    }

    public static function formatTaka(int $taka): string
    {
        return number_format($taka).'/-';
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

    /**
     * Voucher ticks: Cash and/or Online. The till still records bKash / Nagad / card.
     *
     * @return list<string>
     */
    public static function tickedVoucherPayments(PharmacySale $sale): array
    {
        $raw = self::tickedMethods($sale);
        $out = [];
        if (in_array(ChamberCashEntry::METHOD_CASH, $raw, true)) {
            $out[] = ChamberCashEntry::METHOD_CASH;
        }

        $online = [
            ChamberCashEntry::METHOD_BKASH,
            ChamberCashEntry::METHOD_NAGAD,
            ChamberCashEntry::METHOD_CARD,
            ChamberCashEntry::METHOD_BANK,
            ChamberCashEntry::METHOD_BANGLA_QR,
            ChamberCashEntry::METHOD_OTHER,
        ];
        foreach ($raw as $method) {
            if (in_array($method, $online, true)) {
                $out[] = self::VOUCHER_ONLINE;
                break;
            }
        }

        return $out;
    }
}
