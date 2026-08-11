<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Support\MedicinePickerFields;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\MedicineUsage;
use App\Services\MedicineService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyMedicines extends Page implements HasActions, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'My medicines';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'My medicines';

    protected string $view = 'filament.tenant-admin.pages.my-medicines';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canRecordVisitNotes() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MedicineUsage::query()
                    ->where('user_id', auth()->id())
                    ->whereNull('hidden_at')
                    // Alphabetical: this is a list the doctor curates, so it
                    // must stay where they left it rather than reordering
                    // itself from behaviour they cannot see.
                    ->orderBy('medicine_name')
            )
            ->columns([
                TextColumn::make('medicine_name')->label(__('Medicine'))->searchable(),
                TextColumn::make('generic_name')->label(__('Generic')),
                TextColumn::make('last_dose')->label(__('Default dose')),
                TextColumn::make('last_frequency')->label(__('Frequency')),
                TextColumn::make('last_duration')->label(__('Duration')),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('Edit defaults'))
                    ->form([
                        ...MedicinePickerFields::schema(),
                        TextInput::make('generic_name')->maxLength(120),
                        TextInput::make('last_dose')->maxLength(80),
                        TextInput::make('last_frequency')->maxLength(80),
                        TextInput::make('last_duration')->maxLength(80),
                    ])
                    ->fillForm(function (MedicineUsage $record): array {
                        $base = VisitNotesFormSchema::prescriptionItemStateFromStored(
                            $record->medicine_name,
                            $record->generic_name,
                            $record->last_dose,
                            $record->last_frequency,
                            $record->last_duration,
                        );

                        return [
                            'medicine_name' => $base['medicine_name'],
                            'generic_name' => $record->generic_name,
                            'last_dose' => $record->last_dose,
                            'last_frequency' => $record->last_frequency,
                            'last_duration' => $record->last_duration,
                        ];
                    })
                    ->action(function (MedicineUsage $record, array $data, MedicineService $medicineService): void {
                        $name = $medicineService->resolveMedicineNameFromFormState($data);

                        $record->update([
                            'medicine_name' => $name,
                            'generic_name' => filled($data['generic_name'] ?? null) ? trim($data['generic_name']) : null,
                            'last_dose' => filled($data['last_dose'] ?? null) ? trim($data['last_dose']) : null,
                            'last_frequency' => filled($data['last_frequency'] ?? null) ? trim($data['last_frequency']) : null,
                            'last_duration' => filled($data['last_duration'] ?? null) ? trim($data['last_duration']) : null,
                        ]);

                        Notification::make()->title(__('Medicine updated'))->success()->send();
                    }),
                Action::make('hide')
                    ->label(__('Hide'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (MedicineUsage $record): void {
                        $record->update(['hidden_at' => now()]);
                        Notification::make()->title(__('Medicine hidden from your search'))->success()->send();
                    }),
            ])
            ->headerActions([
                Action::make('add')
                    ->label(__('Add medicine'))
                    ->form([
                        ...MedicinePickerFields::schema(),
                        TextInput::make('generic_name')->maxLength(120),
                        TextInput::make('last_dose')->maxLength(80),
                        TextInput::make('last_frequency')->maxLength(80),
                        TextInput::make('last_duration')->maxLength(80),
                    ])
                    ->action(function (array $data, MedicineService $medicineService): void {
                        $medicineService->saveDoctorMedicine(auth()->user(), [
                            'medicine_name' => $medicineService->resolveMedicineNameFromFormState($data) ?? '',
                            'generic_name' => $data['generic_name'] ?? null,
                            'dose' => $data['last_dose'] ?? null,
                            'frequency' => $data['last_frequency'] ?? null,
                            'duration' => $data['last_duration'] ?? null,
                        ]);

                        Notification::make()->title(__('Medicine added'))->success()->send();
                    }),
            ])
            ->emptyStateHeading(__('No medicines yet'))
            ->emptyStateDescription(__('Add your commonly used medicines here, or they will appear automatically as you prescribe.'));
    }
}
