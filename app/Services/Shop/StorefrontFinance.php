<?php

namespace App\Services\Shop;

use App\Models\BankAccount;
use App\Models\PaymentType;
use App\Models\Setting;

class StorefrontFinance
{
    public const BANK_ACCOUNT_NAME = 'Website orders';

    public const PAYMENT_TYPE_NAME = 'Chapa (Website)';

    /**
     * Ensure a dedicated bank account + payment type exist for online sales,
     * and keep them linked. Returns the payment type used for website orders.
     */
    public static function paymentType(): PaymentType
    {
        $account = self::bankAccount();

        $configuredId = config('storefront.payment_type_id');
        $type = $configuredId
            ? PaymentType::query()->whereKey((int) $configuredId)->first()
            : null;

        if (! $type) {
            $type = PaymentType::query()
                ->where('name', self::PAYMENT_TYPE_NAME)
                ->first();
        }

        if (! $type) {
            $type = PaymentType::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Chapa%')->orWhere('name', 'like', '%Online%');
                })
                ->orderBy('id')
                ->first();
        }

        if (! $type) {
            $type = PaymentType::create([
                'name' => self::PAYMENT_TYPE_NAME,
                'is_global' => true,
                'branch_id' => null,
                'bank_account_id' => $account->id,
                'is_active' => true,
                'is_accounts_receivable' => false,
            ]);
        } else {
            $updates = [];
            if ((int) $type->bank_account_id !== (int) $account->id) {
                $updates['bank_account_id'] = $account->id;
            }
            if (! $type->is_active) {
                $updates['is_active'] = true;
            }
            if ($updates !== []) {
                $type->update($updates);
            }
        }

        return $type->fresh(['bankAccount']);
    }

    public static function bankAccount(): BankAccount
    {
        $configuredId = config('storefront.bank_account_id');
        if ($configuredId) {
            $existing = BankAccount::query()->whereKey((int) $configuredId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $account = BankAccount::query()
            ->where('name', self::BANK_ACCOUNT_NAME)
            ->first();

        if ($account) {
            return $account;
        }

        $currency = Setting::getDefaultCurrency();

        return BankAccount::create([
            'name' => self::BANK_ACCOUNT_NAME,
            'bank_name' => 'Online / Chapa',
            'account_number' => 'WEBSITE',
            'currency' => $currency ?: 'ETB',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_default' => false,
            'is_global' => true,
            'branch_id' => null,
            'notes' => 'All New Online Optics / shop website order payments deposit here.',
        ]);
    }
}
