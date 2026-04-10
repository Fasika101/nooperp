<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEntry extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_REMOTE = 'remote';

    public const STATUS_HOLIDAY = 'holiday';

    protected $fillable = [
        'employee_id',
        'work_date',
        'time_in',
        'time_out',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PRESENT => 'Present',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_REMOTE => 'Remote',
            self::STATUS_HOLIDAY => 'Holiday',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
