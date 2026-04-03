<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\TelegramBotChat;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBotCustomerOnboardingService
{
    public const STEP_NAME = 'name';

    public const STEP_PHONE = 'phone';

    public const STEP_EMAIL = 'email';

    public const STEP_ADDRESS = 'address';

    public function __construct(
        protected TelegramBotService $bots,
        protected TelegramCustomerSyncService $phones,
    ) {}

    /**
     * @param  array<string, mixed>  $message  Raw Telegram message payload
     */
    public function handleIncoming(TelegramBotChat $chat, array $message, mixed $textPayload): void
    {
        if (! config('integrations.telegram_customer_onboarding_enabled', true)) {
            $this->legacyWelcomeOnly($chat, $textPayload);

            return;
        }

        if ($chat->type !== 'private') {
            return;
        }

        $text = is_string($textPayload) ? trim($textPayload) : '';
        $command = $this->commandWord($text);

        if ($command === '/cancel') {
            $this->clearRegistration($chat);
            try {
                $this->bots->sendTextToChat($chat, config('integrations.telegram_onboarding_cancelled'), removeKeyboard: true);
            } catch (Throwable $e) {
                Log::warning('Telegram onboarding cancel reply failed: '.$e->getMessage(), ['exception' => $e]);
            }

            return;
        }

        if ($command === '/start' || $command === '/register') {
            $this->beginRegistration($chat, $command === '/start');

            return;
        }

        $state = $this->getRegistrationState($chat);

        if ($command === '/skip' && $state['step'] === self::STEP_EMAIL) {
            $this->advanceToAddressStep($chat, $state['draft'], null);

            return;
        }

        if ($command !== null && $state['step'] !== null) {
            try {
                $this->bots->sendTextToChat($chat, (string) config('integrations.telegram_onboarding_unknown_command'));
            } catch (Throwable $e) {
                Log::warning('Telegram onboarding unknown command reply failed: '.$e->getMessage(), ['exception' => $e]);
            }

            return;
        }

        if ($state['step'] === null) {
            return;
        }

        if ($state['step'] === self::STEP_PHONE && ! empty($message['contact']) && is_array($message['contact'])) {
            $this->acceptPhoneContact($chat, $message, $state['draft']);

            return;
        }

        if ($text === '') {
            try {
                $this->bots->sendTextToChat($chat, config('integrations.telegram_onboarding_need_text'));
            } catch (Throwable $e) {
                Log::warning('Telegram onboarding hint failed: '.$e->getMessage(), ['exception' => $e]);
            }

            return;
        }

        match ($state['step']) {
            self::STEP_NAME => $this->acceptName($chat, $text, $state['draft']),
            self::STEP_PHONE => $this->acceptPhoneTyped($chat, $text, $state['draft']),
            self::STEP_EMAIL => $this->acceptEmail($chat, $text, $state['draft']),
            self::STEP_ADDRESS => $this->acceptAddress($chat, $text, $state['draft']),
            default => null,
        };
    }

    protected function legacyWelcomeOnly(TelegramBotChat $chat, mixed $textPayload): void
    {
        if (! is_string($textPayload) || $textPayload === '') {
            return;
        }
        if (! str_starts_with(trim($textPayload), '/start')) {
            return;
        }
        $msg = config('integrations.telegram_welcome_message');
        if (! is_string($msg) || trim($msg) === '') {
            return;
        }
        try {
            $this->bots->sendTextToChat($chat, $msg);
        } catch (Throwable $e) {
            Log::warning('Telegram bot welcome reply failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    protected function beginRegistration(TelegramBotChat $chat, bool $includeWelcome): void
    {
        $lines = [];
        if ($includeWelcome) {
            $welcome = config('integrations.telegram_welcome_message');
            if (is_string($welcome) && trim($welcome) !== '') {
                $lines[] = trim($welcome);
            }
        }
        $lines[] = trim((string) config('integrations.telegram_onboarding_intro'));
        $lines[] = trim((string) config('integrations.telegram_onboarding_ask_name'));

        $draft = [
            'name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
        ];
        $this->saveRegistrationState($chat, self::STEP_NAME, $draft);

        try {
            $this->bots->sendTextToChat($chat, implode("\n\n", array_filter($lines)), removeKeyboard: true);
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding start failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    protected function acceptName(TelegramBotChat $chat, string $text, array $draft): void
    {
        if (strlen($text) < 2 || strlen($text) > 120) {
            $this->notifyValidation($chat, (string) config('integrations.telegram_onboarding_invalid_name'));

            return;
        }
        $draft['name'] = $text;
        $this->saveRegistrationState($chat, self::STEP_PHONE, $draft);

        $keyboard = [
            'keyboard' => [
                [['text' => (string) config('integrations.telegram_onboarding_share_phone_button'), 'request_contact' => true]],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];

        try {
            $this->bots->sendTextToChat(
                $chat,
                (string) config('integrations.telegram_onboarding_ask_phone'),
                replyMarkup: $keyboard,
            );
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding phone step failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    protected function acceptPhoneContact(TelegramBotChat $chat, array $message, array $draft): void
    {
        $from = $message['from'] ?? [];
        $fromId = (int) ($from['id'] ?? 0);
        $contact = $message['contact'];
        $contactUserId = isset($contact['user_id']) ? (int) $contact['user_id'] : null;
        if ($contactUserId !== null && $contactUserId !== $fromId) {
            $this->notifyValidation($chat, (string) config('integrations.telegram_onboarding_invalid_contact'));

            return;
        }

        $raw = (string) ($contact['phone_number'] ?? '');
        $normalized = $this->phones->normalizePhone($raw);
        if ($normalized === '') {
            $this->notifyValidation($chat, (string) config('integrations.telegram_onboarding_invalid_phone'));

            return;
        }

        $draft['phone'] = $normalized;
        $this->saveRegistrationState($chat, self::STEP_EMAIL, $draft);

        try {
            $this->bots->sendTextToChat($chat, (string) config('integrations.telegram_onboarding_ask_email'), removeKeyboard: true);
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding email prompt failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    protected function acceptPhoneTyped(TelegramBotChat $chat, string $text, array $draft): void
    {
        $normalized = $this->phones->normalizePhone($text);
        $digits = ltrim($normalized, '+');
        if (strlen($digits) < 8) {
            $this->notifyValidation($chat, (string) config('integrations.telegram_onboarding_invalid_phone'));

            return;
        }

        $draft['phone'] = $normalized;
        $this->saveRegistrationState($chat, self::STEP_EMAIL, $draft);

        try {
            $this->bots->sendTextToChat($chat, (string) config('integrations.telegram_onboarding_ask_email'), removeKeyboard: true);
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding email prompt failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    protected function acceptEmail(TelegramBotChat $chat, string $text, array $draft): void
    {
        if ($this->emailStepSkipped($text)) {
            $this->advanceToAddressStep($chat, $draft, null);

            return;
        }

        if (strlen($text) > 190 || ! filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $this->notifyValidation($chat, (string) config('integrations.telegram_onboarding_invalid_email'));

            return;
        }

        $this->advanceToAddressStep($chat, $draft, $text);
    }

    /**
     * @param  array{name: string, phone: string, email: string, address: string}  $draft
     */
    protected function advanceToAddressStep(TelegramBotChat $chat, array $draft, ?string $email): void
    {
        $draft['email'] = $email ?? '';
        $this->saveRegistrationState($chat, self::STEP_ADDRESS, $draft);

        try {
            $this->bots->sendTextToChat($chat, (string) config('integrations.telegram_onboarding_ask_address'));
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding address prompt failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    protected function emailStepSkipped(string $text): bool
    {
        $t = strtolower(trim($text));

        return in_array($t, ['skip', '-', '—', 'no', 'n/a', 'na', 'none'], true);
    }

    protected function acceptAddress(TelegramBotChat $chat, string $text, array $draft): void
    {
        if (strlen($text) < 3 || strlen($text) > 500) {
            $this->notifyValidation($chat, (string) config('integrations.telegram_onboarding_invalid_address'));

            return;
        }

        $draft['address'] = $text;

        $email = trim((string) ($draft['email'] ?? ''));

        $customer = Customer::query()->updateOrCreate(
            ['telegram_bot_chat_id' => $chat->id],
            [
                'name' => $draft['name'],
                'phone' => $draft['phone'],
                'email' => $email !== '' ? $email : null,
                'address' => $draft['address'],
            ]
        );

        $this->finishRegistrationSavingChatDisplayName($chat, $draft['name']);

        $done = (string) config('integrations.telegram_onboarding_complete');
        $done = str_replace(':id', (string) $customer->id, $done);

        try {
            $this->bots->sendTextToChat($chat, $done, removeKeyboard: true);
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding complete message failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * @return array{step: ?string, draft: array{name: string, phone: string, email: string, address: string}}
     */
    protected function getRegistrationState(TelegramBotChat $chat): array
    {
        $meta = $chat->meta ?? [];
        $reg = $meta['registration'] ?? null;
        if (! is_array($reg)) {
            return [
                'step' => null,
                'draft' => ['name' => '', 'phone' => '', 'email' => '', 'address' => ''],
            ];
        }

        $draft = $reg['draft'] ?? [];

        return [
            'step' => isset($reg['step']) && is_string($reg['step']) ? $reg['step'] : null,
            'draft' => [
                'name' => is_string($draft['name'] ?? null) ? $draft['name'] : '',
                'phone' => is_string($draft['phone'] ?? null) ? $draft['phone'] : '',
                'email' => is_string($draft['email'] ?? null) ? $draft['email'] : '',
                'address' => is_string($draft['address'] ?? null) ? $draft['address'] : '',
            ],
        ];
    }

    /**
     * @param  array{name: string, phone: string, email: string, address: string}  $draft
     */
    protected function saveRegistrationState(TelegramBotChat $chat, ?string $step, array $draft): void
    {
        $meta = $chat->meta ?? [];
        if ($step === null) {
            unset($meta['registration']);
        } else {
            $meta['registration'] = [
                'step' => $step,
                'draft' => $draft,
            ];
        }
        $chat->update(['meta' => $meta === [] ? null : $meta]);
    }

    protected function clearRegistration(TelegramBotChat $chat): void
    {
        $meta = $chat->meta ?? [];
        unset($meta['registration']);
        $chat->update(['meta' => $meta === [] ? null : $meta]);
    }

    protected function finishRegistrationSavingChatDisplayName(TelegramBotChat $chat, string $customerName): void
    {
        $meta = $chat->meta ?? [];
        unset($meta['registration']);
        $meta['customer_display_name'] = $customerName;
        $chat->update(['meta' => $meta === [] ? null : $meta]);
    }

    protected function commandWord(string $text): ?string
    {
        if ($text === '' || $text[0] !== '/') {
            return null;
        }
        $parts = preg_split('/\s+/', $text, 2);
        $cmd = strtolower((string) ($parts[0] ?? ''));
        if (str_contains($cmd, '@')) {
            $cmd = explode('@', $cmd, 2)[0] ?? $cmd;
        }

        return $cmd !== '' ? $cmd : null;
    }

    protected function notifyValidation(TelegramBotChat $chat, string $message): void
    {
        if ($message === '') {
            return;
        }
        try {
            $this->bots->sendTextToChat($chat, $message);
        } catch (Throwable $e) {
            Log::warning('Telegram onboarding validation message failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }
}
