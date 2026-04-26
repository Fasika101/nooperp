<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    protected $fillable = [
        'customer_id',
        'branch_id',
        'affiliate_id',
        'affiliate_commission_type',
        'affiliate_commission_rate',
        'affiliate_commission_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_status',
        'due_date',
        'discount_amount',
        'discount_type',
        'shipping_amount',
        'tax_amount',
        'tax_type_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'affiliate_commission_rate' => 'decimal:2',
            'affiliate_commission_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    /**
     * Recalculate amount_paid / balance_due / payment_status from completed, non–A/R payments.
     */
    public function syncPaymentTotals(): void
    {
        $this->unsetRelation('payments');
        $this->load(['payments.paymentType']);

        $paid = (float) $this->payments
            ->where('status', 'completed')
            ->filter(fn (Payment $p) => ! ($p->paymentType?->is_accounts_receivable))
            ->sum('amount');

        $total = (float) $this->total_amount;
        $balance = round(max(0, $total - $paid), 2);
        $paymentStatus = $balance <= 0.01
            ? self::PAYMENT_STATUS_PAID
            : ($paid > 0 ? self::PAYMENT_STATUS_PARTIAL : self::PAYMENT_STATUS_UNPAID);

        $this->forceFill([
            'amount_paid' => round($paid, 2),
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
        ])->saveQuietly();
    }

    public function scopeWithBalanceDue($query)
    {
        return $query->where('balance_due', '>', 0)
            ->where('status', 'completed');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class, 'tax_type_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
