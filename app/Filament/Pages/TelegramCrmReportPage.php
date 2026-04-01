<?php

namespace App\Filament\Pages;

use App\Models\TelegramMessage;
use App\Services\TelegramCrmReportService;
use App\Services\TelegramCustomerSyncService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class TelegramCrmReportPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Telegram CRM report';

    protected static ?string $title = 'Telegram CRM report';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.telegram-crm-report-page';

    /** @var array<string, array{count: int, samples: Collection}> */
    public array $keywordStats = [];

    /** @var Collection<int, object> */
    public $topContacts;

    /** @var Collection<int, TelegramMessage> */
    public $addressHints;

    /** @var Collection<int, object> */
    public Collection $matchedPeople;

    public string $phoneFilter = '';

    public string $addressFilter = '';

    public function mount(TelegramCrmReportService $reports): void
    {
        $this->keywordStats = $reports->keywordCounts(3);
        $this->topContacts = $reports->topIncomingContacts(25);
        $this->addressHints = $reports->addressHintMessages(40);
        $this->matchedPeople = collect();
    }

    public function updatedPhoneFilter(): void
    {
        $this->refreshMatchedPeople();
    }

    public function updatedAddressFilter(): void
    {
        $this->refreshMatchedPeople();
    }

    public function clearContactFilters(): void
    {
        $this->phoneFilter = '';
        $this->addressFilter = '';
        $this->matchedPeople = collect();
    }

    protected function refreshMatchedPeople(): void
    {
        $this->matchedPeople = app(TelegramCrmReportService::class)->findPeopleByPhoneAndAddress(
            $this->phoneFilter !== '' ? $this->phoneFilter : null,
            $this->addressFilter !== '' ? $this->addressFilter : null,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCustomers')
                ->label('Sync to customers')
                ->icon('heroicon-o-user-plus')
                ->action(function () {
                    $sync = app(TelegramCustomerSyncService::class)->syncUserChatsToCustomers();
                    Notification::make()
                        ->title('Customers synced from Telegram')
                        ->body(sprintf(
                            'Created %d, updated %d, skipped (no display name) %d.',
                            $sync['created'],
                            $sync['updated'],
                            $sync['skipped'],
                        ))
                        ->success()
                        ->send();
                }),
        ];
    }
}
