<?php

namespace App\Filament\TenantAdmin\Resources\Doctors\Pages;

use App\Filament\TenantAdmin\Concerns\HasPrimarySaveAndDangerDelete;
use App\Filament\TenantAdmin\Resources\Doctors\DoctorResource;
use App\Models\Doctor;
use Filament\Resources\Pages\EditRecord;

class EditDoctor extends EditRecord
{
    use HasPrimarySaveAndDangerDelete;

    protected static string $resource = DoctorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['notify_channels'] = ($this->getRecord() instanceof Doctor)
            ? $this->getRecord()->notifyChannels()
            : Doctor::defaultNotifyChannels();

        $record = $this->getRecord();
        $data['inherit_practice_rules'] = ! is_array($record->practice_rules) || $record->practice_rules === [];
        $data = array_merge($data, \App\Services\PracticeRules::forDoctor($record instanceof Doctor ? $record : null));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return self::applyPracticeRules($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyPracticeRules(array $data): array
    {
        $inherit = (bool) ($data['inherit_practice_rules'] ?? true);
        unset($data['inherit_practice_rules']);

        if ($inherit) {
            $data['practice_rules'] = null;
        } else {
            $data['practice_rules'] = \App\Services\PracticeRules::normalize($data);
        }

        foreach (array_keys(\App\Services\PracticeRules::defaults()) as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
