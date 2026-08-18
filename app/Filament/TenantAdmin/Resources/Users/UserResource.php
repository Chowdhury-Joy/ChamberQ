<?php

namespace App\Filament\TenantAdmin\Resources\Users;

use App\Filament\TenantAdmin\Resources\Users\Pages\CreateUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\EditUser;
use App\Filament\TenantAdmin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Support\ChamberQHelperAccess;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
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

        return $user?->canViewStaffAndRoles() ?? false;
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->canManageDeskStaff() ?? false;
    }

    public static function actorIsLeadOnly(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && StaffDeskJobs::isLeadDesk($user)
            && ! $user->canManageUsers();
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

        $actor = auth()->user();
        if ($actor instanceof User && static::actorIsLeadOnly()) {
            return StaffDeskScope::leadMayManageStaff($actor, $record);
        }

        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        if ($record->isHelper()) {
            return false;
        }

        $actor = auth()->user();
        if ($actor instanceof User && static::actorIsLeadOnly()) {
            return StaffDeskScope::leadMayManageStaff($actor, $record)
                && $actor->id !== $record->id;
        }

        return static::canManageUsersForActor($actor) && ! $record->isHelper();
    }

    protected static function canManageUsersForActor(?User $actor): bool
    {
        return $actor instanceof User && $actor->canManageUsers();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['chambers', 'assignedDoctor']);

        $actor = auth()->user();

        if ($actor instanceof User && static::actorIsLeadOnly()) {
            $query->where('role', User::ROLE_STAFF);

            $leadChambers = StaffDeskScope::chamberIdsFor($actor);
            if ($leadChambers !== null) {
                $query->whereHas('chambers', fn (Builder $chamber): Builder => $chamber->whereIn('chambers.id', $leadChambers));
            }

            return $query;
        }

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

    public static function roleGroupLabel(string $role): string
    {
        return match ($role) {
            User::ROLE_OWNER => __('Owners'),
            User::ROLE_HELPER => __('ChamberQ helpers'),
            User::ROLE_DOCTOR => __('Doctors'),
            User::ROLE_STAFF => __('Desk staff'),
            default => __('Other'),
        };
    }

    public static function showsBranchScopeFields(?string $role): bool
    {
        if (! in_array($role, [User::ROLE_STAFF, User::ROLE_DOCTOR], true)) {
            return false;
        }

        return StaffDeskScope::tenantHasMultipleChambers();
    }

    public static function showsAssistantDoctorField(?string $role): bool
    {
        return $role === User::ROLE_STAFF;
    }

    public static function showsDeskJobFields(?string $role): bool
    {
        return $role === User::ROLE_STAFF;
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
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', tenant('id')),
                    ),

                Forms\Components\Select::make('role')
                    ->label('Access Role')
                    ->options(fn (): array => static::actorIsLeadOnly()
                        ? [User::ROLE_STAFF => 'Staff (desk + content)']
                        : self::clientRoleOptions())
                    ->default(User::ROLE_STAFF)
                    ->required()
                    ->live()
                    ->disabled(fn (?User $record): bool => ($record?->isHelper() ?? false)
                        || (static::actorIsLeadOnly() && $record !== null))
                    ->dehydrated(),

                Forms\Components\Checkbox::make('desk_is_lead')
                    ->label(__('Lead desk'))
                    ->helperText(__('Covers any counter and may hire other desk staff. Only an owner or ChamberQ helper can grant this.'))
                    ->visible(fn (Get $get): bool => static::showsDeskJobFields($get('role'))
                        && ! static::actorIsLeadOnly()
                        && (auth()->user()?->canManageUsers() ?? false))
                    ->live(),

                Forms\Components\CheckboxList::make('desk_jobs')
                    ->label(__('Desk jobs'))
                    ->options(StaffDeskJobs::jobOptions())
                    ->helperText(__('Leave all ticked for one person doing everything. Untick to split till, queue, and prep.'))
                    ->visible(fn (Get $get): bool => static::showsDeskJobFields($get('role'))
                        && ! ($get('desk_is_lead') ?? false))
                    ->columns(1)
                    ->default(StaffDeskJobs::ALL_JOBS),

                Forms\Components\Select::make('chamber_ids')
                    ->label(__('Branches'))
                    ->multiple()
                    ->options(fn (): array => StaffDeskScope::chamberOptionsFor(auth()->user()))
                    ->helperText(__('Leave empty for all branches. Owners and ChamberQ helpers always see every branch.'))
                    ->visible(fn (Get $get): bool => static::showsBranchScopeFields($get('role')))
                    ->dehydrated(false),

                Forms\Components\Select::make('assigned_doctor_id')
                    ->label(__('Works for'))
                    ->options(fn (): array => StaffDeskScope::doctorOptionsFor(auth()->user()))
                    ->placeholder(__('Hospital team — every doctor at their branch'))
                    ->helperText(__('Pick one doctor only for a private assistant. Leave empty for hospital reception.'))
                    ->visible(fn (Get $get): bool => static::showsAssistantDoctorField($get('role')))
                    ->nullable(),

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

                Tables\Columns\TextColumn::make('chambers.name')
                    ->label(__('Branches'))
                    ->badge()
                    ->placeholder(__('All branches'))
                    ->visible(fn (): bool => StaffDeskScope::tenantHasMultipleChambers())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assignedDoctor.name')
                    ->label(__('Works for'))
                    ->placeholder(__('Hospital team'))
                    ->visible(fn (): bool => true)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('desk_jobs')
                    ->label(__('Desk jobs'))
                    ->badge()
                    ->state(fn (User $record): string => implode(', ', StaffDeskJobs::labelsFor($record)))
                    ->placeholder('—')
                    ->visible(fn (): bool => true)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultGroup(
                Group::make('role')
                    ->label(__('Job'))
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (User $record): string => self::roleGroupLabel($record->role))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        "CASE role WHEN 'admin' THEN 1 WHEN 'helper' THEN 2 WHEN 'doctor' THEN 3 WHEN 'staff' THEN 4 ELSE 5 END"
                    ))
            )
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label(__('Job'))
                    ->options(function (): array {
                        $options = [
                            User::ROLE_OWNER => __('Owners'),
                            User::ROLE_DOCTOR => __('Doctors'),
                            User::ROLE_STAFF => __('Desk staff'),
                        ];

                        if (ChamberQHelperAccess::actorSeesHelpersOnStaffList()) {
                            $options[User::ROLE_HELPER] = __('ChamberQ helpers');
                        }

                        return $options;
                    }),
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
