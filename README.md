# Liba ERP (Laravel + Filament)

## POS → Telegram

Staff can share the current **POS cart** with a customer on **Telegram** and, after **Complete Sale**, the customer can receive the **receipt PDF** on Telegram when they are linked to the bot.

### Requirements

- **Telegram bot** configured (Admin → Integrations or `TELEGRAM_BOT_TOKEN` in `.env`).
- **Customer** must have `telegram_bot_chat_id` set. This happens when the customer uses your bot and completes the onboarding flow (`/start` / registration) that saves their profile to **Customers**.

### Send cart from POS

1. Open **Point of Sale (POS)**.
2. Select a **customer** who is linked to Telegram (onboarding completed).
3. Add products to the **cart** as usual.
4. Click **Send cart to Telegram**.

The bot sends:

- A short intro message.
- For each cart line: **product photo + caption** (name and line total) when a public storage image exists; otherwise a **text** line. Optical/lens-only lines without images are sent as **text**.
- A closing prompt to reply in chat.

If the customer is not linked, the button does not appear. If the bot token is missing or Telegram returns an error, a Filament notification shows the failure reason.

### Receipt after purchase

When you **Complete Sale** for a real customer (not walk-in):

- If the order’s customer has **`telegram_bot_chat_id`** and the bot is configured, the app generates the same **PDF** as **Download PDF** on the receipt (`receipt` view + DomPDF) and sends it via **`sendDocument`** with a short caption.
- **Walk-in** sales (`walkin@pos.local`) do **not** trigger a Telegram receipt.
- Customers **without** a Telegram chat are skipped (no error to the cashier).
- If PDF generation or upload fails, the app logs a warning and tries a **short text** receipt (order id, date, total). Further failures are logged only.

### Code touched

| File | Role |
|------|------|
| `app/Services/PosTelegramService.php` | Cart preview + receipt PDF/text sending via `TelegramBotService`. |
| `app/Filament/Pages/PosPage.php` | `sendCartToTelegram()`; after successful checkout, calls `sendOrderReceiptToCustomer`. |
| `resources/views/filament/pages/pos-page.blade.php` | **Send cart to Telegram** button when cart is non-empty and customer has `telegram_bot_chat_id`. |

### Related features (same project)

- **Telegram bot onboarding**: `app/Services/TelegramBotCustomerOnboardingService.php` — collects name, phone, email (optional), address and links **Customer** ↔ **TelegramBotChat**.
- **Unread badges / poller**: `TelegramUnreadPoller` Livewire component and `TelegramBotChat` unread columns — see navigation badge on **Telegram bot chats**.

### Operational notes

- Telegram **rate limits**: cart lines include small delays between messages; very large carts may still need spacing or batching in the future.
- **Receipt PDF** uses a temp file on disk; it is deleted after send.
- Ensure `storage:link` and product images on the **public** disk are readable by the PHP process sending to Telegram.
