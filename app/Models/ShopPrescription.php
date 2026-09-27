<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ShopPrescription extends Model
{
    protected $fillable = [
        'batch_token',
        'order_id',
        'path',
        'original_name',
        'mime',
        'scan_json',
        'vision_type',
        'confidence',
        'prescription_price',
        'scan_notes',
        'scan_ok',
    ];

    protected function casts(): array
    {
        return [
            'scan_json' => 'array',
            'prescription_price' => 'decimal:2',
            'scan_ok' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function publicUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }
}
