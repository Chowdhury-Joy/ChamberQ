<?php

namespace App\Filament\TenantAdmin\Resources\LabTests;

use App\Filament\TenantAdmin\Resources\LabTests\Pages\CreateLabTest;
use App\Filament\TenantAdmin\Resources\LabTests\Pages\EditLabTest;
use App\Filament\TenantAdmin\Resources\LabTests\Pages\ListLabTests;
use App\Filament\TenantAdmin\Resources\LabTests\Schemas\LabTestForm;
use App\Filament\TenantAdmin\Resources\LabTests\Tables\LabTestsTable;
use App\Models\LabTest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms;

class LabTestResource extends Resource
{
    protected static ?string $model = LabTest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Test name'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('price')
                ->label(__('Price'))
                ->required()
                ->numeric()
                ->minValue(0)
                ->prefix('৳'),
            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->helperText(__('Shown on your website. Write it in whatever language your patients read.'))
                ->columnSpanFull(),
            // The clinically important field. Shown prominently on the patient's
            // ticket and never machine-translated: a mistranslated fasting
            // requirement means a wasted test and a wasted trip.
            Forms\Components\Textarea::make('preparation_instructions')
                ->label(__('Preparation instructions'))
                ->helperText(__('For example: "Do not eat for 12 hours before your sample is taken." Shown prominently to the patient.'))
                ->columnSpanFull(),
            Forms\Components\TextInput::make('sample_type')
                ->label(__('Sample type'))
                ->placeholder(__('Blood, Urine, ...'))
                ->maxLength(255),
            Forms\Components\TextInput::make('turnaround_time')
                ->label(__('Report ready in'))
                ->placeholder(__('Same day, 48 hours, ...'))
                ->maxLength(255),
            Forms\Components\TextInput::make('display_order')
                ->label(__('Display order'))
                ->numeric()
                ->default(0)
                ->helperText(__('Lower numbers appear first.')),
            Forms\Components\Toggle::make('is_active')
                ->label(__('Available for booking'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return LabTestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabTests::route('/'),
            'create' => CreateLabTest::route('/create'),
            'edit' => EditLabTest::route('/{record}/edit'),
        ];
    }
}
