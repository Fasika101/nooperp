<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBug extends Model
{
    public const PRIORITY_LOW      = 'low';
    public const PRIORITY_NORMAL   = 'normal';
    public const PRIORITY_HIGH     = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_CLOSED      = 'closed';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'assigned_user_id',
        'priority',
        'status',
        'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
