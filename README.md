<div align="center">

<img src="public/logo.svg" alt="Handloft logo" width="72" height="72">

# Handloft

**Work handed off, never dropped.**

An open-source team, project and task tracker built on Laravel 13 and Livewire 4. Every handoff is recorded, time is logged daily, reports are one click away, and notifications reach each person the moment work lands on their desk.

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](#license)

</div>

---

## Contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Step-by-step installation](#step-by-step-installation)
- [Running the app](#running-the-app)
- [First login](#first-login)
- [Configuration](#configuration)
- [Live notifications (Reverb)](#live-notifications-reverb)
- [Roles and permissions](#roles-and-permissions)
- [Deploying to production](#deploying-to-production)
- [Updating an installation](#updating-an-installation)
- [Developer guide](#developer-guide)
- [Testing and code style](#testing-and-code-style)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)
- [Credits](#credits)

---

## Features

| Area | What you get |
| --- | --- |
| **Accounts and roles** | Sign-in by email or User ID, with no public registration. Three roles (Super Admin, Manager, Team Member). What Managers and Team Members can do is adjustable in **Settings → Roles & Permissions**. Everyone edits their own profile, password and photo. |
| **Projects** | Status, start date and deadline, a Coordinator, and progress rolled up from top-level tasks. One form for creating and editing. |
| **Tasks** | Each task gets a key such as `HL-42`. Kanban and list views, subtasks, categories, attachments, comments and stars. A QA workflow runs *To Do → In Progress → QA Testing → Rejected / Ready to Deploy → Done*. Reassignments are kept as history. |
| **Daily time logs** | Hours and minutes per person, per task, per day. A task can have many entries per person. |
| **Reports** | Daily, weekly, monthly, yearly or custom periods across tasks, projects and people, with trends, per-person reports (hours, time by task, completed tasks) and a daily hours grid. Export to CSV and Excel, or print a ready-made A4 report. Team members get their own report only. Super Admins can correct logged hours, with a reason, from a person's report. |
| **Dashboard** | What's assigned to you, what's due or overdue, a "needs your attention" queue and your hours against your weekly target. Report holders also see team throughput and top contributors. |
| **For You and Starred** | Your personal queue grouped by stage (why each task is waiting on you, priority, due date), and the tasks you've starred. |
| **Calendar** | Month, week and day views that follow the configured work week and shade days off. |
| **Notifications** | In-app bell and page plus email, pushed **live** over WebSockets (Laravel Reverb). It can be switched off in Settings; the bell then checks on a timer. |
| **Settings** | Per-person look (Liquid glass or Static, six theme colours, light/dark/system), work schedule (days off, week start, daily hours target), roles and permissions, live notifications, the post-login loading screen (four theme-coloured animations), an SMTP override with a test email, and email templates (three layouts, colour presets, live preview). |
| **UI** | Mobile-first, with frosted glass or solid panels, dark mode, a notched shortcut bar, Dynamic Island–style toasts, searchable pickers that scale to thousands of records, and in-app 403 and 404 pages. |

## Tech stack

- **Backend:** Laravel 13 (PHP 8.3+), Livewire 4 single-file components
- **Frontend:** Tailwind CSS 4, Alpine.js (bundled with Livewire), Vite, Quill (rich text)
- **Real time:** Laravel Reverb (WebSockets) with Laravel Echo and pusher-js
- **Database:** MySQL 8 / MariaDB 10.6+ (the test suite uses in-memory SQLite)
- **Packages:** `spatie/laravel-permission` (roles and permissions), `spatie/laravel-activitylog` (audit trail)
- **Tooling:** PHPUnit 12, Laravel Pint, Laravel Pail, Laravel Boost (AI-assisted development guidelines)

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.3 or newer | Extensions: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `openssl`, `fileinfo`. `pcntl` is optional and enables the log viewer in `composer dev`. |
| Composer | 2.x | |
| Node.js | 20.19+ or 22.12+ | Required by Vite 8. Only needed to build assets. |
| MySQL / MariaDB | 8.0+ / 10.6+ | Any database Laravel supports should work. MySQL is the tested target. |
| A mail server | optional | A Mailtrap sandbox locally, any SMTP provider in production. |

> **Tip:** [Laravel Herd](https://herd.laravel.com) (macOS/Windows) and [Valet](https://laravel.com/docs/valet) provide PHP, a local domain and HTTPS in one install. Sail/Docker works too.

## Quick start

```bash
git clone https://github.com/nurulanam/Handloft.git handloft
cd handloft

# 1. Create an empty MySQL database first (default name: handloft),
#    then set DB_* in .env if your credentials differ from root/no password.

composer setup     # install deps, create .env + key, migrate & seed, link storage, build assets
composer dev       # start the app server, queue listener, logs, Vite and Reverb
```

Open **http://localhost:8000** and sign in with the [default account](#first-login).

`composer setup` is safe to re-run: it only generates an app key if `.env` doesn't already have one.

## Step-by-step installation

**1. Get the code and dependencies**

```bash
git clone https://github.com/nurulanam/Handloft.git handloft
cd handloft
composer install
npm install
```

**2. Create your environment file**

```bash
cp .env.example .env
php artisan key:generate
```

Set at least the app URL and the database connection:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=handloft
DB_USERNAME=root
DB_PASSWORD=
```

**3. Create the database, then migrate and seed**

```bash
mysql -u root -e "CREATE DATABASE handloft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
```

The seeder creates the permissions, the three roles with their default capabilities, default task categories and the first Super Admin. It's idempotent, and it never overwrites capabilities you've changed in Settings.

**4. Link public storage** (profile photos and attachments):

```bash
php artisan storage:link
```

**5. Configure live notifications (Reverb)**

Generate an app ID, key and secret:

```bash
php -r 'echo "REVERB_APP_ID=".random_int(100000, 999999).PHP_EOL."REVERB_APP_KEY=".bin2hex(random_bytes(10)).PHP_EOL."REVERB_APP_SECRET=".bin2hex(random_bytes(10)).PHP_EOL;'
```

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=123456
REVERB_APP_KEY=your-generated-key
REVERB_APP_SECRET=your-generated-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

Leave `REVERB_TLS_*` empty unless you serve the app over local HTTPS (see [Do I need REVERB_TLS_*?](#do-i-need-reverb_tls_)). Avoid `php artisan reverb:install`: the project ships customised `config/reverb.php` and `resources/js/echo.js`, and the installer can overwrite them.

**6. Build the frontend**

```bash
npm run build      # or `npm run dev` for hot reloading
```

## Running the app

### All-in-one (recommended for development)

```bash
composer dev
```

This starts five processes in one terminal, using Laravel's `artisan dev` runner:

| Process | Command | Purpose |
| --- | --- | --- |
| `server` | `php artisan serve` | The app at http://localhost:8000 |
| `queue` | `php artisan queue:listen` | Database queue (nothing requires it today; see [Queues](#queues)) |
| `logs` | `php artisan pail` | Live log tail (needs `pcntl`) |
| `vite` | `npm run dev` | Asset hot reloading |
| `reverb` | `php artisan reverb:start` | WebSocket server for live notifications |

### With Herd or Valet

Herd and Valet serve PHP for you, so run only Vite and Reverb:

```bash
npm run dev
php artisan reverb:start
```

Set `APP_URL` to your site (for example `https://handloft.test`). For an HTTPS site, Reverb has to serve `wss://` too; see [Local HTTPS](#local-https-valet--herd).

## First login

| Field | Value |
| --- | --- |
| User ID / Email | `admin` / `admin@handloft.test` |
| Password | `password` |

Then:

1. **Change the password** under the avatar menu → **My profile** → *Change password*.
2. **Add your team** under **Manage → Team → Add member**. There's no public sign-up.
3. **Review Settings:** Work Schedule, Roles & Permissions, Live Notifications (press *Test connection*), and **Email / SMTP** (send a test).
4. **Create a project**, add tasks, and start handing work off.

> ⚠️ **Change or remove the default account before deploying.** Its credentials are public in this repository.

## Configuration

Infrastructure comes from `.env`. Business settings are edited in the app under **Settings** (Super Admins), stored in the `app_settings` table and applied at runtime, with no redeploy or rebuild.

| Settings tab | Who | Stored | Notes |
| --- | --- | --- | --- |
| **Look** | Everyone | Each person's browser (`localStorage`) | Liquid glass / Static, theme colour, light / dark / system. Defaults: Liquid glass, Forest, System. |
| **Work Schedule** | Super Admin | Database | Days off, first day of the week, daily hours target. Drives reports, the calendar, the dashboard and "this week". |
| **Roles & Permissions** | Super Admin | Database (Spatie) | See [Roles and permissions](#roles-and-permissions). |
| **Live Notifications** | Super Admin | Database (secret encrypted) | See [Live notifications](#live-notifications-reverb). |
| **Loading Screen** | Super Admin | Database | Shown once after sign-in: Jumping Boxes, Equalizer, Handoff or Spinner, drawn in each viewer's theme colours, with duration, transparency and blur. |
| **Email / SMTP** | Super Admin | Database (password encrypted) | Overrides `.env` mail settings when set. |
| **Email Template** | Super Admin | Database | Layout (Modern, Classic, Minimal), brand and button colours with readable presets, footer note, and a live preview. See [Email templates](#email-templates). |

### Application

| Key | Purpose |
| --- | --- |
| `APP_NAME` | Display name. Defaults to `Handloft`. |
| `APP_URL` | The full public URL, used in email links and asset URLs. |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` in production. |
| `APP_KEY` | Encrypts sessions **and the secrets saved in Settings** (SMTP password, Reverb secret). Rotating it makes those unreadable, so re-enter them afterwards. |

### Mail

1. **`.env`** (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_*`) is the default mailer.
2. **Settings → Email / SMTP** is an override stored in the database, encrypted, that takes effect immediately and wins over `.env`.

With `MAIL_MAILER=log` (the default), emails are written to `storage/logs` instead of being sent. A failed email is logged and never breaks the action that triggered it.

### Email templates

Every notification email shares one template, set under **Settings → Email Template**:

- **Layout:** *Modern* (logo header and a rounded card with a brand-coloured top edge), *Classic* (the familiar centred card) or *Minimal* (plain white and left-aligned, like a personal email).
- **Colours:** ready-made pairs (Forest, Lime, Ocean, Indigo, Plum, Ember, Graphite), each with a button text colour that passes WCAG AA contrast, or your own. The page shows the contrast ratio and suggests white or dark text when it's hard to read.
- **Footer note:** an optional line above the copyright.
- **Preview:** a live preview of the real rendered email, plus *Send test* to your inbox.

The Modern layout's logo is loaded from `APP_URL/apple-touch-icon.png`, so `APP_URL` must be the public address. Templates live in `resources/views/vendor/mail`. The look is applied in `vendor/mail/html/message.blade.php` from `App\Support\MailTheme`.

### Files

Uploads go to the `public` disk under `storage/app/public`, served through `public/storage`. Profile photos are cropped in the browser and saved as 512×512 JPEGs. Any Laravel filesystem driver (for example S3) can be configured in `config/filesystems.php`.

### Queues

Notifications are sent **after the HTTP response** (`App\Support\DeferredNotification`) and broadcasts go out synchronously, so Handloft runs with **no queue worker**. `QUEUE_CONNECTION=database` is configured for jobs you add later.

### Sessions and cache

Both use the database by default (`SESSION_DRIVER=database`, `CACHE_STORE=database`). Redis works too: set the drivers and `REDIS_*`.

## Live notifications (Reverb)

Each notification is written to the database, pushed to the recipient's private channel `App.Models.User.{id}`, then emailed.

- **No worker needed.** The push is synchronous.
- **Failures never block.** If Reverb is down, the failure is logged and the database record and email still go out (`App\Notifications\Channels\SafeBroadcastChannel`).
- **Fallback.** While live push is off or the socket is down, the bell checks for new notifications every 15 s – 2 min (configurable), in visible tabs only. After a reconnect it catches up once.

### Two places to configure it

1. **`.env`** is the default (below).
2. **Settings → Live Notifications** can switch live push on or off, set the fallback interval, and override the Reverb app and addresses. The secret is stored encrypted and never sent to the browser. *Test connection* checks the server with a 5-second timeout without saving anything, and the page shows whether the current browser is connected. With nothing saved there, `.env` decides: live push is on when `BROADCAST_CONNECTION=reverb`.

Browsers get their connection details from the page at runtime (`App\Support\LiveUpdates::clientConfig()`), so changing them needs **no asset rebuild**. Despite the `VITE_` prefix, those values are no longer compiled into the bundle.

### Environment keys

| Key | Purpose |
| --- | --- |
| `BROADCAST_CONNECTION` | `reverb` to enable live push from `.env`; `null` to disable (Settings can still override). |
| `REVERB_APP_ID` / `_KEY` / `_SECRET` | Credentials shared by the app and the Reverb server. |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | Where **the app server** sends pushes to Reverb. |
| `VITE_REVERB_HOST` / `_PORT` / `_SCHEME` | Where **browsers** open the WebSocket. Blank host = the site's own host; blank scheme = match the page. |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | The interface and port the Reverb server binds to (default `0.0.0.0:8080`). Use `127.0.0.1` behind a proxy. |
| `REVERB_ALLOWED_ORIGINS` | Sites allowed to open a WebSocket, comma separated. Default `*`; **restrict it in production**. |
| `REVERB_TLS_CERT` / `REVERB_TLS_KEY` | Only when Reverb itself serves `wss://` (local HTTPS). |
| `REVERB_TLS_CA` | Only when the app posts to Reverb over `https` with a self-signed or local CA. |

### Do I need `REVERB_TLS_*`?

| Setup | `REVERB_TLS_CERT` / `_KEY` | `REVERB_TLS_CA` |
| --- | --- | --- |
| Local, plain `http://localhost:8000` | No | No |
| Local HTTPS with Valet / Herd (`https://handloft.test`) | **Yes**: the page is HTTPS, so the browser must use `wss://`, and Reverb serves it directly | **Yes**: the app talks to Reverb over HTTPS with Valet's self-signed CA |
| **Production** behind Nginx / Caddy / a load balancer | **No**: the web server terminates TLS and proxies to Reverb over plain HTTP | **No** (the app reaches Reverb internally over `http`) |

### Local HTTPS (Valet / Herd)

```bash
valet secure handloft                         # trusts Valet's CA and issues a certificate
```

```dotenv
APP_URL=https://handloft.test
REVERB_HOST="handloft.test"
REVERB_SCHEME=https
REVERB_TLS_CERT=/home/you/.valet/Certificates/handloft.test.crt
REVERB_TLS_KEY=/home/you/.valet/Certificates/handloft.test.key
REVERB_TLS_CA=/home/you/.valet/CA/LaravelValetCASelfSigned.pem
```

On macOS Valet, certificates live under `~/.config/valet/`. Herd keeps them in its own config directory. After changing these, run `php artisan config:clear` and restart Reverb.

## Roles and permissions

There are three roles: **Super Admin** has everything, always. **Manager** and **Team Member** are adjustable under **Settings → Roles & Permissions**; changes apply as soon as they're saved.

| Capability (permission) | Super Admin | Manager (default) | Team Member (default) |
| --- | :---: | :---: | :---: |
| See every task (`view-all-tasks`) | ✅ | ✅ | |
| Create tasks (`create-task`) | ✅ | ✅ | ✅ |
| Reassign tasks (`reassign-task`) | ✅ | | |
| Create and edit projects (`manage-projects`) | ✅ | ✅ | |
| Manage team members (`manage-users`) | ✅ | | |
| See the whole team's reports (`view-reports`) | ✅ | ✅ | own report only |
| Export reports (`export-data`, needs reports) | ✅ | ✅ | |
| Correct logged hours (`edit-completed-hours`, needs reports) | ✅ | | |
| Change settings and permissions (`manage-settings`) | ✅ | locked | locked |

- **Visibility:** without *See every task*, a person sees tasks they reported, are assigned to, review as QA, or handed to someone. This is defined once in `Task::visibleTo()` and mirrored by `TaskPolicy::view()`.
- **Super Admin protection:** even with *Manage team members*, nobody but a Super Admin can edit a Super Admin or grant that role (`UserPolicy`).
- **Default admin:** the seeder creates `admin@handloft.test`. On installs from before the rename, it moves the old `admin@am2amdesk.test` account over instead of adding a second one.
- **Seeding:** defaults live in `App\Support\PermissionCatalog::DEFAULTS` and are applied only when a role is first created, so re-running the seeder never undoes your choices. A permission added later must be switched on in Settings for existing roles.
- **Cache:** Spatie caches permissions. Settings clears the cache on save. After editing roles by hand, run `php artisan permission:cache-reset`.

## Deploying to production

### 1. Server

PHP 8.3+ with the extensions above, PHP-FPM, a web server (Nginx, Apache or Caddy) whose document root is **`public/`**, MySQL/MariaDB, a process manager (Supervisor or systemd) for Reverb, and Node.js for the build step (or build assets in CI and ship `public/build`).

### 2. Environment

The recommended layout: the app reaches Reverb **internally** over plain HTTP, and browsers reach it **through your domain** over `wss://`, proxied by the web server.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://handloft.example.com

LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp            # or set it in Settings → Email / SMTP

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=…             # generate fresh values for production
REVERB_APP_KEY=…
REVERB_APP_SECRET=…

# App → Reverb (internal)
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb binds locally only; the web server proxies to it
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=handloft.example.com

# Browsers → Reverb (public, through the proxy)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=handloft.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

# Leave empty in production (TLS is terminated by the web server)
REVERB_TLS_CERT=
REVERB_TLS_KEY=
REVERB_TLS_CA=
```

The same values can instead be entered in **Settings → Live Notifications → Custom** after the first deploy.

### 3. Release steps

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force          # first deploy only (permissions, roles, first admin)
php artisan storage:link
php artisan optimize                 # caches config, routes, views and events
php artisan reverb:restart           # pick up new code and credentials
```

`storage/` and `bootstrap/cache/` must be writable by the web server user. Settings saved in the app keep working with cached config, because they're applied at runtime.

### 4. Keep Reverb running

Supervisor example:

```ini
[program:handloft-reverb]
command=php /var/www/handloft/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/handloft/storage/logs/reverb.log
stopwaitsecs=10
minfds=10000
```

`minfds` (or `LimitNOFILE` under systemd) raises the open-files limit; each connection uses one. Reverb reads its app credentials **at start**, so restart it after changing them in `.env` or Settings (`php artisan reverb:restart`, or `supervisorctl restart handloft-reverb`).

### 5. Proxy WebSockets through your HTTPS site

Nginx, inside the `server { … }` block for your domain:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 120s;
    proxy_pass http://127.0.0.1:8080;
}
```

Only `/app` (the WebSocket) needs to be public. The app sends its pushes to `127.0.0.1:8080` directly, so the `/apps` HTTP API doesn't have to be exposed. Caddy: `reverse_proxy /app* 127.0.0.1:8080`.

### 6. Verify

1. Sign in as a Super Admin → **Settings → Live Notifications** → *Test connection* should report "Reverb answered in … ms", and the page should say **This browser: connected**.
2. Assign a task to a second account in another browser. The bell should update without a refresh.
3. **Email / SMTP** → *Send test*.

### 7. Production checklist

- [ ] Default admin password changed, or the account replaced
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS everywhere, `SESSION_SECURE_COOKIE=true`
- [ ] Fresh `REVERB_APP_*` values, `REVERB_ALLOWED_ORIGINS` set to your domain, Reverb bound to `127.0.0.1`
- [ ] Reverb supervised, and restarted on every deploy
- [ ] A real mailer configured and tested
- [ ] Roles & Permissions reviewed for your organisation
- [ ] Database and `storage/app/public` backups scheduled

There are no scheduled commands, so no cron entry for `schedule:run` is needed.

## Updating an installation

```bash
git pull
composer install                 # --no-dev --optimize-autoloader in production
npm ci && npm run build
php artisan migrate              # --force in production
php artisan optimize:clear       # then `php artisan optimize` in production
php artisan reverb:restart
```

## Developer guide

### Project layout

```
app/
├── Enums/                 TaskStatus, ProjectStatus, Role, TaskPriority, …
├── Http/Controllers/      ReportExportController (CSV / Excel / print)
├── Livewire/Concerns/     Shared component traits: TogglesStars, SearchesPickerOptions
├── Models/                Task, Project, TaskTimeLog, TaskAssignment, User, AppSetting, …
├── Notifications/         Task*/Project* notifications (Channels/SafeBroadcastChannel, Concerns/BroadcastsInstantly)
├── Policies/              TaskPolicy, ProjectPolicy, UserPolicy, …
├── Reports/               ReportPeriod, ReportBuilder (the single source for every report), ReportExport, XlsxWriter
├── Services/              TaskWorkflowService: create, reassign, submit for QA, done, time logs
└── Support/               LiveUpdates, MailSettings, PermissionCatalog, Theme, WorkSchedule, Picker, DeferredNotification, …

resources/
├── views/
│   ├── layouts/           app (signed-in shell), guest (sign-in), partials/appearance (per-browser look)
│   ├── components/        Livewire pages (⚡*.blade.php) and Blade components
│   │   └── form/          Form kit: header, card, field, picker, date, dropzone, file-chip, submit, actions
│   └── reports/print      Standalone print view (always light)
├── js/app.js, echo.js     Alpine helpers (pickers, editor, cropper), live notifications
└── css/app.css            Theme tokens, dark palette, glass/static styles, form kit classes

routes/web.php             Routes (Livewire full-page components) plus the in-app 404 fallback
routes/channels.php        Private notification channel authorisation
```

### Conventions

- **Pages are Livewire single-file components.** `Route::livewire('/tasks', 'tasks.index')` maps to `resources/views/components/tasks/⚡index.blade.php`.
- **Task changes go through `TaskWorkflowService`.** It records history and activity and sends notifications. Don't update task status or time logs directly.
- **Reports read from `ReportBuilder` only.** The page, CSV, Excel and print all use it, so they always agree.
- **Visibility is defined once** in `Task::visibleTo()` / `TaskPolicy::view()`.
- **Authorise on the server.** Hiding a button is never enough: check the policy or permission in the Livewire action too (`Gate::authorize(...)`, `abort_unless(...)`).
- **Toasts:** `$this->dispatch('notify', message: '…', type: 'success|error|info|star|unstar')`.

### Building forms

Use the form kit instead of hand-rolled markup:

```blade
<x-form.header :back="route('tasks.index')" back-label="Tasks" title="Create a task" subtitle="…">
    <x-slot:actions>
        <a href="…" wire:navigate class="btn-secondary">Cancel</a>
        <x-form.submit form="my-form">Save</x-form.submit>
    </x-slot:actions>
</x-form.header>

<x-form.card title="People" description="…" :icon="$svgPaths">
    <x-form.field label="Assignee" error="assigned_to" required>
        <x-form.picker model="assigned_to" :value="$assigned_to" source="people" :label="$assigneeName" avatar />
    </x-form.field>
</x-form.card>

<x-form.actions :cancel="route('tasks.index')">Save</x-form.actions>   {{-- phone action bar --}}
```

- Inputs use `field-input` (add `field-input-error` on error); buttons use `btn-primary` / `btn-secondary`; menus use `menu-panel` / `menu-item`.
- **Pickers for people, projects and tasks** must use `source="people|projects|tasks"` (server-side search, 20 results at a time). The component needs `use SearchesPickerOptions;`. Pass short fixed lists with `:options="[...]"` instead.
- Rich text: wrap Quill in `<div class="rich-editor">` and give the editor `data-placeholder`.

### Styling, themes and dark mode

- **Colours:** use `bg-surface` (never `bg-white`) for cards, inputs and menus, and the `zinc` scale for text and borders. Dark mode flips `surface`, `zinc` and the colour tints automatically. Use `ink-*` only for things that must stay dark in both modes (tooltips, toasts).
- **Theme colour:** `bg-brand` / `text-brand` / `brand-lime` follow the person's theme. Don't hardcode the green.
- **Glass vs Static:** cards with `rounded-xl|2xl border border-zinc-200 bg-surface` are frosted automatically in Liquid glass. Use the `glass:` variant (`lg:glass:bg-surface/60`) for glass-only styles.
- **Look is per browser:** `window.appearance.get()` / `.set('style'|'theme'|'mode', value)`. The server never renders the theme.

### Adding things

| To add… | Do this |
| --- | --- |
| A permission | Add it to `PermissionCatalog::ALL`, to a group in `GROUPS` (label and description) so it shows in Settings, optionally to `DEFAULTS` and `REQUIRES`, then enforce it in a policy or action. Write a test. |
| A setting | Add a migration column to `app_settings`, the field to `AppSetting` (`#[Fillable]`, casts; `encrypted` for secrets), and a tab or section in `⚡settings.blade.php`. Guard the save with `canManage()`, and load admin-only values only for admins. |
| A notification | Use the `BroadcastsInstantly` trait (database → live push → email) and send it with `DeferredNotification::send()`. |
| An email layout | Add it to `MailTheme::LAYOUTS` and give it a header and `<style>` overrides in `vendor/mail/html/message.blade.php`. |
| A loading animation | Add markup to `components/loading-animation.blade.php`, CSS (using the `--loader-1` to `--loader-5` theme colours) to `layouts/app.blade.php`, and the key to the Settings list and validation. |
| A report figure | Add it to `ReportBuilder`, then to the page and to `ReportExport::tables()` so CSV, Excel and print stay in step. |

### Pitfalls we've hit

- **`wire:navigate` replaces the `<html>` attributes** on every page change. Anything set on `<html>` (dark mode, theme, sidebar state) must be re-applied in `livewire:navigating`'s `onSwap` (see `layouts/partials/appearance.blade.php`).
- **Head `<style>`/`<script>` tags aren't swapped** on navigation. Put per-page values in `<meta>` tags or the body.
- **Back/forward restores cached HTML.** JS widgets must clean up leftover markup before mounting (see `mountQuill`).
- **Static state in long-running processes** (tests, Reverb, Octane): reset it on boot (`LiveUpdates::reset()`).
- **Never read `env()` outside `config/`**: it returns `null` once config is cached.
- **No Blade comments (`{{-- --}}`) inside `@php` blocks.** Use `//`.
- **Don't render emails during a Livewire render.** Livewire adds morph markers (HTML comments) to Blade output while a component renders, which breaks Markdown mail. The email preview is served by its own route (`MailPreviewController`) for this reason.

## Testing and code style

```bash
composer test                       # or: php artisan test --compact
php artisan test --filter=Reports   # a single file or test
vendor/bin/pint                     # format (Laravel preset)
vendor/bin/pint --test              # check formatting in CI
```

The suite (235+ tests) runs on in-memory SQLite with broadcasting set to `null`, so it needs no database server, Reverb or mail setup. Tests that need live push switch it on with a test Reverb app (see `LiveNotificationTest`).

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Pages look unstyled, or a Vite manifest error | Run `npm run build`, or keep `npm run dev` running. |
| Photos or attachments return 404 | Run `php artisan storage:link`. |
| Notifications only appear after a delay or a refresh | Live push is off or unreachable. **Settings → Live Notifications** → *Test connection* and check "This browser". Make sure Reverb is running, `/app` is proxied, and on HTTPS the browser uses `wss` (`VITE_REVERB_SCHEME=https`, port 443). See the browser console and `storage/logs/laravel.log`. |
| The browser can't connect but *Test connection* passes | The browser address is wrong or blocked: check `VITE_REVERB_*` (or the Settings browser address), the proxy, and `REVERB_ALLOWED_ORIGINS`. |
| *Test connection* fails with an SSL error locally | Set `REVERB_TLS_CA` to your local CA (Valet / Herd). |
| New Reverb credentials don't work | Restart Reverb: it reads credentials at start. |
| Emails aren't sent | Check `MAIL_MAILER` (`log` writes to the log file) or **Settings → Email / SMTP** → *Send test*. |
| A saved SMTP password or Reverb secret stopped working | `APP_KEY` changed. Re-enter it in Settings. |
| "This page has expired" (419) | The session expired. Sign in again, or raise `SESSION_LIFETIME`. |
| A permission change doesn't apply | Saved from Settings, it applies immediately. After manual changes, run `php artisan permission:cache-reset`. |
| A deactivated user is still signed in | They're signed out on their next request (`EnsureUserIsActive`). |

## Contributing

Contributions are welcome: bug reports, fixes, features, docs and design.

1. **Open an issue first** for anything larger than a small fix.
2. **Fork, then branch** from `main`, for example `feature/project-templates` or `fix/calendar-timezone`.
3. **Follow the [developer guide](#developer-guide)** and the surrounding code. Keep changes focused.
4. **Add or update tests** in `tests/Feature` for every behaviour change.
5. **Run the checks** before pushing:
   ```bash
   vendor/bin/pint && composer test && npm run build
   ```
6. **Open a pull request** that explains what changed and why, with screenshots at phone and desktop widths, in light and dark mode, for UI changes.

This repository includes [Laravel Boost](https://github.com/laravel/boost) guidelines (`AGENTS.md`, `CLAUDE.md`, `boost.json`) for AI coding assistants. They're optional for human contributors.

## Security

Please **don't open a public issue for security vulnerabilities.** Report them privately through [GitHub Security Advisories](https://github.com/nurulanam/Handloft/security/advisories/new) so a fix can ship before disclosure. Include steps to reproduce and the affected version or commit.

## License

Handloft is open-source software licensed under the **MIT License**. You're free to use, modify and distribute it, including commercially, as long as the copyright and licence notice are kept. See [`LICENSE`](LICENSE) for the full text.

## Credits

- [Laravel](https://laravel.com), [Livewire](https://livewire.laravel.com), [Alpine.js](https://alpinejs.dev) and [Tailwind CSS](https://tailwindcss.com)
- [Laravel Reverb](https://reverb.laravel.com) for real-time delivery
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) and [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)
- [Lucide](https://lucide.dev) icons (ISC License) and the [Quill](https://quilljs.com) editor
