<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Support\MedicinePickerFields;
use App\Filament\TenantAdmin\Support\VisitNotesFormSchema;
use App\Models\Condition;
use App\Models\MedicineUsage;
use App\Services\ConditionService;
use App\Services\MedicineService;
use App\Services\PrescriptionTemplateService;
use App\Support\PrescriptionTiming;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
                TextColumn::make('last_timing')
                    ->label(__('Timing'))
                    ->formatStateUsing(fn (?string $state) => PrescriptionTiming::displayLabel($state)),
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
                        Select::make('last_timing')
                            ->label(__('Timing'))
                            ->options(PrescriptionTiming::options())
                            ->native(false),
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
                            'last_timing' => $record->last_timing,
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
                            'last_timing' => PrescriptionTiming::normalize($data['last_timing'] ?? null),
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
                        Select::make('last_timing')
                            ->label(__('Timing'))
                            ->options(PrescriptionTiming::options())
                            ->native(false),
                    ])
                    ->action(function (array $data, MedicineService $medicineService): void {
                        $medicineService->saveDoctorMedicine(auth()->user(), [
                            'medicine_name' => $medicineService->resolveMedicineNameFromFormState($data) ?? '',
                            'generic_name' => $data['generic_name'] ?? null,
                            'dose' => $data['last_dose'] ?? null,
                            'frequency' => $data['last_frequency'] ?? null,
                            'duration' => $data['last_duration'] ?? null,
                            'timing' => $data['last_timing'] ?? null,
                        ]);

                        Notification::make()->title(__('Medicine added'))->success()->send();
                    }),
            ])
            ->emptyStateHeading(__('No medicines yet'))
            ->emptyStateDescription(__('Add the medicines you prescribe most, with the dose you usually give. Or tap the star on any line of a prescription to save it here.'));
    }

    /**
     * Livewire caches `getXProperty()` for the whole request, so a pack just
     * created, renamed or deleted would re-render from the list captured
     * before the write — the doctor saves a pack and appears to have saved
     * nothing until they reload the page.
     */
    private function forgetPacks(): void
    {
        unset($this->rxPacks);
    }

    /**
     * This doctor's packs, for the list in the view.
     *
     * @return list<array<string, mixed>>
     */
    public function getRxPacksProperty(): array
    {
        $user = auth()->user();

        return $user ? app(PrescriptionTemplateService::class)->forDoctor($user) : [];
    }

    /**
     * The fields of a pack.
     *
     * A pack is a prescription without a patient, so it carries the same
     * medicine rows plus the advice, tests and follow-up that normally travel
     * with them — applying one on the consult screen should fill in everything
     * the doctor would otherwise retype.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    private function packFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Pack name'))
                ->placeholder(__('e.g. Gastritis standard'))
                ->required()
                ->maxLength(120),
            Select::make('diagnosis')
                ->label(__('For which diagnosis'))
                ->helperText(__('Optional. Picking the same diagnosis during a consult offers this pack.'))
                ->placeholder(__('Type at least 3 letters to search…'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => mb_strlen(trim($search)) < ConditionService::MIN_SEARCH_LENGTH
                    ? []
                    : app(ConditionService::class)
                        ->search($search, auth()->user())
                        ->mapWithKeys(fn (array $row) => [$row['id'] => $row['name']])
                        ->all())
                ->getOptionLabelUsing(fn (?string $value): ?string => blank($value)
                    ? null
                    : (Condition::query()->find($value)?->name ?? $value))
                ->native(false),
            Repeater::make('prescription_items')
                ->label(__('Medicines'))
                ->schema([
                    // The plain picker, not `prescriptionMedicineSelect()`: that
                    // variant excludes brands already chosen on sibling rows by
                    // reaching up the consult form's state path (`../../prescription_items`),
                    // which does not resolve from here and leaves the field
                    // failing its own required rule.
                    ...MedicinePickerFields::schema(),
                    TextInput::make('generic_name')->label(__('Generic'))->maxLength(120),
                    TextInput::make('dose')->label(__('Dose'))->maxLength(80),
                    TextInput::make('frequency')->label(__('Frequency'))->placeholder('1+0+1')->maxLength(80),
                    TextInput::make('duration')->label(__('Duration'))->placeholder('5 days')->maxLength(80),
                    Select::make('timing')
                        ->label(__('Timing'))
                        ->options(PrescriptionTiming::options())
                        ->native(false),
                    TextInput::make('instructions')->label(__('Instructions'))->maxLength(255),
                ])
                ->columns(2)
                // Starts empty rather than with a blank row: the medicine field
                // is required, so a pre-seeded empty row makes the form refuse
                // to save until the doctor either fills or deletes it.
                ->defaultItems(0)
                ->addActionLabel(__('Add medicine'))
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => filled($state['medicine_name'] ?? null)
                    ? mb_strtoupper((string) $state['medicine_name'])
                    : null),
            TextInput::make('advice')->label(__('Advice'))->maxLength(255),
            TextInput::make('tests_advised')->label(__('Tests advised'))->maxLength(255),
            Select::make('follow_up_relative')
                ->label(__('Follow-up'))
                ->options([
                    '1_week' => __('1 week'),
                    '2_weeks' => __('2 weeks'),
                    '1_month' => __('1 month'),
                    '3_months' => __('3 months'),
                    'as_needed' => __('As needed'),
                ])
                ->native(false),
        ];
    }

    public function createPackAction(): Action
    {
        return Action::make('createPack')
            ->label(__('New pack'))
            ->icon('heroicon-o-plus')
            ->modalHeading(__('New pack'))
            ->modalSubmitActionLabel(__('Save pack'))
            ->form($this->packFormSchema())
            ->action(function (array $data, PrescriptionTemplateService $templates): void {
                $user = auth()->user();

                if (! $user || blank($data['name'] ?? null)) {
                    return;
                }

                $templates->save($user, $data['name'], $data);
                $this->forgetPacks();

                Notification::make()
                    ->title(__('Pack saved: :name', ['name' => trim($data['name'])]))
                    ->success()
                    ->send();
            });
    }

    public function editPackAction(): Action
    {
        return Action::make('editPack')
            ->label(__('Edit'))
            ->modalHeading(__('Edit pack'))
            ->modalSubmitActionLabel(__('Save pack'))
            ->form($this->packFormSchema())
            ->fillForm(function (array $arguments): array {
                $pack = collect($this->rxPacks)->firstWhere('id', $arguments['packId'] ?? null);

                if (! $pack) {
                    return [];
                }

                return [
                    'name' => $pack['name'],
                    'diagnosis' => $pack['condition_id'],
                    'advice' => $pack['advice'],
                    'tests_advised' => $pack['tests_advised'],
                    'follow_up_relative' => $pack['follow_up_relative'],
                    'prescription_items' => $pack['items'],
                ];
            })
            ->action(function (array $data, array $arguments, PrescriptionTemplateService $templates): void {
                $user = auth()->user();
                $packId = $arguments['packId'] ?? null;

                if (! $user || ! $packId || blank($data['name'] ?? null)) {
                    return;
                }

                $existing = collect($this->rxPacks)->firstWhere('id', $packId);

                $templates->save($user, $data['name'], $data);

                // `save()` matches a pack by its name, so a rename writes a
                // *second* pack rather than renaming the first. Without this the
                // doctor quietly accumulates near-duplicates and cannot tell
                // which one the consult screen is offering.
                if ($existing && trim($existing['name']) !== trim($data['name'])) {
                    $templates->delete($user, $packId);
                }

                $this->forgetPacks();

                Notification::make()->title(__('Pack updated'))->success()->send();
            });
    }

    public function deletePackAction(): Action
    {
        return Action::make('deletePack')
            ->label(__('Delete'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Delete this pack?'))
            ->modalDescription(__('Prescriptions already written from it are not affected.'))
            ->action(function (array $arguments, PrescriptionTemplateService $templates): void {
                $user = auth()->user();
                $packId = $arguments['packId'] ?? null;

                if (! $user || ! $packId) {
                    return;
                }

                $templates->delete($user, $packId);
                $this->forgetPacks();

                Notification::make()->title(__('Pack deleted'))->success()->send();
            });
    }
}
