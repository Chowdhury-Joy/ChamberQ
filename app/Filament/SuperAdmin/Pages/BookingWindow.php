<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class BookingWindow extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Booking window';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Booking window';

    protected static ?string $slug = 'booking-window';

    protected string $view = 'filament.super-admin.pages.booking-window';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->role === User::ROLE_SUPER_ADMIN && $user->tenant_id === null;
    }

    public function mount(): void
    {
        $this->form->fill([
            'patient_booking_horizon_days' => PlatformSetting::patientBookingHorizonDays(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('patient_booking_horizon_days')
                    ->label('How many days ahead a patient can book')
                    ->helperText('One number for every Maestro and Clinic Front door. Desk walk-ins are not limited. Default is 60 (about two months).')
                    ->numeric()
                    ->required()
                    ->integer()
                    ->minValue(PlatformSetting::MIN_HORIZON_DAYS)
                    ->maxValue(PlatformSetting::MAX_HORIZON_DAYS)
                    ->suffix('days'),
            ]);
    }

    public function save(): void
    {
        $days = (int) $this->form->getState()['patient_booking_horizon_days'];

        PlatformSetting::current()->update([
            'patient_booking_horizon_days' => $days,
        ]);

        Notification::make()
            ->title('Booking window saved')
            ->body("Patients can book up to {$days} days ahead on every Front door.")
            ->success()
            ->send();
    }
}
