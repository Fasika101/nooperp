<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLeadStage extends Model
{
    protected $fillable = ['name', 'position'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }
}
