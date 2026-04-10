<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    public const TYPE_CONTRACT = 'contract';

    public const TYPE_ID = 'id_document';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_OFFER_LETTER = 'offer_letter';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'employee_id',
        'title',
        'document_type',
        'file_path',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
        ];
    }

    public static function documentTypeOptions(): array
    {
        return [
            self::TYPE_CONTRACT => 'Contract',
            self::TYPE_OFFER_LETTER => 'Offer letter',
            self::TYPE_ID => 'ID / passport',
            self::TYPE_CERTIFICATE => 'Certificate / license',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
