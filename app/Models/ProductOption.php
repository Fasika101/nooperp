<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    public const TYPE_SIZE = 'size';

    public const TYPE_COLOR = 'color';

    public const TYPE_GENDER = 'gender';

    public const TYPE_MATERIAL = 'material';

    public const TYPE_SHAPE = 'shape';

    public const TYPE_BRAND = 'brand';

    protected $fillable = [
        'type',
        'name',
    ];

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_BRAND => 'Brand',
            self::TYPE_SIZE => 'Size',
            self::TYPE_COLOR => 'Color',
            self::TYPE_GENDER => 'Gender',
            self::TYPE_MATERIAL => 'Material',
            self::TYPE_SHAPE => 'Shape',
        ];
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getTypeLabel(): string
    {
        return self::getTypeOptions()[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Trims, drops empties, preserves order, de-duplicates case-insensitively.
     *
     * @param  array<int, mixed>  $parts
     * @return list<string>
     */
    public static function normalizeNamesFromFragments(array $parts): array
    {
        $names = [];
        $seen = [];

        foreach ($parts as $part) {
            $name = trim((string) $part);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /**
     * Split bulk input: newlines and/or commas, then {@see normalizeNamesFromFragments()}.
     *
     * @return list<string>
     */
    public static function parseBulkNames(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];

        return self::normalizeNamesFromFragments($parts);
    }

    /**
     * @param  non-empty-list<string>  $names
     * @return array{created: int, skipped: int, last: self}
     */
    public static function firstOrCreateManyForType(string $type, array $names): array
    {
        $created = 0;
        $skipped = 0;
        $last = null;

        foreach ($names as $name) {
            $option = self::query()->firstOrCreate([
                'type' => $type,
                'name' => $name,
            ]);

            $last = $option;

            if ($option->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        assert($last instanceof self);

        return ['created' => $created, 'skipped' => $skipped, 'last' => $last];
    }

    public function productsAsBrand(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_option_id');
    }

    public function productsAsSize(): HasMany
    {
        return $this->hasMany(Product::class, 'size_option_id');
    }

    public function productsAsColor(): HasMany
    {
        return $this->hasMany(Product::class, 'color_option_id');
    }

    public function productsAsGender(): HasMany
    {
        return $this->hasMany(Product::class, 'gender_option_id');
    }

    public function productsAsMaterial(): HasMany
    {
        return $this->hasMany(Product::class, 'material_option_id');
    }

    public function productsAsShape(): HasMany
    {
        return $this->hasMany(Product::class, 'shape_option_id');
    }

    /**
     * Many-to-many: products that offer this option at POS (sizes/colors, etc.).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function attachedPivotProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_option')->withTimestamps();
    }
}
