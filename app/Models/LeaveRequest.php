<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use BelongsToTenant;

    public const TYPE_ANNUAL = 'annual';

    public const TYPE_SICK = 'sick';

    public const TYPE_UNPAID = 'unpaid';

    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'leave_type',
        'status',
        'reason',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'start_date' => DateOnly::class,
        'end_date' => DateOnly::class,
        'reviewed_at' => 'datetime',
    ];

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_ANNUAL => __('Annual'),
            self::TYPE_SICK => __('Sick'),
            self::TYPE_UNPAID => __('Unpaid'),
            self::TYPE_OTHER => __('Other'),
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
