<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $fillable = [
        'customer_id',
        'left_eye_sphere',
        'left_eye_cylinder',
        'left_eye_axis',
        'right_eye_sphere',
        'right_eye_cylinder',
        'right_eye_axis',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'left_eye_sphere' => 'decimal:2',
            'left_eye_cylinder' => 'decimal:2',
            'right_eye_sphere' => 'decimal:2',
            'right_eye_cylinder' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
