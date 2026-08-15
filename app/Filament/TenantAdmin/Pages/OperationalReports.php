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

    /** @var array{total: int, completed: int, waiting: int, called: int, in_chamber: int, skipped: int, no_show: int, cancelled: int}|null */
    protected ?array $totalsCache = null;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canViewOperationalReports() ?? false;
    }

    public function mount(): void
    {
        $this->anchorDate = now(OperationalReportService::TIMEZONE)->toDateString();
    }

    public function updatedPeriod(): void
    {
        $this->totalsCache = null;
        $this->resetTable();
    }

    public function updatedAnchorDate(): void
    {
        $this->totalsCache = null;
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
        if ($this->totalsCache !== null) {
            return $this->totalsCache;
        }

        $service = $this->getReportService();
        $anchor = $this->getAnchor();

        return $this->totalsCache = match ($this->period) {
            'week' => $service->countsBetween(...$service->weekRange($anchor)),
            'month' => $service->countsBetween(...$service->monthRange($anchor)),
            default => $service->countsForDate($anchor),
        };
    }

    /**
     * Patients still moving through the queue: booked in, not yet finished.
     */
    public function getQueueCount(): int
    {
        $totals = $this->getTotals();

        return $totals['waiting'] + $totals['called'] + $totals['in_chamber'];
    }

    /**
     * Bookings that did not turn into a completed visit.
     */
    public function getProblemCount(): int
    {
        $totals = $this->getTotals();

        return $totals['skipped'] + $totals['no_show'] + $totals['cancelled'];
    }

    public function getCompletionRate(): ?int
    {
        $totals = $this->getTotals();

        if ($totals['total'] === 0) {
            return null;
        }

        return (int) round($totals['completed'] / $totals['total'] * 100);
    }

    /**
     * Display metadata for each status chip, in reporting order.
     *
     * @return array<string, array{label: string, color: string, icon: string, accent: string, accent_soft: string}>
     */
    public function getStatusMeta(): array
    {
        return [
            'completed' => [
                'label' => __('Completed'),
                'color' => 'success',
                'icon' => 'heroicon-m-check-circle',
                'accent' => 'var(--success-600)',
                'accent_soft' => 'var(--success-50)',
            ],
            'waiting' => [
                'label' => __('Waiting'),
                'color' => 'gray',
                'icon' => 'heroicon-m-clock',
                'accent' => 'var(--gray-600)',
                'accent_soft' => 'var(--gray-100)',
            ],
            'called' => [
                'label' => __('Called'),
                'color' => 'warning',
                'icon' => 'heroicon-m-bell-alert',
                'accent' => 'var(--warning-600)',
                'accent_soft' => 'var(--warning-50)',
            ],
            'in_chamber' => [
                'label' => __('In chamber'),
                'color' => 'info',
                'icon' => 'heroicon-m-user',
                'accent' => 'var(--info-600)',
                'accent_soft' => 'var(--info-50)',
            ],
            'skipped' => [
                'label' => __('Skipped'),
                'color' => 'warning',
                'icon' => 'heroicon-m-forward',
                'accent' => 'var(--warning-600)',
                'accent_soft' => 'var(--warning-50)',
            ],
            'no_show' => [
                'label' => __('No-show'),
                'color' => 'danger',
                'icon' => 'heroicon-m-user-minus',
                'accent' => 'var(--danger-600)',
                'accent_soft' => 'var(--danger-50)',
            ],
            'cancelled' => [
                'label' => __('Cancelled'),
                'color' => 'danger',
                'icon' => 'heroicon-m-x-circle',
                'accent' => 'var(--danger-600)',
                'accent_soft' => 'var(--danger-50)',
            ],
        ];
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
                        fn ($query) => $query->where('booking_date', $anchor->toDateString()),
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
