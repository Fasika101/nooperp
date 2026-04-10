<?php

namespace App\Filament\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * Run CSV import in the same request (no queue worker). Modal stays busy until
     * finished; success / partial / failure notifications appear immediately.
     */
    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'max:255'])
                ->example('Classic Gold Frame'),

            ImportColumn::make('category')
                ->label('Category')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('Eyeglasses'),

            ImportColumn::make('price')
                ->rules(['required', 'numeric', 'min:0'])
                ->numeric()
                ->example('450.00'),

            ImportColumn::make('original_price')
                ->label('List / original price')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->numeric()
                ->ignoreBlankState()
                ->example('500.00'),

            ImportColumn::make('cost_price')
                ->label('Cost price')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->numeric()
                ->ignoreBlankState()
                ->example('220.00'),

            ImportColumn::make('brand')
                ->label('Brand')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('Ray-Ban'),

            ImportColumn::make('gender')
                ->label('Gender')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('Unisex'),

            ImportColumn::make('material')
                ->label('Material')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('Metal'),

            ImportColumn::make('shape')
                ->label('Shape')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('Round'),

            ImportColumn::make('lens_width_mm')
                ->label('Lens width (mm)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->numeric()
                ->ignoreBlankState()
                ->example('52'),

            ImportColumn::make('bridge_width_mm')
                ->label('Bridge width (mm)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->numeric()
                ->ignoreBlankState()
                ->example('18'),

            ImportColumn::make('temple_length_mm')
                ->label('Temple length (mm)')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->numeric()
                ->ignoreBlankState()
                ->example('145'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        // Update by name when a product with the same name already exists.
        return Product::query()->where('name', $this->data['name'])->first()
            ?? new Product;
    }

    protected function afterFill(): void
    {
        // Resolve category
        $categoryName = trim((string) ($this->data['category'] ?? ''));
        if ($categoryName !== '') {
            $category = Category::query()->firstOrCreate(['name' => $categoryName]);
            $this->record->category_id = $category->id;
        }

        // Resolve option fields by name (first-or-create)
        foreach ([
            'brand' => ProductOption::TYPE_BRAND,
            'gender' => ProductOption::TYPE_GENDER,
            'material' => ProductOption::TYPE_MATERIAL,
            'shape' => ProductOption::TYPE_SHAPE,
        ] as $column => $type) {
            $name = trim((string) ($this->data[$column] ?? ''));
            if ($name === '') {
                continue;
            }
            $option = ProductOption::query()->firstOrCreate(['type' => $type, 'name' => $name]);
            $this->record->{$column.'_option_id'} = $option->id;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Imported '.Number::format($import->successful_rows).' '.str('product')->plural($import->successful_rows).'.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed.';
        }

        return $body;
    }

    /**
     * Download a CSV template users can fill in Excel and re-upload.
     * No external packages needed — uses PHP's built-in fputcsv.
     */
    public static function downloadTemplateCsv(): StreamedResponse
    {
        $columns = static::getColumns();

        $headers = array_map(fn (ImportColumn $c): string => $c->getName(), $columns);
        $examples = array_map(function (ImportColumn $c): string {
            $ex = $c->getExamples();

            return (string) (is_array($ex) ? (reset($ex) ?: '') : '');
        }, $columns);

        return response()->streamDownload(function () use ($headers, $examples): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM so Excel opens it correctly
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            fputcsv($handle, $examples);
            fclose($handle);
        }, 'product-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
