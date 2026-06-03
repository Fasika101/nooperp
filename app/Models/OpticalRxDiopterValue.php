<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpticalRxDiopterValue extends Model
{
    public const GROUP_SINGLE_SPH = 'single_sph';

    public const GROUP_SINGLE_CYL = 'single_cyl';

    public const GROUP_PROGRESSIVE_SPH = 'progressive_sph';

    public const GROUP_PROGRESSIVE_CYL = 'progressive_cyl';

    protected $fillable = [
        'group',
        'value',
        'price',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return [
            self::GROUP_SINGLE_SPH => 'Single vision — Sphere (SPH)',
            self::GROUP_SINGLE_CYL => 'Single vision — Cylinder (CYL)',
            self::GROUP_PROGRESSIVE_SPH => 'Progressive — Sphere (SPH)',
            self::GROUP_PROGRESSIVE_CYL => 'Progressive — Cylinder (CYL)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_keys(self::groupOptions());
    }
}
