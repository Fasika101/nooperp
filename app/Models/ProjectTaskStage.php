<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTaskStage extends Model
{
    protected $fillable = ['name', 'position'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_task_stage_id');
    }
}
