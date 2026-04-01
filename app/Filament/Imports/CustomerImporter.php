<?php

namespace App\Filament\Imports;

use App\Models\CustomerImportAudit;
use App\Models\Customer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use League\Csv\Bom;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImporter extends Importer
{
    protected static ?string $model = Customer::class;

    protected bool $matchedExistingByPhone = false;

    protected bool $matchedExistingByEmail = false;

    protected ?string $previousName = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'max:255'])
                ->example('Cu Example Trading'),
            ImportColumn::make('phone')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('+251911223344'),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255'])
                ->ignoreBlankState()
                ->example('customer@example.com'),
            ImportColumn::make('address')
                ->rules(['nullable'])
                ->ignoreBlankState()
                ->example('Bole, Addis Ababa'),
            ImportColumn::make('tin')
                ->label('TIN')
                ->rules(['nullable', 'max:255'])
                ->ignoreBlankState()
                ->example('1234567890'),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $this->matchedExistingByPhone = false;
        $this->matchedExistingByEmail = false;
        $this->previousName = null;

        $phone = $this->data['phone'] ?? null;
        $email = $this->data['email'] ?? null;

        if (filled($phone)) {
            $customer = Customer::query()->where('phone', $phone)->first();

            if ($customer) {
                $this->matchedExistingByPhone = true;
                $this->previousName = $customer->name;

                return $customer;
            }
        }

        if (filled($email)) {
            $customer = Customer::query()->where('email', $email)->first();

            if ($customer) {
                $this->matchedExistingByEmail = true;
                $this->previousName = $customer->name;

                return $customer;
            }
        }

        return new Customer();
    }

    public function fillRecord(): void
    {
        if ($this->matchedExistingByPhone && $this->record?->exists) {
            if (array_key_exists('name', $this->data) && filled($this->data['name'])) {
                $this->record->name = $this->data['name'];
            }

            return;
        }

        parent::fillRecord();
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record) {
            return;
        }

        $action = CustomerImportAudit::ACTION_CREATED;
        $note = 'Created a new customer record.';

        if ($this->matchedExistingByPhone) {
            $nameChanged = filled($this->data['name'] ?? null)
                && ($this->previousName !== $record->name);

            $action = $nameChanged
                ? CustomerImportAudit::ACTION_PHONE_NAME_REPLACED
                : CustomerImportAudit::ACTION_PHONE_NAME_KEPT;

            $note = $nameChanged
                ? 'Matched an existing phone number and replaced the customer name.'
                : 'Matched an existing phone number and kept the current customer name.';
        } elseif ($this->matchedExistingByEmail) {
            $changes = array_keys($record->getChanges());
            $meaningfulChanges = array_diff($changes, ['updated_at']);
            $changed = count($meaningfulChanges) > 0;

            $action = $changed
                ? CustomerImportAudit::ACTION_EMAIL_MATCH_UPDATED
                : CustomerImportAudit::ACTION_EMAIL_MATCH_NO_CHANGE;

            $note = $changed
                ? 'Matched an existing email address and updated the customer.'
                : 'Matched an existing email address but nothing changed.';
        }

        CustomerImportAudit::query()->create([
            'import_id' => $this->import->getKey(),
            'customer_id' => $record->getKey(),
            'action' => $action,
            'row_name' => $this->data['name'] ?? null,
            'row_phone' => $this->data['phone'] ?? null,
            'row_email' => $this->data['email'] ?? null,
            'previous_name' => $this->previousName,
            'current_name' => $record->name,
            'note' => $note,
        ]);
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $summary = CustomerImportAudit::summarizeForImport($import->getKey());

        $body = 'Imported ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . '. ';
        $body .= 'Created ' . Number::format($summary['created']) . ', ';
        $body .= 'phone matches ' . Number::format($summary['phone_matches_total']) . ' ';
        $body .= '(renamed ' . Number::format($summary['phone_name_replaced']) . '), ';
        $body .= 'email matches ' . Number::format($summary['email_matches_total']) . '.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        $body .= ' Open Customer import results for details.';

        return $body;
    }

    public static function downloadExampleCsv(): StreamedResponse
    {
        $columns = static::getColumns();
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        $csv->insertOne(array_map(
            fn (ImportColumn $column): string => $column->getExampleHeader(),
            $columns,
        ));

        $columnExamples = array_map(
            fn (ImportColumn $column): array => $column->getExamples(),
            $columns,
        );

        $exampleRowsCount = array_reduce(
            $columnExamples,
            fn (int $count, array $exampleData): int => max($count, count($exampleData)),
            0,
        );

        $exampleRows = [];

        foreach ($columnExamples as $exampleData) {
            for ($i = 0; $i < $exampleRowsCount; $i++) {
                $exampleRows[$i][] = $exampleData[$i] ?? '';
            }
        }

        $csv->insertAll($exampleRows);

        return response()->streamDownload(function () use ($csv): void {
            $csv->setOutputBOM(Bom::Utf8);

            echo $csv->toString();
        }, 'customer-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
