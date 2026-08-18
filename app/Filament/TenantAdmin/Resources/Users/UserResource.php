<?php

namespace App\Filament\TenantAdmin\Resources\Users;

use App\Filament\TenantAdmin\Resources\Users\Pages\CreateUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\EditUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Support\ChamberQHelperAccess;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Staff & Roles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->canManageUsers() ?? false;
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        if ($record->isHelper() && ! ChamberQHelperAccess::actorSeesHelpersOnStaffList()) {
            return false;
        }

        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        if ($record->isHelper()) {
            $actor = auth()->user();

            return $actor instanceof User
                && $actor->isHelper()
                && $actor->id === $record->id;
        }

        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        return ! $record->isHelper() && static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (ChamberQHelperAccess::actorSeesHelpersOnStaffList()) {
            return $query;
        }

        return $query->where('role', '!=', User::ROLE_HELPER);
    }

    /**
     * @return array<string, string>
     */
    public static function clientRoleOptions(): array
    {
        return [
            User::ROLE_OWNER => 'Owner (practice setup)',
            User::ROLE_DOCTOR => 'Doctor (operations)',
            User::ROLE_STAFF => 'Staff (desk + content)',
        ];
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
                    ->maxLength(255)
                    // The DB index is (tenant_id, email), so the rule has to be
                    // scoped the same way — otherwise a duplicate inside this
                    // tenant is a 500 instead of a field error, and an address
                    // already used by an unrelated tenant is wrongly rejected.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', tenant('id')),
                    ),

                Forms\Components\Select::make('role')
                    ->label('Access Role')
                    ->options(self::clientRoleOptions())
                    ->default(User::ROLE_STAFF)
                    ->required()
                    ->disabled(fn (?User $record): bool => $record?->isHelper() ?? false)
                    ->dehydrated(),

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
                        User::ROLE_OWNER => 'danger',
                        User::ROLE_HELPER => 'gray',
                        User::ROLE_DOCTOR => 'warning',
                        User::ROLE_STAFF => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        User::ROLE_OWNER => 'Owner',
                        User::ROLE_HELPER => 'ChamberQ helper',
                        User::ROLE_DOCTOR => 'Doctor',
                        User::ROLE_STAFF => 'Staff',
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
