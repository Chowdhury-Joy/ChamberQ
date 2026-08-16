<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_ON_LEAVE = 'on_leave';

    protected $fillable = [
        'employee_id',
        'work_date',
        'status',
        'check_in_at',
        'check_out_at',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'work_date' => DateOnly::class,
    ];

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PRESENT => __('Present'),
            self::STATUS_ABSENT => __('Absent'),
            self::STATUS_LATE => __('Late'),
            self::STATUS_HALF_DAY => __('Half day'),
            self::STATUS_ON_LEAVE => __('On leave'),
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
