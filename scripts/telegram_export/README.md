# Telegram personal export (Telethon)

This folder contains a **one-time** script to export your **personal** Telegram dialogs from the user API (not the Bot API). The JSON file is then imported into Liba ERP via `php artisan telegram:import`.

## Security

- Never commit **`telegram_session`** or **`telegram_session.session`** — they can access your account.
- Do not commit **`storage/app/telegram_export.json`** if it contains customer data.
- Keep **`api_id`** / **`api_hash`** private.

## Steps

1. Create an application at [my.telegram.org](https://my.telegram.org) and copy **api_id** and **api_hash**.

2. Install Python 3.10+ and dependencies:

   ```bash
   cd scripts/telegram_export
   py -m pip install -r requirements.txt
   ```

3. Set environment variables (PowerShell example):

   ```powershell
   $env:TELEGRAM_API_ID="YOUR_NUMERIC_ID"
   $env:TELEGRAM_API_HASH="YOUR_API_HASH"
   py export.py
   ```

4. On first run, Telethon will ask for your phone number and the login code from Telegram.

5. The script writes **`storage/app/telegram_export.json`** at the project root (two levels up from this folder).

6. Import into Laravel:

   ```bash
   php artisan migrate
   php artisan telegram:import
   ```

   Or merge without wiping existing CRM data:

   ```bash
   php artisan telegram:import --append
   ```

   After import, **customers** are upserted from **1:1 Telegram chats** (name from chat, phone parsed from messages). Skip that step with `--no-sync-customers` if needed.

7. Open the admin panel: **CRM → Telegram chats** and **Telegram CRM report** (use **Sync to customers** on the report page anytime).

## Export size (defaults are small on purpose)

By default the script exports **only the first 10 chats** and **up to 10 messages per chat** (fast for testing).

| Variable | Default | Meaning |
|----------|---------|---------|
| `TELEGRAM_MAX_CHATS` | `10` | Max dialogs **after** filters. Set to **`0`** for **no cap** (export all that passed filters). |
| `TELEGRAM_EXPORT_LIMIT` | `10` | Max messages **per** chat. For a larger export, e.g. **`5000`**. |
| `TELEGRAM_NAME_PREFIX` | *(empty)* | When set (e.g. `cu`): **private chats only**; contact’s name/username **tokens** must start with this prefix (case-insensitive). Groups/channels are excluded. Disable with empty, `none`, `0`, `false`, or `all`. |
| `TELEGRAM_CONTACT_FILTER` | *(empty)* | If set, name/username must also contain this substring (**AND** with the prefix when both are set). |

**Default behavior:** **no name filter** — first **10** dialogs (any type), **10** messages each. Export **everything** (all dialogs, more messages):

```powershell
$env:TELEGRAM_MAX_CHATS="0"
$env:TELEGRAM_EXPORT_LIMIT="5000"
py export.py
```

**Only private chats** where first/last/display/username tokens start with **`cu`:**

```powershell
$env:TELEGRAM_NAME_PREFIX="cu"
$env:TELEGRAM_MAX_CHATS="0"
$env:TELEGRAM_EXPORT_LIMIT="5000"
py export.py
```

**Substring + name prefix together:**

```powershell
$env:TELEGRAM_CONTACT_FILTER="your_brand"
$env:TELEGRAM_NAME_PREFIX="cu"
$env:TELEGRAM_MAX_CHATS="0"
py export.py
```

## Phone numbers

The export includes a **`phone`** field per private chat when Telegram exposes it (often for **contacts**). The script also calls **`GetFullUserRequest`** once per user chat to try to fill it. Per-message **`sender_phone`** is included when the sender `User` object carries a phone. Telegram may omit numbers for privacy; customer sync then falls back to parsing numbers from message text (see Laravel `config/telegram_crm.php`).

## Keywords & address hints

Edit **`config/telegram_crm.php`** in the Laravel app to tune product keywords and address-related substrings used on the report page.





$env:TELEGRAM_API_ID="33963876"
$env:TELEGRAM_API_HASH="6e6cee81001eed66a257a537bbd810aa"
$env:TELEGRAM_NAME_PREFIX="cu"
Remove-Item Env:TELEGRAM_CONTACT_FILTER -ErrorAction SilentlyContinue
$env:TELEGRAM_MAX_CHATS="0"
$env:TELEGRAM_EXPORT_LIMIT="10"
py export.py

0