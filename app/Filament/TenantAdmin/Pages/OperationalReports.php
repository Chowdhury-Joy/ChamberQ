<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Models\Booking;
use App\Models\LabCollectionSlot;
use App\Models\ScheduleSession;
use App\Services\OperationalReportService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OperationalReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Operational Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Operational Reports';

    protected string $view = 'filament.tenant-admin.pages.operational-reports';

    /** @var 'day'|'week'|'month' */
    public string $period = 'day';

    public string $anchorDate = '';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManageOps() ?? false;
    }

    public function mount(): void
    {
        $this->anchorDate = now(OperationalReportService::TIMEZONE)->toDateString();
    }

    public function updatedPeriod(): void
    {
        $this->resetTable();
    }

    public function updatedAnchorDate(): void
    {
        $this->resetTable();
    }

    public function getReportService(): OperationalReportService
    {
        return app(OperationalReportService::class);
    }

    public function getAnchor(): Carbon
    {
        $date = filled($this->anchorDate)
            ? $this->anchorDate
            : now(OperationalReportService::TIMEZONE)->toDateString();

        return Carbon::parse($date, OperationalReportService::TIMEZONE)->startOfDay();
    }

    /**
     * @return array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}
     */
    public function getTotals(): array
    {
        $service = $this->getReportService();
        $anchor = $this->getAnchor();

        return match ($this->period) {
            'week' => $service->countsBetween(...$service->weekRange($anchor)),
            'month' => $service->countsBetween(...$service->monthRange($anchor)),
            default => $service->countsForDate($anchor),
        };
    }

    /**
     * @return array<string, array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}>
     */
    public function getDailyRows(): array
    {
        if ($this->period !== 'week') {
            return [];
        }

        $service = $this->getReportService();

        return $service->dailyBreakdown(...$service->weekRange($this->getAnchor()));
    }

    /**
     * @return list<array{week_start: string, week_end: string, total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}>
     */
    public function getWeeklyRows(): array
    {
        if ($this->period !== 'month') {
            return [];
        }

        return $this->getReportService()->weeklyBreakdownInMonth($this->getAnchor());
    }

    public function getPeriodLabel(): string
    {
        $anchor = $this->getAnchor();
        $service = $this->getReportService();

        if ($this->period === 'week') {
            [$start, $end] = $service->weekRange($anchor);

            return $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
        }

        if ($this->period === 'month') {
            return $anchor->translatedFormat('F Y');
        }

        return $anchor->translatedFormat('l, j F Y');
    }

    public function table(Table $table): Table
    {
        $anchor = $this->getAnchor();

        return $table
            ->query(
                Booking::query()
                    ->with(['bookable' => function ($morphTo) {
                        $morphTo->morphWith([
                            ScheduleSession::class => ['doctor'],
                            LabCollectionSlot::class => ['chamber'],
                        ]);
                    }])
                    ->when(
                        $this->period === 'day',
                        fn ($query) => $query->whereDate('booking_date', $anchor->toDateString()),
                        fn ($query) => $query->whereRaw('1 = 0'),
                    )
                    ->orderBy('serial_number')
            )
            ->columns([
                TextColumn::make('serial_number')
                    ->label(__('Serial'))
                    ->formatStateUsing(fn ($state) => '#'.$state),
                TextColumn::make('patient_name')
                    ->label(__('Patient'))
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label(__('Phone'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting' => 'gray',
                        'called' => 'warning',
                        'in_chamber' => 'success',
                        'completed' => 'info',
                        'skipped' => 'warning',
                        'no_show' => 'danger',
                        'cancelled' => 'danger',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', Str::title($state))),
                TextColumn::make('session_doctor')
                    ->label(__('Session / Doctor'))
                    ->state(function (Booking $record): string {
                        $bookable = $record->bookable;

                        if ($bookable instanceof ScheduleSession) {
                            $session = $bookable->session_name ?: __('Session');
                            $doctor = $bookable->doctor?->name;

                            return $doctor ? "{$session} — {$doctor}" : $session;
                        }

                        if ($bookable instanceof LabCollectionSlot) {
                            return __('Lab collection').($bookable->chamber?->name ? ' — '.$bookable->chamber->name : '');
                        }

                        return '—';
                    }),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('No bookings for this day'))
            ->emptyStateDescription(__('Pick another date or check the live roster.'));
    }
}
