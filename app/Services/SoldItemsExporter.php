<?php

namespace App\Services;

use App\Models\OrderItem;
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

class SoldItemsExporter
{
    private const LAST_COLUMN = 'L';

    private const COLORS = [
        'company_header' => '1E3A8A',
        'company_info'   => 'EFF6FF',
        'report_banner'  => '0F766E',
        'meta_label'     => 'CCFBF1',
        'summary_header' => '0E7490',
        'summary_body'   => 'CFFAFE',
        'table_header'   => '1D4ED8',
        'row_even'       => 'FFFFFF',
        'row_odd'        => 'F0F9FF',
        'white'          => 'FFFFFF',
        'border'         => 'CBD5E1',
    ];

    /**
     * @param  Collection<int, OrderItem>  $rows
     * @param  array<string, mixed>        $filters
     */
    public function download(Collection $rows, array $filters): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($rows, $filters);
        $filename    = $this->filename($filters);

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  Collection<int, OrderItem>  $rows
     * @param  array<string, mixed>        $filters
     */
    public function buildSpreadsheet(Collection $rows, array $filters): Spreadsheet
    {
        $currency    = Setting::getDefaultCurrency();
        $reportTitle = $this->reportTitle($filters);

        $spreadsheet = new Spreadsheet;
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sold Items');

        $this->setColumnWidths($sheet);

        $row = 1;
        $row = $this->writeCompanyHeader($sheet, $row);
        $row++;
        $row = $this->writeReportBanner($sheet, $row, $reportTitle);
        $row = $this->writeMetaRow($sheet, $row, 'Item type', $this->itemTypeLabel($filters));
        $row = $this->writeMetaRow($sheet, $row, 'Period', $this->dateRangeLabel($filters));
        $row = $this->writeMetaRow($sheet, $row, 'Generated', now()->format('M j, Y g:i A'));
        $row = $this->writeMetaRow($sheet, $row, 'Currency', $currency);
        $row++;
        $row = $this->writeSummarySection($sheet, $row, $rows, $currency);
        $row++;
        $tableHeaderRow = $row;
        $row            = $this->writeTableHeader($sheet, $row);
        $row            = $this->writeTableRows($sheet, $row, $rows, $currency);

        $lastDataRow = max($row - 1, $tableHeaderRow);
        $sheet->freezePane('A'.($tableHeaderRow + 1));
        $sheet->setAutoFilter('A'.$tableHeaderRow.':'.self::LAST_COLUMN.$lastDataRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filename(array $filters): string
    {
        $type = data_get($filters, 'item_type.value') ?? data_get($filters, 'item_type');
        $slug = match ($type) {
            'frames' => 'frames',
            'lenses' => 'lenses',
            default  => 'all-items',
        };

        return 'sold-items-'.$slug.'-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function setColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 12, // Order #
            'B' => 14, // Date
            'C' => 22, // Customer
            'D' => 32, // Item
            'E' => 12, // Type
            'F' => 8,  // Qty
            'G' => 14, // Price
            'H' => 14, // Subtotal
            'I' => 14, // Cost
            'J' => 14, // COGS
            'K' => 14, // Discount
            'L' => 14, // Revenue
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
                    'fill'      => self::COLORS['company_header'],
                    'font'      => ['bold' => true, 'size' => 16, 'color' => self::COLORS['white']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            } else {
                $sheet->getRowDimension($row)->setRowHeight(20);
                $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
                    'fill'      => self::COLORS['company_info'],
                    'font'      => ['size' => 11, 'color' => '1E293B'],
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
            'fill'      => self::COLORS['report_banner'],
            'font'      => ['bold' => true, 'size' => 14, 'color' => self::COLORS['white']],
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
            'fill'      => self::COLORS['meta_label'],
            'font'      => ['bold' => true, 'size' => 11, 'color' => '134E4A'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => true,
        ]);
        $this->styleRange($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row, [
            'fill'      => self::COLORS['white'],
            'font'      => ['size' => 11, 'color' => '1F2937'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrap' => true],
            'borders'   => true,
        ]);

        return $row + 1;
    }

    /**
     * @param  Collection<int, OrderItem>  $rows
     */
    protected function writeSummarySection(Worksheet $sheet, int $row, Collection $rows, string $currency): int
    {
        $totalRevenue  = $rows->sum(fn (OrderItem $item): float => (float) $item->quantity * (float) $item->price);
        $totalCogs     = $rows->sum(fn (OrderItem $item): float => $item->cogs);
        $totalQuantity = $rows->sum(fn (OrderItem $item): int => (int) $item->quantity);

        $this->mergeRow($sheet, $row);
        $sheet->setCellValue('A'.$row, 'SUMMARY');
        $sheet->getRowDimension($row)->setRowHeight(24);
        $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
            'fill'      => self::COLORS['summary_header'],
            'font'      => ['bold' => true, 'size' => 12, 'color' => self::COLORS['white']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        $summaryRows = [
            ['Total line items', (string) $rows->count()],
            ['Total quantity sold', (string) $totalQuantity],
            ['Total revenue (subtotals)', Number::currency($totalRevenue, $currency)],
            ['Total COGS', Number::currency($totalCogs, $currency)],
            ['Gross profit', Number::currency($totalRevenue - $totalCogs, $currency)],
        ];

        foreach ($summaryRows as $summary) {
            $sheet->setCellValue('A'.$row, $summary[0]);
            $this->mergeCells($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row);
            $sheet->setCellValue('B'.$row, $summary[1]);
            $sheet->getRowDimension($row)->setRowHeight(24);

            $this->styleRange($sheet, 'A'.$row, [
                'fill'      => self::COLORS['summary_body'],
                'font'      => ['bold' => true, 'size' => 11, 'color' => '164E63'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => true,
            ]);
            $this->styleRange($sheet, 'B'.$row.':'.self::LAST_COLUMN.$row, [
                'fill'      => self::COLORS['summary_body'],
                'font'      => ['bold' => true, 'size' => 11, 'color' => '0C4A6E'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => true,
            ]);

            $row++;
        }

        return $row;
    }

    protected function writeTableHeader(Worksheet $sheet, int $row): int
    {
        $headers = ['Order #', 'Date', 'Customer', 'Item', 'Type', 'Qty', 'Price', 'Subtotal', 'Cost', 'COGS', 'Discount', 'Revenue'];

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column.$row, $header);
        }

        $sheet->getRowDimension($row)->setRowHeight(26);
        $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
            'fill'      => self::COLORS['table_header'],
            'font'      => ['bold' => true, 'size' => 11, 'color' => self::COLORS['white']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrap' => true],
            'borders'   => true,
        ]);

        return $row + 1;
    }

    /**
     * @param  Collection<int, OrderItem>  $rows
     */
    protected function writeTableRows(Worksheet $sheet, int $row, Collection $rows, string $currency): int
    {
        foreach ($rows as $index => $item) {
            $fill = $index % 2 === 0 ? self::COLORS['row_even'] : self::COLORS['row_odd'];

            $subtotal  = (float) $item->quantity * (float) $item->price;
            $cogs      = $item->cogs;
            $revenue   = $item->order
                ? (float) $item->order->total_amount - (float) $item->order->shipping_amount
                : 0.0;
            $discount  = $item->order ? (float) $item->order->discount_amount : 0.0;
            $type      = $item->hasOpticalDetails() ? 'Lens' : 'Frame';
            $orderDate = $item->created_at?->format('Y-m-d') ?? '';

            $sheet->setCellValue('A'.$row, '#'.($item->order_id ?? ''));
            $sheet->setCellValue('B'.$row, $orderDate);
            $sheet->setCellValue('C'.$row, $item->order?->customer?->name ?? '—');
            $sheet->setCellValue('D'.$row, $item->display_name);
            $sheet->setCellValue('E'.$row, $type);
            $sheet->setCellValue('F'.$row, (int) $item->quantity);
            $sheet->setCellValue('G'.$row, (float) $item->price);
            $sheet->setCellValue('H'.$row, $subtotal);
            $sheet->setCellValue('I'.$row, (float) ($item->unit_cost ?? 0));
            $sheet->setCellValue('J'.$row, $cogs);
            $sheet->setCellValue('K'.$row, $discount);
            $sheet->setCellValue('L'.$row, $revenue);

            $sheet->getRowDimension($row)->setRowHeight(24);

            $this->styleRange($sheet, 'A'.$row.':'.self::LAST_COLUMN.$row, [
                'fill'      => $fill,
                'font'      => ['size' => 11, 'color' => '111827'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrap' => true],
                'borders'   => true,
            ]);

            // Centre: Order #, Date, Type, Qty
            foreach (['A', 'B', 'E', 'F'] as $col) {
                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Right-align numeric money columns
            foreach (['G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }

            // Badge-style teal background for Lens rows in Type column
            if ($type === 'Lens') {
                $this->styleRange($sheet, 'E'.$row, [
                    'fill' => 'CCFBF1',
                    'font' => ['size' => 10, 'bold' => true, 'color' => '0F766E'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            } else {
                $this->styleRange($sheet, 'E'.$row, [
                    'fill' => 'DBEAFE',
                    'font' => ['size' => 10, 'bold' => true, 'color' => '1D4ED8'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }

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
     */
    protected function itemTypeLabel(array $filters): string
    {
        $type = data_get($filters, 'item_type.value') ?? data_get($filters, 'item_type');

        return match ($type) {
            'frames' => 'Frames only',
            'lenses' => 'Lenses only',
            default  => 'All items (frames + lenses)',
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function dateRangeLabel(array $filters): string
    {
        $from  = data_get($filters, 'date_range.from');
        $until = data_get($filters, 'date_range.until');

        if (filled($from) && filled($until)) {
            return Carbon::parse($from)->toFormattedDateString()
                .' – '
                .Carbon::parse($until)->toFormattedDateString();
        }

        return 'All dates';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function reportTitle(array $filters): string
    {
        $type = data_get($filters, 'item_type.value') ?? data_get($filters, 'item_type');

        return match ($type) {
            'frames' => 'SOLD ITEMS REPORT — FRAMES ONLY',
            'lenses' => 'SOLD ITEMS REPORT — LENSES ONLY',
            default  => 'SOLD ITEMS REPORT',
        };
    }
}
