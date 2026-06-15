<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTimeLog extends Model
{
    protected $fillable = [
        'project_task_id',
        'project_id',
        'user_id',
        'hours',
        'logged_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'hours'     => 'decimal:2',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
