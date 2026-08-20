<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ReferringDoctor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'specialty',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    /**
     * Desk Collect fee / walk-in: add a GP without leaving the form.
     *
     * Same name (any capitalisation) reuses the existing row so monthly
     * payouts do not split across "Dr Rashed" and "dr rashed".
     *
     * @param  array{name?: mixed, phone?: mixed, specialty?: mixed}  $data
     */
    public static function findOrCreateFromDesk(array $data): self
    {
        if (! tenant()?->hasReferrals()) {
            throw new InvalidArgumentException(__('Referrals are not enabled for this clinic.'));
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException(__('Doctor name is required.'));
        }

        $phone = filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null;
        $specialty = filled($data['specialty'] ?? null) ? trim((string) $data['specialty']) : null;
        $needle = mb_strtolower($name);

        $existing = static::query()
            ->get()
            ->first(fn (self $doctor): bool => mb_strtolower(trim($doctor->name)) === $needle);

        if ($existing) {
            $updates = [];

            if (! $existing->is_active) {
                $updates['is_active'] = true;
            }

            if (blank($existing->phone) && filled($phone)) {
                $updates['phone'] = $phone;
            }

            if (blank($existing->specialty) && filled($specialty)) {
                $updates['specialty'] = $specialty;
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing->fresh() ?? $existing;
        }

        return static::create([
            'name' => $name,
            'phone' => $phone,
            'specialty' => $specialty,
            'is_active' => true,
        ]);
    }

    public function displayLabel(): string
    {
        $label = $this->name;

        if (filled($this->specialty)) {
            $label .= ' ('.$this->specialty.')';
        }

        return $label;
    }
}
