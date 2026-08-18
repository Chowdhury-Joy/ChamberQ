<?php

namespace App\Filament\TenantAdmin\Resources\ReferringDoctors;

use App\Filament\TenantAdmin\Resources\ReferringDoctors\Pages\CreateReferringDoctor;
use App\Filament\TenantAdmin\Resources\ReferringDoctors\Pages\EditReferringDoctor;
use App\Filament\TenantAdmin\Resources\ReferringDoctors\Pages\ListReferringDoctors;
use App\Models\ReferringDoctor;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferringDoctorResource extends Resource
{
    protected static ?string $model = ReferringDoctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Referring doctors';

    protected static ?string $modelLabel = 'Referring doctor';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() ?? false)
            && (tenant()?->hasReferrals() ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label(__('Phone'))
                ->tel()
                ->maxLength(20),
            Forms\Components\TextInput::make('specialty')
                ->label(__('Specialty / clinic'))
                ->maxLength(255),
            Forms\Components\Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(3),
            Forms\Components\Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone'),
                TextColumn::make('specialty')->label(__('Specialty')),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferringDoctors::route('/'),
            'create' => CreateReferringDoctor::route('/create'),
            'edit' => EditReferringDoctor::route('/{record}/edit'),
        ];
    }
}
