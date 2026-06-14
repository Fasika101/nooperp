<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'description',
        'customer_id',
        'status',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(ProjectBug::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }
}
