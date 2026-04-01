<?php

namespace App\Models;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerImportAudit extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_PHONE_NAME_REPLACED = 'phone_name_replaced';

    public const ACTION_PHONE_NAME_KEPT = 'phone_name_kept';

    public const ACTION_EMAIL_MATCH_UPDATED = 'email_match_updated';

    public const ACTION_EMAIL_MATCH_NO_CHANGE = 'email_match_no_change';

    protected $fillable = [
        'import_id',
        'customer_id',
        'action',
        'row_name',
        'row_phone',
        'row_email',
        'previous_name',
        'current_name',
        'note',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function summarizeForImport(int $importId): array
    {
        $counts = static::query()
            ->where('import_id', $importId)
            ->selectRaw('action, COUNT(*) as aggregate')
            ->groupBy('action')
            ->pluck('aggregate', 'action');

        $created = (int) ($counts[self::ACTION_CREATED] ?? 0);
        $phoneNameReplaced = (int) ($counts[self::ACTION_PHONE_NAME_REPLACED] ?? 0);
        $phoneNameKept = (int) ($counts[self::ACTION_PHONE_NAME_KEPT] ?? 0);
        $emailMatchUpdated = (int) ($counts[self::ACTION_EMAIL_MATCH_UPDATED] ?? 0);
        $emailMatchNoChange = (int) ($counts[self::ACTION_EMAIL_MATCH_NO_CHANGE] ?? 0);

        return [
            'created' => $created,
            'phone_name_replaced' => $phoneNameReplaced,
            'phone_name_kept' => $phoneNameKept,
            'phone_matches_total' => $phoneNameReplaced + $phoneNameKept,
            'email_match_updated' => $emailMatchUpdated,
            'email_match_no_change' => $emailMatchNoChange,
            'email_matches_total' => $emailMatchUpdated + $emailMatchNoChange,
        ];
    }
}
