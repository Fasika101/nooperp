<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Liba ERP — roles & permissions (Filament Shield)

After migrations, seed Shield permissions and default roles:

```bash
php artisan db:seed --class=RolesSeeder
```

Or from `DatabaseSeeder` (if configured to call `RolesSeeder`).

What it does:

1. Runs `shield:generate` (permissions only) for the `admin` panel — same as creating permissions in the DB without manual SQL import.
2. Creates/updates **`super_admin`** with **all** permissions.
3. Creates/updates **`panel_user`** with a minimal read-oriented set (adjust lists in `RolesSeeder` if needed).
4. Creates/updates **`sales`**, **`inventory`**, **`optical`**, **`finance`** with the policy actions defined in `RolesSeeder`.

Then assign roles to users in the Filament admin or via `php artisan tinker` (`$user->assignRole('super_admin');`).

**Production:** run after `php artisan migrate` when the database is empty or you need to refresh role definitions.

### Welcome page logo

Put your logo in **`public/images/`** using one of these names (checked in order): **`logo.svg`**, **`logo.png`**, or **`logo.webp`**. The home page (`resources/views/welcome.blade.php`) loads it automatically; if none exist, it shows the “Liba Digitals” text fallback. The same page includes **Open Graph / Twitter meta tags** (title, description, `og:image`, dimensions) for link previews. **Telegram** expects a **PNG, JPG, or WebP** share image (not SVG) — put **`logo.png`** (or `.jpg` / `.jpeg` / `.webp`) in **`public/images/`**. **`APP_URL`** must be **`https://...`** in production so image URLs are secure. Set **`APP_URL`** in **`.env`** to your public site URL so preview image URLs are correct in production. See also `public/images/README.txt`.

**Telegram previews:** The `/` route detects Telegram’s user agent and returns a **minimal HTML** view with the same meta tags, so previews work even when the full welcome page embeds a large inline CSS fallback (e.g. before `npm run build`).

### Telegram CRM (personal chat import)

Import **your own** Telegram history for internal CRM browsing and reports (not the Bot API).

1. **Export (outside Laravel):** from `scripts/telegram_export/`, install Python deps and run `export.py` with `TELEGRAM_API_ID` and `TELEGRAM_API_HASH` from [my.telegram.org](https://my.telegram.org). See **`scripts/telegram_export/README.md`**.
2. **Migrate & import:** `php artisan migrate` then `php artisan telegram:import` (reads `storage/app/telegram_export.json` by default).
3. **Admin:** **CRM → Telegram chats** (read-only) and **Telegram CRM report** (keywords, contacts, address hints). Tune lists in **`config/telegram_crm.php`**.
4. **Shield:** after adding new resources, run `php artisan shield:generate --all` (or your usual Shield workflow) and assign permissions to roles as needed.

**Data wipe:** Settings → **Data wipe** can include **Telegram CRM** to clear imported chats/messages.

### Projects & CRM (leads, deals, tasks)

ERP-style **pipeline + delivery** (similar in spirit to ERPGo’s CRM + Project modules):

- **CRM → Lead stages / Deal stages** — configure workflow steps (defaults are seeded).
- **CRM → Leads** — pipeline, owner, optional link to a **Customer**, notes, and **Lead tasks** (checklist-style).
- **CRM → Deals** — value, stages, optional link to lead/customer; **Create linked project** on the deal edit screen spins up a **Project** and attaches you + the deal owner as members.
- **Projects → Projects** — customer, dates, status, **team members** (creator is always kept on the team), **Tasks** with stages, priority, due date, **assignees**.
- **Projects → My projects / My tasks** — scoped to projects you created or joined, and tasks assigned to you or on those projects.

After migrating, seed default stages if needed:

```bash
php artisan db:seed --class=CrmProjectStagesSeeder
```

**Data wipe:** Settings → **Data wipe** includes **Projects & CRM**. Run **`php artisan shield:generate --all`** so roles get permissions for the new resources.

### Telegram bot (live inbox)

Use the **Bot API** for two-way chat in the admin (separate from the personal import above).

1. Create a bot with [@BotFather](https://t.me/BotFather) and copy the **bot token**.
2. **Admin → Settings → Integrations:** paste the token (stored encrypted), optionally set a **webhook secret**, click **Save**, then **Test Telegram bot**.
3. Set **`APP_URL`** in `.env` to your public **https** base URL (Telegram requires HTTPS for webhooks).
4. Register the webhook: `php artisan telegram:bot:set-webhook` (uses `APP_URL` + `/telegram/bot/webhook`). On localhost, expose the app with **ngrok** (or similar) and set `APP_URL` to the HTTPS ngrok URL before running the command.
5. **CRM → Telegram bot chats:** incoming messages appear after users write to the bot; open a chat and use **Send message** in the Messages table to reply.

**Data wipe:** Settings → **Data wipe** can include **Telegram bot** to clear bot inbox data. **Shield:** run `php artisan shield:generate --all` after deploy so roles get the new pages/resources.

### User profile (Filament admin)

Signed-in users can open **Profile** from the account menu (top-right) at **`/admin/profile`**. The profile screen uses the **normal admin layout** (sidebar + top bar). There they can update **name**, **email**, **password** (optional), and upload a **profile photo** (stored under `storage/app/public/avatars`). Changing **password** or **email** requires the **current password**. Ensure **`php artisan storage:link`** has been run so photos are served from **`/storage/...`** (required on new servers / after deploy).#   n o o p e r p  
 