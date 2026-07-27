<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Chamber extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'hours',
        'contact',
    ];

    protected $casts = [
        'hours' => 'array',
    ];

    /**
     * Google Maps link for WhatsApp / ticket handoff (Fatima’s husband needs directions).
     */
    public function googleMapsUrl(): ?string
    {
        $lat = trim((string) ($this->latitude ?? ''));
        $lng = trim((string) ($this->longitude ?? ''));

        if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
            return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng);
        }

        $address = trim((string) ($this->address ?? ''));
        if ($address !== '') {
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
        }

        return null;
    }
}
