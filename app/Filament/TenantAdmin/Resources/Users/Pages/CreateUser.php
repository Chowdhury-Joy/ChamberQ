<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimaryCreate;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Models\User;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    use HasPrimaryCreate;

    protected static string $resource = UserResource::class;

    /** @var list<int|string> */
    protected array $chamberIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if (($data['role'] ?? null) === User::ROLE_HELPER) {
            throw ValidationException::withMessages([
                'role' => __('ChamberQ helper access cannot be created from this login.'),
            ]);
        }

        if ($actor instanceof User && UserResource::actorIsLeadOnly()) {
            $data['role'] = User::ROLE_STAFF;
            $data['desk_is_lead'] = false;
        } else {
            $data['role'] = \App\Support\TenantPanelUserRoles::normalize(
                $data['role'] ?? null,
                $actor,
            );
        }

        if (! ($actor instanceof User && $actor->canManageUsers())) {
            $data['desk_is_lead'] = false;
        }

        unset($data['chamber_ids']);

        if (! in_array($data['role'] ?? null, [User::ROLE_STAFF, User::ROLE_DOCTOR], true)) {
            $data['assigned_doctor_id'] = null;
        }

        if ($actor instanceof User) {
            StaffDeskScope::guardAssignedDoctor($actor, isset($data['assigned_doctor_id']) ? (int) $data['assigned_doctor_id'] : null);
        }

        if (($data['role'] ?? null) !== User::ROLE_STAFF) {
            $data['assigned_doctor_id'] = null;
            $data['desk_jobs'] = null;
            $data['desk_is_lead'] = false;
        } else {
            $data['desk_jobs'] = ($data['desk_is_lead'] ?? false)
                ? null
                : StaffDeskJobs::normalizeJobsForStorage(is_array($data['desk_jobs'] ?? null) ? $data['desk_jobs'] : null);
        }

        return $data;
    }

    protected function beforeCreate(): void
    {
        $state = $this->form->getState();
        $this->chamberIds = is_array($state['chamber_ids'] ?? null) ? $state['chamber_ids'] : [];

        /** @var User|null $actor */
        $actor = auth()->user();
        if ($actor instanceof User && UserResource::actorIsLeadOnly()) {
            $this->chamberIds = StaffDeskScope::constrainChamberIdsForLeadHire($actor, $this->chamberIds);
            StaffDeskScope::assertLeadHireChamberIds($actor, $this->chamberIds);
        }
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof User) {
            return;
        }

        StaffDeskScope::syncChambers($record, $this->chamberIds);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $role = (string) ($data['role'] ?? User::ROLE_STAFF);
        unset($data['role']);

        $record = static::getModel()::create($data);
        if ($record instanceof User) {
            $record->forceFill(['role' => $role])->save();
        }

        return $record;
    }
}
