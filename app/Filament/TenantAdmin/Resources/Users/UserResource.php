<?php

namespace App\Filament\TenantAdmin\Resources\Users;

use App\Filament\TenantAdmin\Resources\Users\Pages\CreateUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\EditUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Staff & Roles';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        // Content Editors cannot manage users or change roles
        return $user?->isWebDeveloper() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->extraInputAttributes(['name' => 'name'])
                    ->autocomplete('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->extraInputAttributes(['name' => 'email'])
                    ->autocomplete('email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('role')
                    ->label('Access Role')
                    ->options([
                        'tenant_admin' => 'Tenant Admin (Full Access)',
                        'web_developer' => 'Web Developer (Site Builder & Technical)',
                        'content_editor' => 'Content Editor (Safe Content Updates Only)',
                    ])
                    ->default('content_editor')
                    ->required(),

                Forms\Components\TextInput::make('password')
                    ->extraInputAttributes(['name' => 'password'])
                    ->autocomplete('new-password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->icon('heroicon-m-envelope')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tenant_admin' => 'danger',
                        'web_developer' => 'warning',
                        'content_editor' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tenant_admin' => 'Tenant Admin',
                        'web_developer' => 'Web Developer',
                        'content_editor' => 'Content Editor',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
