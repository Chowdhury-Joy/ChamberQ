<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'pay_period',
        'amount_taka',
        'paid_on',
        'method',
        'cash_entry_id',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'amount_taka' => 'integer',
        'paid_on' => DateOnly::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cashEntry(): BelongsTo
    {
        return $this->belongsTo(ChamberCashEntry::class, 'cash_entry_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
