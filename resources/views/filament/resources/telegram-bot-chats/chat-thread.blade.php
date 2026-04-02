@php
    /** @var \Filament\Schemas\Components\View $schemaComponent */
    $livewire = $schemaComponent->getLivewire();
    $chat = $livewire->getOwnerRecord();
    $messages = $chat->messages()->orderBy('sent_at')->orderBy('id')->get();
@endphp

<div
    class="fi-telegram-chat"
    wire:key="telegram-chat-{{ $chat->getKey() }}"
>
    <div
        data-telegram-thread
        wire:poll.12s
        class="fi-telegram-chat__thread"
    >
        @forelse ($messages as $message)
            @php
                $isOut = $message->direction === \App\Models\TelegramBotMessage::DIRECTION_OUTGOING;
                $mediaUrl = $message->hasTelegramAttachment()
                    ? route('telegram.bot.message.attachment', $message)
                    : null;
            @endphp
            <div
                wire:key="msg-{{ $message->id }}"
                class="fi-telegram-chat__row {{ $isOut ? 'fi-telegram-chat__row--out' : 'fi-telegram-chat__row--in' }}"
            >
                <div class="fi-telegram-chat__bubble">
                    <div
                        class="fi-telegram-chat__bubble-inner {{ $isOut ? 'fi-telegram-chat__bubble-inner--out' : 'fi-telegram-chat__bubble-inner--in' }}"
                    >
                        @if ($mediaUrl)
                            <div class="fi-telegram-chat__media">
                                @if ($message->telegramAttachmentDisplayableAsImage())
                                    <img
                                        src="{{ $mediaUrl }}"
                                        alt=""
                                        loading="lazy"
                                    />
                                @else
                                    <a
                                        href="{{ $mediaUrl }}"
                                        class="fi-telegram-chat__media-link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {{ __('Open / download attachment') }}
                                        @if ($message->telegramAttachmentDownloadName())
                                            ({{ $message->telegramAttachmentDownloadName() }})
                                        @endif
                                    </a>
                                @endif
                            </div>
                        @endif
                        @php
                            $redundantLabel = $mediaUrl
                                && $message->telegramAttachmentDisplayableAsImage()
                                && in_array((string) $message->text, ['📷 Photo', '🖼 Sticker'], true);
                        @endphp
                        @if (filled($message->text) && ! $redundantLabel)
                            <p class="fi-telegram-chat__body">{{ $message->text }}</p>
                        @elseif (! $mediaUrl && ! filled($message->text))
                            <p class="fi-telegram-chat__body">—</p>
                        @endif
                    </div>
                    <p
                        class="fi-telegram-chat__meta {{ $isOut ? 'fi-telegram-chat__meta--out' : '' }}"
                    >
                        {{ $message->sent_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                        ·
                        {{ $isOut ? __('You') : __('Customer') }}
                    </p>
                </div>
            </div>
        @empty
            <p class="fi-telegram-chat__empty">
                {{ __('No messages yet. Send something below when the customer has written first.') }}
            </p>
        @endforelse
    </div>

    <div class="fi-telegram-chat__composer">
        <form wire:submit="sendChatMessage">
            <div class="fi-telegram-chat__composer-fields">
                <div>
                    <label class="fi-telegram-chat__label" for="telegram-chat-draft">{{ __('Message') }}</label>
                    <textarea
                        id="telegram-chat-draft"
                        wire:model="messageDraft"
                        rows="3"
                        maxlength="4096"
                        placeholder="{{ __('Type a reply (optional if you attach a file)…') }}"
                        class="fi-telegram-chat__textarea"
                    ></textarea>
                    @error('messageDraft')
                        <p class="fi-telegram-chat__error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="fi-telegram-chat__label" for="telegram-chat-file">{{ __('Attachment') }}</label>
                    <input
                        id="telegram-chat-file"
                        type="file"
                        wire:model="attachment"
                        class="fi-telegram-chat__file"
                    />
                    @error('attachment')
                        <p class="fi-telegram-chat__error">{{ $message }}</p>
                    @enderror
                    @if ($livewire->attachment)
                        <p class="fi-telegram-chat__meta">{{ __('File selected.') }}</p>
                    @endif
                </div>
            </div>
            <div class="fi-telegram-chat__actions">
                <button type="submit" class="fi-telegram-chat__btn">
                    {{ __('Send') }}
                </button>
                @if ($livewire->attachment)
                    <button
                        type="button"
                        class="fi-telegram-chat__btn fi-telegram-chat__btn-secondary"
                        wire:click="$set('attachment', null)"
                    >
                        {{ __('Remove file') }}
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
