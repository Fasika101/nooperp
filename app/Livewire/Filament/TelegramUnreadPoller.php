<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Models\TelegramBotChat;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class TelegramUnreadPoller extends Component
{
    public ?int $telegramUnreadBaseline = null;

    public function mount(): void
    {
        if (! $this->shouldPoll()) {
            return;
        }

        $this->telegramUnreadBaseline = TelegramBotChat::query()->unreadByStaff()->count();
    }

    public function pollTelegramUnread(): void
    {
        if (! $this->shouldPoll()) {
            return;
        }

        $count = TelegramBotChat::query()->unreadByStaff()->count();

        if ($this->telegramUnreadBaseline !== null && $count > $this->telegramUnreadBaseline) {
            Notification::make()
                ->title('New Telegram message')
                ->body('You have new unread customer chats.')
                ->success()
                ->send();

            $this->js('window.libaPlayTelegramNotifySound && window.libaPlayTelegramNotifySound()');
        }

        $this->telegramUnreadBaseline = $count;
    }

    protected function shouldPoll(): bool
    {
        $user = auth()->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', TelegramBotChat::class);
    }

    public function render(): View
    {
        if (! $this->shouldPoll()) {
            return view('livewire.filament.telegram-unread-poller-disabled');
        }

        return view('livewire.filament.telegram-unread-poller');
    }
}
