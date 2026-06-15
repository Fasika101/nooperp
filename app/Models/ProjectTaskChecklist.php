<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskChecklist extends Model
{
    protected $fillable = ['project_task_id', 'title', 'is_done', 'position'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }
}
