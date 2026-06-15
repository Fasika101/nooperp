<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectLabel extends Model
{
    protected $fillable = ['name', 'color'];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTask::class, 'project_label_task');
    }
}
