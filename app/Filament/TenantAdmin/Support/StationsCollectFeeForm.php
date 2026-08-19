<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\ChamberCashEntry;
use App\Models\FeeCatalogItem;
use App\Models\ReferringDoctor;
use App\Models\User;
use App\Services\StationsTillService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;

class StationsCollectFeeForm
{
    /**
     * @return list<Component>
     */
    public static function components(Booking $record): array
    {
        return [
            Placeholder::make('patient_header')
                ->label(__('Patient'))
                ->content(fn (): string => $record->patient_name
                    .' · '.__('Serial :n', ['n' => $record->serial_number])
                    .($record->voucher_number ? ' · '.__('Voucher :v', ['v' => $record->voucher_number]) : '')),
            Select::make('referring_doctor_id')
                ->label(__('Referred by (outside GP)'))
                ->options(fn (): array => ReferringDoctor::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (ReferringDoctor $doctor) => [$doctor->id => $doctor->displayLabel()])
                    ->all())
                ->placeholder(__('Walk-in / no referrer'))
                ->searchable()
                ->native(false)
                ->visible(fn (): bool => tenant()?->hasReferrals() ?? false),
            Select::make('fee_catalog_item_id')
                ->label(__('Procedure / visit'))
                ->options(fn (): array => FeeCatalogItem::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get()
                    ->mapWithKeys(fn (FeeCatalogItem $item) => [$item->id => $item->chipLabel()])
                    ->all())
                ->required()
                ->live()
                ->native(false)
                ->searchable(),
            Placeholder::make('list_price')
                ->label(__('Full price'))
                ->content(function (Get $get): string {
                    $item = self::catalogItem((int) $get('fee_catalog_item_id'));

                    return $item ? '৳'.number_format($item->list_price_taka) : '—';
                }),
            TextInput::make('cash_taka')
                ->label(__('Cash ৳'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->live()
                ->disabled(fn (Get $get): bool => (bool) $get('waived')),
            TextInput::make('mobile_taka')
                ->label(__('Online ৳'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->live()
                ->disabled(fn (Get $get): bool => (bool) $get('waived')),
            Select::make('mobile_method')
                ->label(__('Online method'))
                ->options(ChamberCashEntry::onlineMethods())
                ->native(false)
                ->visible(fn (Get $get): bool => ! (bool) $get('waived') && (int) ($get('mobile_taka') ?? 0) > 0),
            Placeholder::make('discount_tape')
                ->label(__('Discount'))
                ->content(function (Get $get): string {
                    return self::tapeLabel($get);
                }),
            Placeholder::make('split_tape')
                ->label(__('Clinic | Doctor'))
                ->content(function (Get $get): string {
                    return self::splitLabel($get);
                }),
            Checkbox::make('waived')
                ->label(__('Waive all'))
                ->live(),
            TextInput::make('note')
                ->label(__('Note')),
            Hidden::make('validation_error')
                ->dehydrated(false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fillFromEntry(Booking $record): array
    {
        $entry = $record->cashEntry;
        $itemId = $entry?->fee_catalog_item_id;

        if (! $itemId) {
            $record->loadMissing('bookable');
            $kind = $record->bookable instanceof \App\Models\ScheduleSession
                ? $record->bookable->kind
                : null;
            if (filled($kind)) {
                $preferFollowUp = $kind === \App\Models\ScheduleSession::KIND_VISIT
                    && $record->care_path === \App\Services\CarePath::FOLLOW_UP;

                $query = FeeCatalogItem::query()
                    ->where('is_active', true)
                    ->where('sitting_kind', $kind)
                    ->orderBy('sort_order');

                if ($preferFollowUp) {
                    $itemId = (clone $query)->where('label', 'like', '%ollow-up%')->value('id')
                        ?: $query->value('id');
                } else {
                    $itemId = $query->value('id');
                }
            }
        }

        return [
            'referring_doctor_id' => $record->referring_doctor_id,
            'fee_catalog_item_id' => $itemId,
            'cash_taka' => $entry?->cash_taka ?? 0,
            'mobile_taka' => $entry?->mobile_taka ?? 0,
            'mobile_method' => $entry?->mobile_method ?? ChamberCashEntry::METHOD_BKASH,
            'waived' => $entry?->isWaived() ?? false,
            'note' => $entry?->note,
        ];
    }

    public static function save(Booking $record, array $data, User $user): void
    {
        $item = self::catalogItem((int) ($data['fee_catalog_item_id'] ?? 0));

        if (! $item) {
            throw new InvalidArgumentException(__('Pick a fee from the catalogue.'));
        }

        $referringDoctorId = filled($data['referring_doctor_id'] ?? null)
            ? (int) $data['referring_doctor_id']
            : null;

        if ($referringDoctorId !== $record->referring_doctor_id) {
            $record->update(['referring_doctor_id' => $referringDoctorId]);
        }

        app(StationsTillService::class)->recordPatientIncome(
            $record,
            $user,
            $item,
            (int) ($data['cash_taka'] ?? 0),
            (int) ($data['mobile_taka'] ?? 0),
            filled($data['mobile_method'] ?? null) ? (string) $data['mobile_method'] : null,
            filled($data['note'] ?? null) ? (string) $data['note'] : null,
            waived: (bool) ($data['waived'] ?? false),
        );
    }

    private static function catalogItem(int $id): ?FeeCatalogItem
    {
        return $id > 0 ? FeeCatalogItem::find($id) : null;
    }

    private static function tapeLabel(Get $get): string
    {
        $item = self::catalogItem((int) $get('fee_catalog_item_id'));
        if (! $item || (bool) $get('waived')) {
            return '৳0';
        }

        try {
            $split = app(StationsTillService::class)->computeSplit(
                $item->list_price_taka,
                (int) ($get('cash_taka') ?? 0),
                (int) ($get('mobile_taka') ?? 0),
                $item->house_share_taka,
            );
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return __('Discount ৳:d · Collected ৳:c', [
            'd' => number_format($split['discount']),
            'c' => number_format($split['collected']),
        ]);
    }

    private static function splitLabel(Get $get): string
    {
        $item = self::catalogItem((int) $get('fee_catalog_item_id'));
        if (! $item || (bool) $get('waived')) {
            return __('Clinic ৳0 · Doctor ৳0');
        }

        try {
            $split = app(StationsTillService::class)->computeSplit(
                $item->list_price_taka,
                (int) ($get('cash_taka') ?? 0),
                (int) ($get('mobile_taka') ?? 0),
                $item->house_share_taka,
            );
        } catch (InvalidArgumentException) {
            return '—';
        }

        return __('Clinic ৳:c · Doctor ৳:d', [
            'c' => number_format($split['clinic_share']),
            'd' => number_format($split['doctor_share']),
        ]);
    }
}
