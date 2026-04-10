<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
