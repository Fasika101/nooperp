<?php

namespace App\Console\Commands;

use App\Services\TelegramCustomerSyncService;
use App\Services\TelegramImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportTelegramExportCommand extends Command
{
    protected $signature = 'telegram:import
                            {path? : Path to JSON export (default: storage/app/telegram_export.json)}
                            {--append : Merge with existing data instead of replacing all}
                            {--no-sync-customers : Do not upsert customers from Telegram user chats}';

    protected $description = 'Import Telegram chat export JSON (from Telethon script) into the CRM tables';

    public function handle(TelegramImportService $import, TelegramCustomerSyncService $customerSync): int
    {
        $path = $this->argument('path')
            ?: storage_path('app/telegram_export.json');

        if (! File::isFile($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        $json = File::get($path);
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            $this->error('Invalid JSON in '.$path);

            return self::FAILURE;
        }

        $replaceAll = ! $this->option('append');

        $this->info($replaceAll ? 'Replacing all Telegram CRM data…' : 'Merging into existing data…');

        $result = $import->importFromArray($payload, $replaceAll);

        $this->info('Imported chats: '.$result['chats'].', messages: '.$result['messages']);

        if (! $this->option('no-sync-customers')) {
            $sync = $customerSync->syncUserChatsToCustomers();
            $this->info(sprintf(
                'Customers synced — created: %d, updated: %d, skipped (no name): %d',
                $sync['created'],
                $sync['updated'],
                $sync['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
