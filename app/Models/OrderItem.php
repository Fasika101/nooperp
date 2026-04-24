<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'size_option_id',
        'color_option_id',
        'line_label',
        'quantity',
        'price',
        'unit_cost',
        'optical_meta',
        'prescription_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'optical_meta' => 'array',
            'prescription_id' => 'integer',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->line_label) {
            return $this->line_label;
        }

        $product = $this->product;
        if (! $product) {
            return 'Item';
        }

        return $product->formatNameWithVariant(
            $this->size_option_id ? (int) $this->size_option_id : null,
            $this->color_option_id ? (int) $this->color_option_id : null,
        );
    }

    public function frameSize(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'size_option_id');
    }

    public function frameColor(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'color_option_id');
    }

    public function getCogsAttribute(): float
    {
        return (float) ($this->quantity * ($this->unit_cost ?? 0));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<OrderItemRxExtra, $this>
     */
    public function rxExtraCustomizations(): HasMany
    {
        return $this->hasMany(OrderItemRxExtra::class);
    }

    /**
     * Extra Rx customization labels: prefer persisted rows, else JSON snapshot on optical_meta.
     *
     * @return list<string>
     */
    public function getExtraCustomizationNamesList(): array
    {
        if ($this->relationLoaded('rxExtraCustomizations') && $this->rxExtraCustomizations->isNotEmpty()) {
            return $this->rxExtraCustomizations->pluck('name')->filter()->values()->all();
        }

        return collect($this->optical_meta['lens_type_remarks'] ?? [])
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    public function hasOpticalDetails(): bool
    {
        return is_array($this->optical_meta) && $this->optical_meta !== [];
    }

    /**
     * Short label for the lens / service line (order line detail table).
     */
    public function getLensTypeSummaryAttribute(): string
    {
        if (! $this->hasOpticalDetails()) {
            return '—';
        }
        $m = $this->optical_meta;

        return match ($m['route'] ?? '') {
            'no_prescription' => (string) ($m['lens_name'] ?? '—'),
            'prescription' => $this->formatPrescriptionLensSummary(),
            default => '—',
        };
    }

    /**
     * Readable prescription block for admin order view (HTML).
     */
    public function getPrescriptionAdminHtml(): ?HtmlString
    {
        if (! $this->hasOpticalDetails()) {
            return null;
        }

        $m = $this->optical_meta;
        if (($m['route'] ?? '') !== 'prescription') {
            return new HtmlString('<span class="text-gray-500 dark:text-gray-400">—</span>');
        }

        $vision = $m['vision'] ?? 'single';
        $isProgressive = $vision === 'progressive';
        $title = $isProgressive ? 'Progressive vision' : 'Single vision';

        $od = $m['od'] ?? [];
        $os = $m['os'] ?? [];
        $pd = $m['pd'] ?? [];

        $right = $this->formatRxEyeLine('Right (OD)', $od, $isProgressive);
        $left = $this->formatRxEyeLine('Left (OS)', $os, $isProgressive);

        $pdHtml = '';
        if (! empty($pd['mode'])) {
            if (($pd['mode'] ?? '') === 'one' && filled($pd['single'] ?? null)) {
                $pdHtml = '<div class="mt-2 text-sm"><strong>PD</strong> '.e((string) $pd['single']).' mm</div>';
            } elseif (($pd['mode'] ?? '') === 'two') {
                $pdHtml = '<div class="mt-2 text-sm"><strong>PD</strong> OD '.e((string) ($pd['right'] ?? '—')).' / OS '.e((string) ($pd['left'] ?? '—')).'</div>';
            }
        }

        $html = '<div class="prescription-admin space-y-1 text-gray-900 dark:text-gray-100">';
        $html .= '<div class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">'.e($title).'</div>';
        $html .= '<ul class="list-none space-y-1 pl-0 text-sm leading-relaxed">';
        $html .= '<li><span class="font-medium">'.e($right['label']).'</span> '.$right['body'].'</li>';
        $html .= '<li><span class="font-medium">'.e($left['label']).'</span> '.$left['body'].'</li>';
        $html .= '</ul>';
        $html .= $pdHtml;
        $frame = $m['frame'] ?? null;
        if (is_array($frame) && (filled($frame['size_name'] ?? null) || filled($frame['color_name'] ?? null))) {
            $html .= '<div class="mt-2 text-sm"><strong>Frame</strong> '.e(collect([$frame['size_name'] ?? null, $frame['color_name'] ?? null])->filter()->implode(', ')).'</div>';
        }

        $typeNames = implode(', ', $this->getExtraCustomizationNamesList());
        if ($typeNames !== '') {
            $html .= '<div class="mt-2 text-sm"><strong>Extra customizations</strong> '.e($typeNames).'</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected function formatPrescriptionLensSummary(): string
    {
        $m = $this->optical_meta ?? [];
        $base = (string) ($m['lens_name'] ?? '—');
        $extras = implode(', ', $this->getExtraCustomizationNamesList());

        return $extras !== '' ? $base.' — '.$extras : $base;
    }

    /**
     * @return array{label: string, body: string}
     */
    protected function formatRxEyeLine(string $label, array $eye, bool $includeAdd): array
    {
        $parts = [
            'Sph '.e((string) ($eye['sph'] ?? '—')),
            'Cyl '.e((string) ($eye['cyl'] ?? '—')),
            'Axis '.e((string) ($eye['axis'] ?? '—')),
        ];
        if ($includeAdd) {
            $parts[] = 'Add '.e((string) ($eye['add'] ?? '—'));
        }

        return [
            'label' => $label.':',
            'body' => implode(', ', $parts),
        ];
    }
}
