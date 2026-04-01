<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmDealStage extends Model
{
    protected $fillable = ['name', 'position'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }
}
