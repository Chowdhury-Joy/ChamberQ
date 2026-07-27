<?php

namespace App\Filament\TenantAdmin\Pages;

use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use App\Models\Booking;
use Carbon\Carbon;
use App\Services\BookingService;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class DailyRoster extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.tenant-admin.pages.daily-roster';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManageQueue() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->whereDate('booking_date', today())
                    ->orderByRaw("CASE WHEN status = 'in_chamber' THEN 1 WHEN status = 'waiting' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'cancelled' THEN 4 ELSE 5 END")
                    ->orderBy('serial_number')
            )
            ->columns([
                TextColumn::make('serial_number')->label('Serial'),
                TextColumn::make('patient_name')->label('Name')->searchable(),
                TextColumn::make('patient_phone')->label('Phone')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'primary',
                    }),
            ])
            ->recordActions([
                Action::make('call')
                    ->label('Call to Chamber')
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => $record->status === 'waiting')
                    ->action(fn (Booking $record) => $record->update(['status' => 'in_chamber'])),

                Action::make('complete')
                    ->label('Mark Completed')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => in_array($record->status, ['waiting', 'in_chamber']))
                    ->action(fn (Booking $record) => $record->update(['status' => 'completed'])),
            ])
            ->headerActions([
                Action::make('manageQueue')
                    ->label('Manage Live Queue')
                    ->icon('heroicon-o-queue-list')
                    ->url(LiveQueueControl::getUrl())
                    ->color('primary'),

                Action::make('newWalkIn')
                    ->label(__('New Walk-In'))
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        TextInput::make('patient_name')
                            ->label(__('Patient name'))
                            ->extraInputAttributes(['name' => 'patient_name'])
                            ->autocomplete('name')
                            ->required(),
                        TextInput::make('patient_phone')
                            ->label(__('Phone number'))
                            ->extraInputAttributes(['name' => 'patient_phone'])
                            ->autocomplete('tel')
                            ->tel()
                            ->required()
                            ->rule('regex:/^(?:\+?88)?01[3-9]\d{8}$/')
                            ->validationMessages([
                                'regex' => __('Please enter a valid Bangladeshi mobile number, for example 01712345678.'),
                            ]),
                        Select::make('bookable')
                            ->label(__('Session'))
                            // Resolved names, never raw ids: two sessions both
                            // labelled "Morning Shift" are indistinguishable.
                            ->options(fn () => static::todaysBookableOptions())
                            ->required()
                            ->native(false)
                            ->searchable(),
                    ])
                    ->action(function (array $data, BookingService $bookingService) {
                        [$type, $id] = explode(':', $data['bookable']);

                        $bookable = $type === 'lab'
                            ? LabCollectionSlot::findOrFail($id)
                            : ScheduleSession::findOrFail($id);

                        // Same service as online bookings — a separate write path
                        // would bypass the lock and duplicate serial numbers.
                        $bookingService->createBookingForBookable(
                            $bookable,
                            today()->toDateString(),
                            $data['patient_name'],
                            $data['patient_phone']
                        );
                    })
                    ->successNotificationTitle(__('Walk-in added to today\'s queue.'))
            ]);
    }

    /**
     * Today's bookable sessions and lab windows, labelled so staff can tell two
     * identically-named sessions apart.
     *
     * @return array<string, string>
     */
    protected static function todaysBookableOptions(): array
    {
        $today = today()->dayOfWeek;
        $options = [];

        $sessions = ScheduleSession::with(['doctor', 'chamber'])
            ->where('day_of_week', $today)
            ->get();

        foreach ($sessions as $session) {
            $options['session:' . $session->id] = sprintf(
                '%s — %s (%s, %s–%s)',
                $session->doctor?->name ?? __('Unknown doctor'),
                $session->chamber?->name ?? __('Unknown chamber'),
                $session->session_name,
                Carbon::parse($session->start_time)->format('g:i A'),
                Carbon::parse($session->end_time)->format('g:i A'),
            );
        }

        if (tenant()?->hasFeature('lab_tests')) {
            $slots = LabCollectionSlot::with('chamber')->where('day_of_week', $today)->get();

            foreach ($slots as $slot) {
                $options['lab:' . $slot->id] = sprintf(
                    '%s — %s (%s–%s)',
                    __('Lab collection'),
                    $slot->chamber?->name ?? __('Main lab'),
                    Carbon::parse($slot->start_time)->format('g:i A'),
                    Carbon::parse($slot->end_time)->format('g:i A'),
                );
            }
        }

        return $options;
    }
}
