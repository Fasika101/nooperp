<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLeadTask extends Model
{
    protected $fillable = [
        'crm_lead_id',
        'title',
        'is_done',
        'due_date',
        'assigned_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'due_date' => 'date',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
