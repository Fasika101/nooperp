<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Imports\CustomerImporter;
use App\Filament\Resources\CustomerResource;
use App\Models\CustomerImportAudit;
use Filament\Actions\Action;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Models\Import;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class CustomerImportResults extends Page
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Customer import results';

    protected static ?string $breadcrumb = 'Import results';

    protected string $view = 'filament.resources.customer-resource.pages.customer-import-results';

    #[Url(as: 'import')]
    public ?int $selectedImportId = null;

    public ?Import $selectedImport = null;

    public array $summary = [];

    public Collection $recentImports;

    public Collection $phoneNameReplacedRows;

    public Collection $phoneNameKeptRows;

    public Collection $emailMatchRows;

    public Collection $failedRows;

    public function mount(): void
    {
        $this->loadRecentImports();

        if (blank($this->selectedImportId)) {
            $this->selectedImportId = $this->recentImports->first()['id'] ?? null;
        }

        $this->loadSelectedImport();
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function updatedSelectedImportId(): void
    {
        $this->loadSelectedImport();
    }

    public function selectImport(int $importId): void
    {
        $this->selectedImportId = $importId;
        $this->loadSelectedImport();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('customers')
                ->label('Back to customers')
                ->url(CustomerResource::getUrl('index')),
            Action::make('latest')
                ->label('Latest import')
                ->color('gray')
                ->action(function (): void {
                    $this->selectedImportId = $this->recentImports->first()['id'] ?? null;
                    $this->loadSelectedImport();
                }),
            ImportAction::make('importCustomers')
                ->label('Import customers (CSV)')
                ->importer(CustomerImporter::class),
        ];
    }

    protected function loadRecentImports(): void
    {
        $imports = Import::query()
            ->where('importer', CustomerImporter::class)
            ->latest()
            ->limit(12)
            ->get();

        $summaries = $imports->isEmpty()
            ? collect()
            : CustomerImportAudit::query()
                ->whereIn('import_id', $imports->pluck('id'))
                ->get()
                ->groupBy('import_id')
                ->map(fn (Collection $rows): array => CustomerImportAudit::summarizeForImport((int) $rows->first()->import_id));

        $this->recentImports = $imports->map(function (Import $import) use ($summaries): array {
            $summary = $summaries->get($import->id, [
                'created' => 0,
                'phone_name_replaced' => 0,
                'phone_name_kept' => 0,
                'phone_matches_total' => 0,
                'email_match_updated' => 0,
                'email_match_no_change' => 0,
                'email_matches_total' => 0,
            ]);

            return [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'created_at' => $import->created_at,
                'completed_at' => $import->completed_at,
                'total_rows' => $import->total_rows,
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $import->getFailedRowsCount(),
                ...$summary,
            ];
        });
    }

    protected function loadSelectedImport(): void
    {
        $this->selectedImport = null;
        $this->summary = [];
        $this->phoneNameReplacedRows = collect();
        $this->phoneNameKeptRows = collect();
        $this->emailMatchRows = collect();
        $this->failedRows = collect();

        if (blank($this->selectedImportId)) {
            return;
        }

        $import = Import::query()
            ->where('importer', CustomerImporter::class)
            ->with('failedRows')
            ->find($this->selectedImportId);

        if (! $import) {
            return;
        }

        $this->selectedImport = $import;
        $this->selectedImportId = $import->id;

        $actionSummary = CustomerImportAudit::summarizeForImport($import->id);

        $this->summary = [
            'total_rows' => $import->total_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->getFailedRowsCount(),
            ...$actionSummary,
        ];

        $audits = CustomerImportAudit::query()
            ->where('import_id', $import->id)
            ->latest()
            ->get();

        $this->phoneNameReplacedRows = $audits
            ->where('action', CustomerImportAudit::ACTION_PHONE_NAME_REPLACED)
            ->values();

        $this->phoneNameKeptRows = $audits
            ->where('action', CustomerImportAudit::ACTION_PHONE_NAME_KEPT)
            ->values();

        $this->emailMatchRows = $audits
            ->whereIn('action', [
                CustomerImportAudit::ACTION_EMAIL_MATCH_UPDATED,
                CustomerImportAudit::ACTION_EMAIL_MATCH_NO_CHANGE,
            ])
            ->values();

        $this->failedRows = $import->failedRows
            ->sortByDesc('created_at')
            ->values();
    }
}
