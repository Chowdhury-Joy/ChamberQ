<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use BelongsToTenant;
    
    protected $fillable = [
        'name',
        'user_id',
        'practice_type',
        'staff_may_enter_prescriptions',
        'qualifications',
        'registration_number',
    ];

    protected $casts = [
        'staff_may_enter_prescriptions' => 'boolean',
    ];

    public const PRACTICE_GENERAL = 'general_physician';

    public const PRACTICE_GYNECOLOGIST = 'gynecologist';

    public const PRACTICE_DENTIST = 'dentist';

    public const PRACTICE_PEDIATRICIAN = 'pediatrician';

    public const PRACTICE_CARDIOLOGIST = 'cardiologist';

    public const PRACTICE_DERMATOLOGIST = 'dermatologist';

    /**
     * The login this doctor signs in with, when one has been matched.
     *
     * Optional: a practice may list a visiting doctor who never logs in, and
     * a clinic admin may not have paired accounts to profiles yet.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    public static function practiceTypeOptions(): array
    {
        return [
            self::PRACTICE_GENERAL => __('General physician'),
            self::PRACTICE_GYNECOLOGIST => __('Gynecologist'),
            self::PRACTICE_DENTIST => __('Dentist'),
            self::PRACTICE_PEDIATRICIAN => __('Pediatrician'),
            self::PRACTICE_CARDIOLOGIST => __('Cardiologist'),
            self::PRACTICE_DERMATOLOGIST => __('Dermatologist'),
        ];
    }

    public function practiceTypeLabel(): string
    {
        return self::practiceTypeOptions()[$this->practice_type ?? self::PRACTICE_GENERAL]
            ?? (string) $this->practice_type;
    }

    /**
     * This doctor writes on paper and lets staff key it in afterwards.
     *
     * There is no per-prescription approval step: turning this on IS the
     * doctor's standing permission, so the switch itself is the safeguard.
     */
    public function allowsStaffPrescriptionEntry(): bool
    {
        return (bool) $this->staff_may_enter_prescriptions;
    }
}
