<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'project_task_stage_id',
        'priority',
        'due_date',
        'progress',
        'estimated_hours',
        'is_starred',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date'        => 'date',
            'progress'        => 'integer',
            'estimated_hours' => 'decimal:2',
            'is_starred'      => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectTaskStage::class, 'project_task_stage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_task_user')
            ->withTimestamps();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ProjectLabel::class, 'project_label_task')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class, 'project_task_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ProjectTaskChecklist::class, 'project_task_id')->orderBy('position');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(ProjectTimeLog::class, 'project_task_id');
    }

    public function totalLoggedHours(): float
    {
        return (float) $this->timeLogs()->sum('hours');
    }

    public function checklistProgress(): array
    {
        $total = $this->checklists()->count();
        $done  = $this->checklists()->where('is_done', true)->count();

        return ['total' => $total, 'done' => $done];
    }
}
