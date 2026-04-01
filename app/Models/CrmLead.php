<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    protected $fillable = [
        'title',
        'company_name',
        'email',
        'phone',
        'source',
        'crm_lead_stage_id',
        'assigned_user_id',
        'customer_id',
        'notes',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStage::class, 'crm_lead_stage_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function leadTasks(): HasMany
    {
        return $this->hasMany(CrmLeadTask::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }
}
