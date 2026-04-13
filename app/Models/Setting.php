<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('settings', 3600, fn () => self::all()->pluck('value', 'key')->toArray());

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $value = $value === '' || $value === null ? null : (string) $value;
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }

    public static function getDefaultCurrency(): string
    {
        return self::get('default_currency', 'ETB');
    }

    /**
     * Which product attribute fields (brand, size, color, etc.) appear on product forms and related tables.
     * All default to enabled when unset.
     *
     * @return array<string, bool>
     */
    public static function getProductOptionFieldsEnabled(): array
    {
        $defaults = [
            ProductOption::TYPE_BRAND => true,
            ProductOption::TYPE_SIZE => true,
            ProductOption::TYPE_COLOR => true,
            ProductOption::TYPE_GENDER => true,
            ProductOption::TYPE_MATERIAL => true,
            ProductOption::TYPE_SHAPE => true,
        ];

        $stored = self::get('product_option_fields_enabled');
        if ($stored === null || $stored === '') {
            return $defaults;
        }

        $decoded = json_decode($stored, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $decoded)) {
                $defaults[$key] = filter_var($decoded[$key], FILTER_VALIDATE_BOOL);
            }
        }

        return $defaults;
    }

    public static function setProductOptionFieldsEnabled(array $flags): void
    {
        $allowed = array_keys(ProductOption::getTypeOptions());
        $filtered = array_intersect_key($flags, array_flip($allowed));
        self::set('product_option_fields_enabled', json_encode($filtered));
    }

    public static function isProductOptionFieldEnabled(string $type): bool
    {
        return self::getProductOptionFieldsEnabled()[$type] ?? true;
    }

    public static function getBusinessPhone(): ?string
    {
        return self::get('business_phone');
    }

    public static function getBusinessEmail(): ?string
    {
        return self::get('business_email');
    }

    public static function getBusinessTin(): ?string
    {
        return self::get('business_tin');
    }

    /**
     * Extra amount added to progressive Rx lens lines when either eye has cylinder ≠ 0.00 (POS).
     */
    public static function getOpticalProgressiveCylinderSurcharge(): float
    {
        $v = self::get('optical_progressive_cylinder_surcharge', '0');
        if ($v === null || $v === '') {
            return 0.0;
        }

        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * Store a value encrypted with the app key (for API tokens).
     */
    public static function setEncrypted(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            self::set($key, null);

            return;
        }

        self::set($key, Crypt::encryptString($value));
    }

    /**
     * Decrypt a stored value; returns legacy plaintext if decryption fails.
     */
    public static function getEncrypted(string $key): ?string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return null;
        }

        try {
            return Crypt::decryptString($v);
        } catch (\Throwable) {
            return $v;
        }
    }

    public static function hasEncrypted(string $key): bool
    {
        $v = self::get($key);

        return $v !== null && $v !== '';
    }
}
