<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Booking;
use App\Models\FeeCatalogItem;
use App\Services\StationsHandoffService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class StationsHandoffForm
{
    public static function bookAction(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(__('Send to intervention'))
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->modalHeading(__('Send to intervention'))
            ->modalDescription(__('Intervention hours are often in the morning, before visiting hours. Choose Same day only if they will stay or come back today.'))
            ->modalSubmitActionLabel(__('Send'))
            ->visible(function (...$args) use ($booking): bool {
                $target = self::resolveBooking($booking, $args);

                return $target !== null
                    && self::actorMaySend()
                    && app(StationsHandoffService::class)->canSendVisit($target);
            })
            ->fillForm(function (...$args) use ($booking): array {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return [];
                }

                $options = app(StationsHandoffService::class)->sittingOptions($target);
                $default = collect($options)->firstWhere('is_default') ?? $options[0] ?? null;

                return [
                    'sitting_key' => $default['key'] ?? null,
                    'remarks' => $target->remarks,
                ];
            })
            ->schema(function (...$args) use ($booking): array {
                $target = self::resolveBooking($booking, $args);

                return array_merge(self::sittingFields($target), self::interventionTypeFields(), [
                    StaffBookingForm::remarksField(),
                ]);
            })
            ->action(function (array $data, ...$args) use ($booking): void {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return;
                }

                try {
                    $choice = app(StationsHandoffService::class)->parseSittingKey((string) ($data['sitting_key'] ?? ''));
                    $procedure = app(StationsHandoffService::class)->sendVisitToIntervention(
                        $target,
                        $choice['date'],
                        $choice['session_id'],
                        filled($data['fee_catalog_item_id'] ?? null)
                            ? (int) $data['fee_catalog_item_id']
                            : null,
                        filled($data['remarks'] ?? null) ? (string) $data['remarks'] : null,
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $when = $procedure->booking_date?->isToday()
                    ? __('today')
                    : $procedure->booking_date?->isoFormat('ddd D MMM');

                Notification::make()
                    ->title(__('Procedure row added'))
                    ->body(__('Serial :n on the intervention list for :when.', [
                        'n' => $procedure->serial_number,
                        'when' => $when,
                    ]))
                    ->success()
                    ->send();
            });
    }

    public static function moveAction(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(__('Move intervention'))
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->modalHeading(__('Move intervention'))
            ->modalDescription(__('If they cannot come on the booked day, pick another intervention sitting.'))
            ->modalSubmitActionLabel(__('Move'))
            ->visible(function (...$args) use ($booking): bool {
                $target = self::resolveBooking($booking, $args);

                return $target !== null
                    && self::actorMaySend()
                    && app(StationsHandoffService::class)->canMove($target);
            })
            ->fillForm(function (...$args) use ($booking): array {
                $target = self::resolveBooking($booking, $args);
                $procedure = $target
                    ? app(StationsHandoffService::class)->openProcedureFor($target)
                    : null;
                if (! $procedure) {
                    return [];
                }

                $source = $procedure->relatedBooking ?? $procedure;
                $options = app(StationsHandoffService::class)->sittingOptions(
                    $source->id === $procedure->id ? $procedure : $source,
                );

                $currentKey = $procedure->booking_date?->toDateString().'|'.$procedure->bookable_id;
                $default = collect($options)->firstWhere('key', $currentKey)
                    ?? collect($options)->firstWhere('is_default')
                    ?? $options[0]
                    ?? null;

                return ['sitting_key' => $default['key'] ?? null];
            })
            ->schema(function (...$args) use ($booking): array {
                $target = self::resolveBooking($booking, $args);
                $procedure = $target
                    ? app(StationsHandoffService::class)->openProcedureFor($target)
                    : null;
                $source = $procedure?->relatedBooking ?? $procedure;

                return self::sittingFields($source);
            })
            ->action(function (array $data, ...$args) use ($booking): void {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return;
                }

                try {
                    $choice = app(StationsHandoffService::class)->parseSittingKey((string) ($data['sitting_key'] ?? ''));
                    $procedure = app(StationsHandoffService::class)->moveProcedure(
                        $target,
                        $choice['date'],
                        $choice['session_id'],
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $when = $procedure->booking_date?->isToday()
                    ? __('today')
                    : $procedure->booking_date?->isoFormat('ddd D MMM');

                Notification::make()
                    ->title(__('Intervention moved'))
                    ->body(__('Serial :n on the intervention list for :when.', [
                        'n' => $procedure->serial_number,
                        'when' => $when,
                    ]))
                    ->success()
                    ->send();
            });
    }

    public static function sendToCounselingAction(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(__('Send to counseling'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Send to counseling'))
            ->modalDescription(__('Puts this patient on today\'s counseling list. No fee, no voucher.'))
            ->modalSubmitActionLabel(__('Send'))
            ->visible(function (...$args) use ($booking): bool {
                $target = self::resolveBooking($booking, $args);

                return $target !== null
                    && self::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToCounseling($target);
            })
            ->action(function (...$args) use ($booking): void {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return;
                }

                try {
                    $counseling = app(StationsHandoffService::class)->sendToCounseling($target);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Sent to counseling'))
                    ->body(__('Serial :n on today\'s counseling list.', [
                        'n' => $counseling->serial_number,
                    ]))
                    ->success()
                    ->send();
            });
    }

    public static function sendToLabAction(Action $action, ?Closure $booking = null): Action
    {
        return $action
            ->label(__('Send to lab'))
            ->icon('heroicon-o-arrow-right-circle')
            ->color('success')
            ->modalHeading(__('Send to lab'))
            ->modalDescription(__('Choose the lab type. They go on today\'s list. Collect the scan fee at the door.'))
            ->modalSubmitActionLabel(__('Send'))
            ->visible(function (...$args) use ($booking): bool {
                $target = self::resolveBooking($booking, $args);

                return $target !== null
                    && self::actorMaySend()
                    && app(StationsHandoffService::class)->canSendToLab($target);
            })
            ->fillForm(function (...$args) use ($booking): array {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return [];
                }

                $options = app(StationsHandoffService::class)->labTypeOptions($target);

                return ['lab_type' => array_key_first($options)];
            })
            ->schema(function (...$args) use ($booking): array {
                $target = self::resolveBooking($booking, $args);
                $options = $target
                    ? app(StationsHandoffService::class)->labTypeOptions($target)
                    : [];

                if ($options === []) {
                    return [
                        Placeholder::make('no_lab')
                            ->hiddenLabel()
                            ->content(__('This centre has no lab room. Tick Lab on Branding.')),
                    ];
                }

                return [
                    Radio::make('lab_type')
                        ->label(__('Lab type'))
                        ->options($options)
                        ->required(),
                ];
            })
            ->action(function (array $data, ...$args) use ($booking): void {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return;
                }

                $handoff = app(StationsHandoffService::class);
                $labType = (string) ($data['lab_type'] ?? '');
                $typeLabel = $handoff->labTypeOptions($target)[$labType] ?? __('Lab');

                try {
                    $row = $handoff->sendToLab($target, $labType);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Sent to lab'))
                    ->body(__('Serial :n on today\'s :type list.', [
                        'n' => $row->serial_number,
                        'type' => $typeLabel,
                    ]))
                    ->success()
                    ->send();
            });
    }

    public static function sendToMskAction(Action $action, ?Closure $booking = null): Action
    {
        return self::sendToLabAction($action, $booking);
    }

    public static function sendToReportAction(Action $action, ?Closure $booking = null): Action
    {
        return self::freeRoomAction(
            $action,
            $booking,
            __('Send to report'),
            __('Puts this patient on today\'s report list. No extra fee.'),
            __('Sent to report'),
            __('Serial :n on today\'s report list.'),
            fn (StationsHandoffService $handoff, Booking $target): bool => $handoff->canSendToReport($target),
            fn (StationsHandoffService $handoff, Booking $target): Booking => $handoff->sendToReport($target),
        );
    }

    public static function actorMaySend(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return app(StationsHandoffService::class)->actorMaySend($user);
    }

    /**
     * @param  callable(StationsHandoffService, Booking): bool  $can
     * @param  callable(StationsHandoffService, Booking): Booking  $send
     */
    private static function freeRoomAction(
        Action $action,
        ?Closure $booking,
        string $label,
        string $description,
        string $doneTitle,
        string $doneBody,
        callable $can,
        callable $send,
    ): Action {
        return $action
            ->label($label)
            ->icon('heroicon-o-arrow-right-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription($description)
            ->modalSubmitActionLabel(__('Send'))
            ->visible(function (...$args) use ($booking, $can): bool {
                $target = self::resolveBooking($booking, $args);

                return $target !== null
                    && self::actorMaySend()
                    && $can(app(StationsHandoffService::class), $target);
            })
            ->action(function (...$args) use ($booking, $send, $doneTitle, $doneBody): void {
                $target = self::resolveBooking($booking, $args);
                if (! $target) {
                    return;
                }

                try {
                    $row = $send(app(StationsHandoffService::class), $target);
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($doneTitle)
                    ->body(strtr($doneBody, [':n' => (string) $row->serial_number]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  list<mixed>  $args
     */
    private static function resolveBooking(?Closure $booking, array $args): ?Booking
    {
        if ($booking) {
            $resolved = $booking();

            return $resolved instanceof Booking ? $resolved : null;
        }

        foreach ($args as $arg) {
            if ($arg instanceof Booking) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function sittingFields(?Booking $booking): array
    {
        if (! $booking) {
            return [
                Placeholder::make('no_patient')
                    ->hiddenLabel()
                    ->content(__('No patient is selected.')),
            ];
        }

        $forOptions = $booking;
        $handoff = app(StationsHandoffService::class);
        if ($handoff->isOpenProcedure($booking) && $booking->relatedBooking) {
            $forOptions = $booking->relatedBooking;
        }

        $options = $handoff->sittingOptions($forOptions);
        if ($options === []) {
            return [
                Placeholder::make('no_sittings')
                    ->hiddenLabel()
                    ->content(__('No intervention sitting is scheduled for this doctor.')),
            ];
        }

        return [
            Radio::make('sitting_key')
                ->label(__('When'))
                ->options(collect($options)->mapWithKeys(
                    fn (array $option): array => [$option['key'] => $option['label']],
                )->all())
                ->descriptions(collect($options)->mapWithKeys(
                    fn (array $option): array => [$option['key'] => $option['description']],
                )->all())
                ->required(),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function interventionTypeFields(): array
    {
        $options = FeeCatalogItem::interventionTypeOptions();
        if ($options === []) {
            return [];
        }

        return [
            Select::make('fee_catalog_item_id')
                ->label(__('Intervention type'))
                ->helperText(__('PRP, epidural, nerve block — whatever is on this clinic’s fee list.'))
                ->options($options)
                ->required()
                ->native(false)
                ->searchable(),
        ];
    }
}
