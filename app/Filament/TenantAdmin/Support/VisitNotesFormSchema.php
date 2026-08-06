<?php

namespace App\Filament\TenantAdmin\Support;

use App\Models\Condition;
use App\Services\ConditionService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;

class VisitNotesFormSchema
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('_visit_notes_hint')
                ->label('')
                ->default(__('All fields are optional — leave blank to complete without notes.'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
            Section::make(__('Diagnosis'))
                ->schema([
                    Select::make('condition_id')
                        ->label(__('Coded condition'))
                        ->placeholder(__('Type at least 3 letters to search…'))
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(function (string $search): array {
                            if (mb_strlen(trim($search)) < ConditionService::MIN_SEARCH_LENGTH) {
                                return [];
                            }

                            return app(ConditionService::class)
                                ->search($search, auth()->user())
                                ->mapWithKeys(fn (array $row) => [$row['id'] => $row['name']])
                                ->all();
                        })
                        ->getOptionLabelUsing(fn (?string $value): ?string => $value
                            ? Condition::query()->find($value)?->name
                            : null)
                        ->visible(fn (Get $get): bool => blank($get('diagnosis_free_text')))
                        ->native(false),
                    TextInput::make('diagnosis_free_text')
                        ->label(__('Or free-text diagnosis'))
                        ->placeholder(__('Uncoded — not counted in research'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => blank($get('condition_id'))),
                ])
                ->columns(1),
            Textarea::make('advice')
                ->label(__('Advice'))
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('tests_advised')
                ->label(__('Tests advised'))
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('reports_seen')
                ->label(__('Reports seen'))
                ->rows(2)
                ->columnSpanFull(),
            DatePicker::make('follow_up_date')
                ->label(__('Follow-up date'))
                ->native(false)
                ->minDate(now()->toDateString()),
            Section::make(__('Prescription'))
                ->schema([
                    Repeater::make('prescription_items')
                        ->label(__('Medicines'))
                        ->schema([
                            TextInput::make('medicine_name')
                                ->label(__('Medicine (brand)'))
                                ->placeholder(__('e.g. NAPA'))
                                ->maxLength(120),
                            TextInput::make('generic_name')
                                ->label(__('Generic name'))
                                ->placeholder(__('e.g. Paracetamol'))
                                ->maxLength(120),
                            TextInput::make('dose')
                                ->label(__('Dose'))
                                ->placeholder(__('e.g. 500 mg'))
                                ->maxLength(80),
                            TextInput::make('frequency')
                                ->label(__('Frequency'))
                                ->placeholder(__('e.g. 1+1+1'))
                                ->maxLength(80),
                            TextInput::make('duration')
                                ->label(__('Duration'))
                                ->placeholder(__('e.g. 5 days'))
                                ->maxLength(80),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('Add medicine'))
                        ->reorderable()
                        ->collapsible(),
                ])
                ->collapsed(),
            Section::make(__('Voice note'))
                ->schema([
                    View::make('filament.tenant-admin.components.visit-voice-recorder')
                        ->columnSpanFull(),
                    Hidden::make('voice_path')
                        ->extraAttributes(['data-visit-voice-path' => 'true']),
                    Textarea::make('voice_transcript')
                        ->label(__('Voice transcript (optional)'))
                        ->helperText(__('Manual transcript for convenience — editable, never replaces the recording. Does not set diagnosis.'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->collapsed(),
            Section::make(__('Paper prescription photo'))
                ->schema([
                    FileUpload::make('prescription_photo')
                        ->label(__('Photo of handwritten prescription'))
                        ->helperText(__('Image only — no handwriting recognition.'))
                        ->image()
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/heic',
                            'image/heif',
                        ])
                        ->maxSize(5120)
                        ->disk('public')
                        ->directory(fn () => 'visit-photos/'.(tenant('id') ?? 'shared'))
                        ->visibility('public')
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ];
    }
}
