<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseExporter
{
    private const LAST_COLUMN = 'H';

    private const COLORS = [
        'company_header' => '1E3A8A',
        'company_info' => 'EFF6FF',
        'report_banner' => '4338CA',
        'meta_label' => 'E0E7FF',
        'summary_header' => 'D97706',
        'summary_body' => 'FEF3C7',
        'table_header' => '047857',
        'row_even' => 'FFFFFF',
        'row_odd' => 'F3F4F6',
        'white' => 'FFFFFF',
        'border' => 'CBD5E1',
    ];

    /**
     * @param  Collection<int, Expense>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function download(Collection $rows, float $total, array $filters): StreamedResponse
    {
        $typeName = $this->resolveExpenseTypeName($filters);
        $spreadsheet = $this->buildSpreadsheet($rows, $total, $filters, $typeName);
        $filename = $this->filename($typeName);

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    /**
     * @param  Collection<int, Expense>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function buildSpreadsheet(Collection $rows, float $total, array $filters, ?string $typeName = null): Spreadsheet
    {
        $currency = Setting::getDefaultCurrency();
        $typeName ??= $this->resolveExpenseTypeName($filters);
        $reportTitle = $this->reportTitle($typeName);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Expenses');

        $this->setColumnWidths($sheet);

        $row = 1;
        $row = $this->writeCompanyHeader($sheet, $row);
        $row++;
        $row = $this->writeReportBanner($sheet, $row, $reportTitle);
        $row = $this->writeMetaRow($sheet, $row, 'Expense category', $this->expenseCategoryLabel($typeName));
        $row = $this->writeMetaRow($sheet, $row, 'Period', $this->dateRangeLabel($filters));
        $row = $this->writeMetaRow($sheet, $row, 'Generated', now()->format('M j, Y g:i A'));
        $row = $this->writeMetaRow($sheet, $row, 'Currency', $currency);
        $row++;
        $row = $this->writeSummarySection($sheet, $row, $total, $rows->count(), $currency);
        $row++;
        $tableHeaderRow = $row;
        $row = $this->writeTableHeader($sheet, $row);
        $row = $this->writeTableRows($sheet, $row, $rows);

        $lastDataRow = max($row - 1, $tableHeaderRow);
        $sheet->freezePane('A'.($tableHeaderRow + 1));
        $sheet->setAutoFilter('A'.$tableHeaderRow.':'.self::LAST_COLUMN.$lastDataRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        return $spreadsheet;
    }

    public function filename(?string $typeName): string
    {
        if (! $typeName) {
            return 'expenses-all-types-'.now()->format('Y-m-d-His').'.xlsx';
        }

        $slug = Str::slug($typeName);

        if (strlen($slug) > 80) {
            $slug = 'selected-types';
        }

        return 'expenses-'.$slug.'-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function setColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 14,
            'B' => 16,
            'C' => 18,
            'D' => 22,
            'E' => 20,
            'F' => 22,
            'G' => 22,
            'H' => 44,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    protected function writeCompanyHeader(Worksheet $sheet, int $row): int
    {
        $lines = [strtoupper(Setting::getBusinessName())];

        if ($address = Setting::getBusinessAddress()) {
            $lines[] = $address;
        }
        if ($phone = Setting::getBusinessPhone()) {
            $lines[] = 'Tel: '.$phone;
        }
        if ($email = Setting::getBusinessEmail()) {
            $lines[] = 'Email: '.$email;
        }
        if ($tin = Setting::getBusinessTin()) {
            $lines[] = 'TIN: '.$tin;
        }

        foreach ($lines as $index => $line) {
            $this->mergeRow($sheet, $row);
            $sheet->setCellValue('A'.$row, $line);

            if ($index === 0) {
                $sheet->getRowDimension($row)->setRowHeight(32);
                $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
                    'fill' => self::COLORS['company_header'],
                    'font' => ['bold' => true, 'size' => 16, 'color' => self::COLORS['white']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            } else {
                $sheet->getRowDimension($row)->setRowHeight(20);
                $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
                    'fill' => self::COLORS['company_info'],
                    'font' => ['size' => 11, 'color' => '1E293B'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }

            $row++;
        }

        return $row;
    }

    protected function writeReportBanner(Worksheet $sheet, int $row, string $title): int
    {
        $this->mergeRow($sheet, $row);
        $sheet->setCellValue('A'.$row, $title);
        $sheet->getRowDimension($row)->setRowHeight(30);
        $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
            'fill' => self::COLORS['report_banner'],
            'font' => ['bold' => true, 'size' => 14, 'color' => self::COLORS['white']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        return $row + 1;
    }

    protected function writeMetaRow(Worksheet $sheet, int $row, string $label, string $value): int
    {
        $sheet->setCellValue('A'.$row, $label);
        $this->mergeCells($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row);
        $sheet->setCellValue('B'.$row, $value);
        $sheet->getRowDimension($row)->setRowHeight(22);

        $this->styleRange($sheet, 'A'.$row, [
            'fill' => self::COLORS['meta_label'],
            'font' => ['bold' => true, 'size' => 11, 'color' => '312E81'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => true,
        ]);
        $this->styleRange($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row, [
            'fill' => self::COLORS['white'],
            'font' => ['size' => 11, 'color' => '1F2937'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrap' => true],
            'borders' => true,
        ]);

        return $row + 1;
    }

    protected function writeSummarySection(Worksheet $sheet, int $row, float $total, int $count, string $currency): int
    {
        $this->mergeRow($sheet, $row);
        $sheet->setCellValue('A'.$row, 'SUMMARY');
        $sheet->getRowDimension($row)->setRowHeight(24);
        $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
            'fill' => self::COLORS['summary_header'],
            'font' => ['bold' => true, 'size' => 12, 'color' => self::COLORS['white']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        $summaryRows = [
            ['Total amount', Number::currency($total, $currency)],
            ['Number of expenses', (string) $count],
        ];

        foreach ($summaryRows as $summary) {
            $sheet->setCellValue('A'.$row, $summary[0]);
            $this->mergeCells($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row);
            $sheet->setCellValue('B'.$row, $summary[1]);
            $sheet->getRowDimension($row)->setRowHeight(24);

            $this->styleRange($sheet, 'A'.$row, [
                'fill' => self::COLORS['summary_body'],
                'font' => ['bold' => true, 'size' => 11, 'color' => '92400E'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => true,
            ]);
            $this->styleRange($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row, [
                'fill' => self::COLORS['summary_body'],
                'font' => ['bold' => true, 'size' => 11, 'color' => '78350F'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => true,
            ]);

            $row++;
        }

        return $row;
    }

    protected function writeTableHeader(Worksheet $sheet, int $row): int
    {
        $headers = ['Date', 'Amount', 'Branch', 'Account', 'Type', 'Employee', 'Vendor', 'Description'];

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column.$row, $header);
        }

        $sheet->getRowDimension($row)->setRowHeight(26);
        $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
            'fill' => self::COLORS['table_header'],
            'font' => ['bold' => true, 'size' => 11, 'color' => self::COLORS['white']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrap' => true],
            'borders' => true,
        ]);

        return $row + 1;
    }

    /**
     * @param  Collection<int, Expense>  $rows
     */
    protected function writeTableRows(Worksheet $sheet, int $row, Collection $rows): int
    {
        foreach ($rows as $index => $expense) {
            $fill = $index % 2 === 0 ? self::COLORS['row_even'] : self::COLORS['row_odd'];
            $description = (string) ($expense->description ?? '');
            $lineCount = max(1, (int) ceil(mb_strlen($description) / 48));

            $sheet->setCellValue('A'.$row, $expense->date?->format('Y-m-d') ?? '');
            $sheet->setCellValue('B'.$row, (float) $expense->amount);
            $sheet->setCellValue('C'.$row, $expense->branch?->name ?? '');
            $sheet->setCellValue('D'.$row, $expense->bankAccount?->name ?? '');
            $sheet->setCellValue('E'.$row, $expense->expenseType?->name ?? '');
            $sheet->setCellValue('F'.$row, $expense->employee?->full_name ?? '');
            $sheet->setCellValue('G'.$row, $expense->vendor ?? '');
            $sheet->setCellValue('H'.$row, $description);

            $sheet->getRowDimension($row)->setRowHeight(max(24, min(72, 18 + ($lineCount * 14))));

            $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
                'fill' => $fill,
                'font' => ['size' => 11, 'color' => '111827'],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrap' => true,
                ],
                'borders' => true,
            ]);

            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('B'.$row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B'.$row)
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

            $row++;
        }

        return $row;
    }

    protected function mergeRow(Worksheet $sheet, int $row): void
    {
        $this->mergeCells($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row);
    }

    protected function mergeCells(Worksheet $sheet, string $range): void
    {
        $sheet->mergeCells($range);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function styleRange(Worksheet $sheet, string $range, array $options): void
    {
        $style = $sheet->getStyle($range);

        if (isset($options['fill'])) {
            $style->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FF'.$options['fill']);
        }

        if (isset($options['font'])) {
            $font = $style->getFont();
            $font->setBold((bool) ($options['font']['bold'] ?? false));
            $font->setSize($options['font']['size'] ?? 11);
            if (isset($options['font']['color'])) {
                $font->getColor()->setARGB('FF'.$options['font']['color']);
            }
        }

        if (isset($options['alignment'])) {
            $alignment = $style->getAlignment();
            if (isset($options['alignment']['horizontal'])) {
                $alignment->setHorizontal($options['alignment']['horizontal']);
            }
            if (isset($options['alignment']['vertical'])) {
                $alignment->setVertical($options['alignment']['vertical']);
            }
            if ($options['alignment']['wrap'] ?? false) {
                $alignment->setWrapText(true);
            }
        }

        if ($options['borders'] ?? false) {
            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $style->getBorders()->getAllBorders()->getColor()->setARGB('FF'.self::COLORS['border']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    public static function filteredExpenseTypeIds(array $filters): array
    {
        $raw = data_get($filters, 'expense_type_id.values')
            ?? data_get($filters, 'expense_type_id.value')
            ?? data_get($filters, 'expense_type_id');

        if (! filled($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($id): int => (int) $id,
            (array) $raw,
        ))));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function filteredExpenseTypeLabel(array $filters): ?string
    {
        $ids = self::filteredExpenseTypeIds($filters);

        if ($ids === []) {
            return null;
        }

        $names = ExpenseType::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return implode(', ', $names);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function resolveExpenseTypeName(array $filters): ?string
    {
        return self::filteredExpenseTypeLabel($filters);
    }

    protected function reportTitle(?string $typeName): string
    {
        if ($typeName) {
            return strtoupper($typeName).' EXPENSE REPORT';
        }

        return 'EXPENSE REPORT — ALL TYPES';
    }

    protected function expenseCategoryLabel(?string $typeName): string
    {
        return $typeName ?? 'All expense types';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function dateRangeLabel(array $filters): string
    {
        $from = data_get($filters, 'date_range.from');
        $until = data_get($filters, 'date_range.until');

        if (filled($from) && filled($until)) {
            return Carbon::parse($from)->toFormattedDateString()
                .' – '
                .Carbon::parse($until)->toFormattedDateString();
        }

        return 'All dates';
    }
}
