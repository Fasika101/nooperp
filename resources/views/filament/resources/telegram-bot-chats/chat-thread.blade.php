@php
    /** @var \Filament\Schemas\Components\View $schemaComponent */
    $livewire = $schemaComponent->getLivewire();
    $chat = $livewire->getOwnerRecord();
    $messages = $chat->messages()->orderBy('sent_at')->orderBy('id')->get();
@endphp

<div
    class="fi-telegram-chat flex flex-col gap-0 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    wire:key="telegram-chat-{{ $chat->getKey() }}"
>
    <div
        data-telegram-thread
        wire:poll.12s
        class="max-h-[min(28rem,55vh)] min-h-[12rem] space-y-3 overflow-y-auto p-4"
    >
        @forelse ($messages as $message)
            @php
                $isOut = $message->direction === \App\Models\TelegramBotMessage::DIRECTION_OUTGOING;
            @endphp
            <div
                wire:key="msg-{{ $message->id }}"
                class="flex {{ $isOut ? 'justify-end' : 'justify-start' }}"
            >
                <div class="max-w-[min(100%,28rem)]">
                    <div
                        @class([
                            'inline-block rounded-2xl px-3 py-2 text-sm leading-relaxed shadow-sm',
                            'rounded-tr-sm bg-primary-600 text-white dark:bg-primary-500' => $isOut,
                            'rounded-tl-sm bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' => ! $isOut,
                        ])
                    >
                        <p class="whitespace-pre-wrap break-words">{{ $message->text ?: '—' }}</p>
                    </div>
                    <p
                        @class([
                            'mt-1 px-1 text-xs',
                            'text-right text-gray-500 dark:text-gray-400' => $isOut,
                            'text-left text-gray-500 dark:text-gray-400' => ! $isOut,
                        ])
                    >
                        {{ $message->sent_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                        ·
                        {{ $isOut ? __('You') : __('Customer') }}
                    </p>
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('No messages yet. Send something below when the customer has written first.') }}
            </p>
        @endforelse
    </div>

    <div class="border-t border-gray-200 p-3 dark:border-gray-700">
        <form wire:submit="sendChatMessage" class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label class="sr-only" for="telegram-chat-draft">{{ __('Message') }}</label>
                <textarea
                    id="telegram-chat-draft"
                    wire:model="messageDraft"
                    rows="3"
                    maxlength="4096"
                    placeholder="{{ __('Type a reply…') }}"
                    class="fi-input block w-full rounded-lg border-0 bg-white py-2 text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-50 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                ></textarea>
                @error('messageDraft')
                    <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>
            <x-filament::button type="submit" class="w-full shrink-0 sm:w-auto">
                {{ __('Send') }}
            </x-filament::button>
        </form>
    </div>
</div>
