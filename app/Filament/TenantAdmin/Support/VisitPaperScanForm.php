<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Services\VisitRecordService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Desk scan of paper the patient brought (reports, handwritten Rx).
 *
 * Staff do this when the practice has a staff login. Scanner is the default;
 * taking a photo stays available when there is no scanner.
 */
class VisitPaperScanForm
{
    public static function scanAction(Action $action): Action
    {
        return $action
            ->label('Scan papers')
            ->icon('heroicon-o-document-plus')
            ->color('gray')
            ->visible(fn (Booking $record): bool => static::mayScan($record))
            ->fillForm(fn (Booking $record): array => [
                'prescription_photo' => filled($record->visitRecord?->photo_path)
                    ? [$record->visitRecord->photo_path]
                    : [],
                'report_photos' => $record->visitRecord?->report_photo_paths ?? [],
                '_prescription_capture' => 'scan',
                '_report_capture' => 'scan',
            ])
            ->modalHeading(__('Scan papers'))
            ->modalDescription(__('Use the desk scanner first. Take a photo only if there is no scanner.'))
            ->modalSubmitActionLabel('Save')
            ->schema(VisitNotesFormSchema::paperScanComponents())
            ->action(function (Booking $record, array $data, VisitRecordService $visitRecordService): void {
                /** @var \App\Models\User $user */
                $user = auth()->user();

                $visitRecordService->saveStaffVisitPaper($record, $user, $data);

                Notification::make()
                    ->title(__('Papers scanned'))
                    ->success()
                    ->send();
            });
    }

    public static function mayScan(Booking $record): bool
    {
        if (! tenant()?->hasPrescription()) {
            return false;
        }

        if (! auth()->user()?->attachesVisitPaperAtDesk()) {
            return false;
        }

        return ! in_array($record->status, ['cancelled', 'no_show'], true);
    }
}
