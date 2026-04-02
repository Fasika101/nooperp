<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Public landing page (/), meta tags, and link-preview values from Settings with sensible defaults.
 */
final class PublicLanding
{
    private const RASTER_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    public static function brandName(): string
    {
        $v = Setting::get('public_brand_name');

        return (is_string($v) && $v !== '') ? $v : (string) config('app.name', 'Liba Digitals');
    }

    public static function metaTitle(): string
    {
        $v = Setting::get('public_meta_title');

        return (is_string($v) && $v !== '') ? $v : (self::brandName().' — ERP & inventory');
    }

    public static function metaDescription(): string
    {
        $v = Setting::get('public_meta_description');

        return (is_string($v) && $v !== '')
            ? $v
            : 'Liba Digitals ERP: unified operations for retail and optical — sales, POS, stock, finance, and inventory. Sign in to manage your business.';
    }

    public static function heroTitle(): string
    {
        $v = Setting::get('public_hero_title');

        return (is_string($v) && $v !== '') ? $v : 'Liba Digitals ERP';
    }

    public static function heroLead(): string
    {
        $v = Setting::get('public_hero_lead');

        return (is_string($v) && $v !== '')
            ? $v
            : 'Unified operations for retail and optical businesses — sales, POS, stock, finance, and inventory in one place.';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function featureOne(): array
    {
        $title = Setting::get('public_feature_1_title');
        $text = Setting::get('public_feature_1_text');

        return [
            (is_string($title) && $title !== '') ? $title : 'Inventory & purchasing',
            (is_string($text) && $text !== '')
                ? $text
                : 'Track products, restocks, categories, and stock levels across your catalog.',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function featureTwo(): array
    {
        $title = Setting::get('public_feature_2_title');
        $text = Setting::get('public_feature_2_text');

        return [
            (is_string($title) && $title !== '') ? $title : 'Sales & finance',
            (is_string($text) && $text !== '')
                ? $text
                : 'Orders, POS, customers, bank accounts, expenses, and reporting in the admin panel.',
        ];
    }

    public static function primaryButtonLabel(): string
    {
        $v = Setting::get('public_primary_button_label');

        return (is_string($v) && $v !== '') ? $v : 'Log in now';
    }

    public static function themeColor(): string
    {
        $v = Setting::get('public_theme_color');

        return (is_string($v) && $v !== '') ? $v : '#FDFDFC';
    }

    public static function logoStoragePath(): ?string
    {
        $v = Setting::get('public_logo_storage_path');

        return (is_string($v) && $v !== '' && Storage::disk('public')->exists($v)) ? $v : null;
    }

    public static function faviconStoragePath(): ?string
    {
        $v = Setting::get('public_favicon_storage_path');

        return (is_string($v) && $v !== '' && Storage::disk('public')->exists($v)) ? $v : null;
    }

    public static function logoUrl(): ?string
    {
        $stored = self::logoStoragePath();
        if ($stored !== null) {
            return self::ensureHttpsUrl(Storage::disk('public')->url($stored));
        }

        foreach (['images/logo.svg', 'images/logo.png', 'images/logo.webp'] as $rel) {
            if (file_exists(public_path($rel))) {
                return self::ensureHttpsUrl(asset($rel));
            }
        }

        return null;
    }

    public static function faviconUrl(): ?string
    {
        $stored = self::faviconStoragePath();
        if ($stored !== null) {
            return self::ensureHttpsUrl(Storage::disk('public')->url($stored));
        }

        if (file_exists(public_path('favicon.ico'))) {
            return self::ensureHttpsUrl(asset('favicon.ico'));
        }

        $logo = self::logoUrl();
        if ($logo !== null && preg_match('#\.(png|jpg|jpeg|webp|gif)(\?|$)#i', $logo)) {
            return $logo;
        }

        return null;
    }

    /**
     * Raster image URL for Open Graph / Twitter (Telegram, etc.). SVG is skipped.
     *
     * @return array{url: string, width: int|null, height: int|null, type: string|null}|null
     */
    public static function ogImageInfo(): ?array
    {
        $logoPath = self::logoStoragePath();
        if ($logoPath !== null) {
            $info = self::rasterInfoFromDisk($logoPath);
            if ($info !== null) {
                return $info;
            }
        }

        $publicRasters = ['images/logo.png', 'images/logo.jpg', 'images/logo.jpeg', 'images/logo.webp'];
        foreach ($publicRasters as $rel) {
            if (! file_exists(public_path($rel))) {
                continue;
            }
            $abs = public_path($rel);
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            $url = self::ensureHttpsUrl(asset($rel));
            $dims = @getimagesize($abs);
            $type = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => null,
            };

            return [
                'url' => $url,
                'width' => $dims !== false ? $dims[0] : null,
                'height' => $dims !== false ? $dims[1] : null,
                'type' => $type,
            ];
        }

        return null;
    }

    /**
     * @return array{url: string, width: int|null, height: int|null, type: string|null}|null
     */
    private static function rasterInfoFromDisk(string $pathOnPublicDisk): ?array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($pathOnPublicDisk)) {
            return null;
        }

        $ext = strtolower(pathinfo($pathOnPublicDisk, PATHINFO_EXTENSION));
        if (! in_array($ext, self::RASTER_EXT, true)) {
            return null;
        }

        $abs = $disk->path($pathOnPublicDisk);
        if (! is_readable($abs)) {
            return null;
        }

        $dims = @getimagesize($abs);
        $type = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };

        return [
            'url' => self::ensureHttpsUrl($disk->url($pathOnPublicDisk)),
            'width' => $dims !== false ? $dims[0] : null,
            'height' => $dims !== false ? $dims[1] : null,
            'type' => $type,
        ];
    }

    private static function ensureHttpsUrl(string $url): string
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            return preg_replace('#^http://#', 'https://', $url) ?? $url;
        }

        return $url;
    }
}
