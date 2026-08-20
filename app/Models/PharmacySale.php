<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacySale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'patient_id',
        'booking_id',
        'prescription_id',
        'cash_entry_id',
        'refund_cash_entry_id',
        'patient_name',
        'patient_phone',
        'method',
        'amount',
        'cash_taka',
        'mobile_taka',
        'mobile_method',
        'waived',
        'voided_at',
        'recorded_by',
        'occurred_on',
        'note',
        'receipt_number',
    ];

    protected $casts = [
        'amount' => 'integer',
        'cash_taka' => 'integer',
        'mobile_taka' => 'integer',
        'waived' => 'boolean',
        'voided_at' => 'datetime',
        'occurred_on' => DateOnly::class,
        'receipt_number' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PharmacySaleItem::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function cashEntry(): BelongsTo
    {
        return $this->belongsTo(ChamberCashEntry::class, 'cash_entry_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function receiptLabel(): string
    {
        return $this->receipt_number !== null
            ? (string) $this->receipt_number
            : (string) $this->id;
    }
}
