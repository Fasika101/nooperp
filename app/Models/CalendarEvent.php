<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CalendarEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'color',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_event_user')
            ->withTimestamps();
    }

    public function isAllDay(): bool
    {
        return $this->start_time === null;
    }

    public static function colorOptions(): array
    {
        return [
            'violet' => 'Violet',
            'blue'   => 'Blue',
            'green'  => 'Green',
            'red'    => 'Red',
            'yellow' => 'Yellow',
            'orange' => 'Orange',
            'pink'   => 'Pink',
            'gray'   => 'Gray',
        ];
    }

    public function hexColor(): string
    {
        return match ($this->color) {
            'blue'   => '#3b82f6',
            'green'  => '#10b981',
            'red'    => '#ef4444',
            'yellow' => '#f59e0b',
            'orange' => '#f97316',
            'pink'   => '#ec4899',
            'gray'   => '#6b7280',
            default  => '#8b5cf6', // violet
        };
    }
}
