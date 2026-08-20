<?php

namespace App\Filament\TenantAdmin\Resources\Users\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\Users\UserResource;
use App\Models\User;
use App\Support\StaffDeskJobs;
use App\Support\StaffDeskScope;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = UserResource::class;

    /** @var list<int|string> */
    protected array $chamberIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof User) {
            $data['chamber_ids'] = $record->chambers()->pluck('chambers.id')->all();
            $data['desk_jobs'] = $record->desk_jobs ?? StaffDeskJobs::ALL_JOBS;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        $record = $this->getRecord();

        if ($record instanceof User && $record->isHelper()) {
            unset($data['role'], $data['email'], $data['assigned_doctor_id'], $data['chamber_ids'], $data['desk_jobs'], $data['desk_is_lead']);
        }

        if ($record instanceof User
            && $actor instanceof User
            && UserResource::actorIsLeadOnly()
            && ! StaffDeskScope::leadMayManageStaff($actor, $record)) {
            throw ValidationException::withMessages([
                'email' => __('You cannot edit this login.'),
            ]);
        }

        if (($data['role'] ?? null) === User::ROLE_HELPER) {
            throw ValidationException::withMessages([
                'role' => __('ChamberQ helper access cannot be created from this login.'),
            ]);
        }

        if ($actor instanceof User && UserResource::actorIsLeadOnly()) {
            $data['role'] = User::ROLE_STAFF;
            $data['desk_is_lead'] = $record instanceof User ? $record->desk_is_lead : false;
        } elseif ($record instanceof User && ! $record->isHelper()) {
            $data['role'] = \App\Support\TenantPanelUserRoles::normalize(
                $data['role'] ?? null,
                $actor,
                $record,
            );
        }

        if (! ($actor instanceof User && $actor->canManageUsers())) {
            unset($data['desk_is_lead']);
            if ($record instanceof User) {
                $data['desk_is_lead'] = $record->desk_is_lead;
            }
        }

        unset($data['chamber_ids']);

        if ($record instanceof User && $actor instanceof User) {
            StaffDeskScope::guardAssignedDoctor($actor, isset($data['assigned_doctor_id']) ? (int) $data['assigned_doctor_id'] : null);
        }

        if (! in_array($data['role'] ?? null, [User::ROLE_STAFF, User::ROLE_DOCTOR], true)) {
            $data['assigned_doctor_id'] = null;
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

    protected function beforeSave(): void
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

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof User || $record->isHelper()) {
            return;
        }

        $state = $this->form->getState();
        $role = \App\Support\TenantPanelUserRoles::normalize(
            $state['role'] ?? null,
            auth()->user(),
            $record,
        );

        if ($record->role !== $role) {
            $record->forceFill(['role' => $role])->save();
        }

        StaffDeskScope::syncChambers($record, $this->chamberIds);
    }
}
