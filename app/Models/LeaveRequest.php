<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const TYPE_ANNUAL = 'annual';

    public const TYPE_SICK = 'sick';

    public const TYPE_UNPAID = 'unpaid';

    public const TYPE_MATERNITY = 'maternity';

    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days_count',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
            'days_count' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LeaveRequest $request): void {
            $start = $request->start_date instanceof Carbon ? $request->start_date : Carbon::parse($request->start_date);
            $end = $request->end_date instanceof Carbon ? $request->end_date : Carbon::parse($request->end_date);
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
                $request->start_date = $start->format('Y-m-d');
                $request->end_date = $end->format('Y-m-d');
            }
            $request->days_count = round($start->diffInDays($end) + 1, 2);
        });
    }

    public static function leaveTypeOptions(): array
    {
        return [
            self::TYPE_ANNUAL => 'Annual leave',
            self::TYPE_SICK => 'Sick leave',
            self::TYPE_UNPAID => 'Unpaid',
            self::TYPE_MATERNITY => 'Maternity / paternity',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
