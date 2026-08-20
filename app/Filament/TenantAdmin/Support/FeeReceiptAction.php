<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\User;
use App\Support\StaffDeskJobs;
use Filament\Actions\Action;

final class FeeReceiptAction
{
    public static function make(Action $action): Action
    {
        return $action
            ->label(__('Print receipt'))
            ->icon('heroicon-o-printer')
            ->url(function (Booking $record): ?string {
                $entry = $record->cashEntry;
                if (! $entry instanceof ChamberCashEntry) {
                    return null;
                }

                return tenant_web_route('fee-receipts.show', ['entry' => $entry], absolute: false);
            })
            ->openUrlInNewTab()
            ->visible(fn (Booking $record): bool => self::canPrint($record));
    }

    public static function forCashEntry(Action $action): Action
    {
        return $action
            ->label(__('Print receipt'))
            ->icon('heroicon-o-printer')
            ->url(fn (ChamberCashEntry $record): string => tenant_web_route(
                'fee-receipts.show',
                ['entry' => $record],
                absolute: false,
            ))
            ->openUrlInNewTab()
            ->visible(function (ChamberCashEntry $record): bool {
                $user = auth()->user();

                return $user instanceof User
                    && StaffDeskJobs::canCollectFee($user)
                    && $record->isPatientFeeReceipt();
            });
    }

    public static function canPrint(Booking $record): bool
    {
        $user = auth()->user();

        $entry = $record->cashEntry;

        return $user instanceof User
            && StaffDeskJobs::canCollectFee($user)
            && $entry instanceof ChamberCashEntry
            && $entry->isPatientFeeReceipt();
    }

    public static function printUrl(ChamberCashEntry $entry): string
    {
        return tenant_web_route('fee-receipts.show', ['entry' => $entry], absolute: false);
    }
}
