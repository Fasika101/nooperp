<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectFile extends Model
{
    protected $fillable = [
        'project_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'description',
        'uploaded_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDownloadUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getFormattedSize(): string
    {
        if (! $this->file_size) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
