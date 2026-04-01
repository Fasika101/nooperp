<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\DataWipeService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class DataWipePage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Data wipe';

    protected static ?string $title = 'Data wipe';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'data_to_wipe' => [],
            'confirm_phrase' => '',
            'acknowledge' => false,
        ]);
    }

    public static function canAccess(): bool
    {
        if (Auth::user()?->hasRole('super_admin') ?? false) {
            return true;
        }

        $permission = static::getPagePermission();
        $user = Filament::auth()?->user();

        return $permission && $user
            ? $user->can($permission)
            : false;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Danger zone')
                    ->description('Permanently delete data from the database. This cannot be undone. Business settings (stored in the settings table) are not affected.')
                    ->schema([
                        CheckboxList::make('data_to_wipe')
                            ->label('Data to remove')
                            ->options([
                                'sales' => 'Sales & revenue — orders, order items, payments, customers, prescriptions',
                                'bank' => 'Bank — transactions and bank accounts',
                                'expenses' => 'Expenses — expense records and expense types',
                                'inventory' => 'Inventory — stock purchases, products, categories (also clears order line items that reference products)',
                                'reference_types' => 'Reference — tax types and payment types',
                                'telegram_crm' => 'Telegram CRM — imported chats and messages (from personal export)',
                                'telegram_bot' => 'Telegram bot — bot inbox chats/messages (webhook integration)',
                                'projects_crm' => 'Projects & CRM — leads, deals, projects, tasks, and workflow stages',
                                'users' => 'Users — all users except the account you are logged in with',
                                'roles_permissions' => 'Roles & permissions — all roles and permissions are removed; a fresh `super_admin` role is recreated and assigned to your current user (run `php artisan shield:generate` if you need full permission sets again)',
                            ])
                            ->required()
                            ->columns(1),
                        Checkbox::make('acknowledge')
                            ->label('I understand this will permanently delete the selected data.')
                            ->accepted()
                            ->validationMessages([
                                'accepted' => 'You must acknowledge the risk before proceeding.',
                            ]),
                        TextInput::make('confirm_phrase')
                            ->label('Confirmation')
                            ->placeholder('Type DELETE in uppercase')
                            ->helperText('Type the word DELETE to enable the wipe button.')
                            ->required()
                            ->maxLength(10),
                    ])
                    ->columns(1),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('wipe')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('wipe')
                ->label('Wipe selected data')
                ->color('danger')
                ->submit('wipe'),
        ];
    }

    public function wipe(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        if (($data['confirm_phrase'] ?? '') !== 'DELETE') {
            throw ValidationException::withMessages([
                'data.confirm_phrase' => 'You must type DELETE exactly to confirm.',
            ]);
        }

        $groups = $data['data_to_wipe'] ?? [];

        if ($groups === []) {
            Notification::make()
                ->warning()
                ->title('Nothing selected')
                ->body('Select at least one data category to wipe.')
                ->send();

            return;
        }

        try {
            app(DataWipeService::class)->wipe($groups, (int) Auth::id());

            Notification::make()
                ->success()
                ->title('Data wiped')
                ->body('The selected data was removed.')
                ->send();

            $this->form->fill([
                'data_to_wipe' => [],
                'confirm_phrase' => '',
                'acknowledge' => false,
            ]);
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Wipe failed')
                ->body('Could not complete the operation: '.$e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
