<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankTransaction extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_TRANSFER = 'transfer';

    protected $fillable = [
        'bank_account_id',
        'branch_id',
        'linked_transaction_id',
        'date',
        'type',
        'amount',
        'description',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_transaction_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDeposits($query)
    {
        return $query->where('type', self::TYPE_DEPOSIT);
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('type', self::TYPE_WITHDRAWAL);
    }

    /**
     * Net impact on balance: positive for deposit, negative for withdrawal.
     */
    public function getBalanceImpact(): float
    {
        return $this->type === self::TYPE_DEPOSIT
            ? (float) $this->amount
            : -(float) $this->amount;
    }

    public function isTransferEntry(): bool
    {
        return filled($this->linked_transaction_id);
    }

    public function getDisplayTypeLabel(): string
    {
        if ($this->isTransferEntry()) {
            return $this->type === self::TYPE_DEPOSIT ? 'Transfer In' : 'Transfer Out';
        }

        return match ($this->type) {
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_WITHDRAWAL => 'Withdrawal',
            self::TYPE_TRANSFER => 'Transfer',
            default => ucfirst((string) $this->type),
        };
    }

    public function getDisplayTypeColor(): string
    {
        if ($this->isTransferEntry()) {
            return 'info';
        }

        return match ($this->type) {
            self::TYPE_DEPOSIT => 'success',
            self::TYPE_WITHDRAWAL => 'danger',
            self::TYPE_TRANSFER => 'info',
            default => 'gray',
        };
    }

    public function getCounterpartyAccountName(): ?string
    {
        return $this->linkedTransaction?->bankAccount?->name;
    }

    public static function getExpectedBalanceForAccount(BankAccount $account): float
    {
        $opening = (float) $account->opening_balance;
        $deposits = (float) self::where('bank_account_id', $account->id)->deposits()->sum('amount');
        $withdrawals = (float) self::where('bank_account_id', $account->id)->withdrawals()->sum('amount');

        return $opening + $deposits - $withdrawals;
    }

    public static function getTotalExpectedBalance(): float
    {
        return BankAccount::all()->sum(fn (BankAccount $account) => self::getExpectedBalanceForAccount($account));
    }
}
