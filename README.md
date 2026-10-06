<div align="center">

<img src="public/logo.svg" alt="Handloft logo" width="72" height="72">

# Handloft

**Work handed off, never dropped.**

An open-source team, project and task tracker built on Laravel 13 and Livewire 4. Every handoff is recorded, time is logged daily, and notifications reach each person the moment work lands on their desk.

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
- [Roles and permissions](#roles-and-permissions)
- [Architecture](#architecture)
- [Testing and code style](#testing-and-code-style)
- [Deploying to production](#deploying-to-production)
- [Updating an installation](#updating-an-installation)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)
- [Credits](#credits)

---

## Features

| Area | What you get |
| --- | --- |
| **Accounts and roles** | Sign-in by email or User ID, with no public registration. Three roles (Super Admin, Manager, Team Member) backed by a permission matrix. Every user can edit their own profile and password and crop their photo, but can't change their role. |
| **Projects** | Status, start date and deadline, an assignable Coordinator, and progress rolled up from top-level tasks. Searchable and sortable in grid or list view. |
| **Tasks** | Kanban and list views, subtasks, categories, attachments, comments and stars. A QA workflow runs *To Do → In Progress → QA Testing → Rejected / Ready to Deploy → Done*. The full assignment history is kept: a reassignment adds to the history instead of overwriting it. |
| **Daily time logs** | Hours and minutes logged per person, per task, per day, by whoever is working on it. Totals roll up automatically. |
| **Work history** | Built from the time logs. Filter by Today, This Week, This Month or a custom range, and compare team members side by side. |
| **Dashboard** | Personal first: what's assigned to you, what's due or overdue, a "needs your attention" queue and your hours. Managers also get team throughput, a task pipeline and the week's top contributors. |
| **Calendar** | Month, week and day views of task and project dates, limited to what each viewer is allowed to see. |
| **Notifications** | In-app bell and page with read/unread toggles and bulk actions, plus email. Notifications are pushed **live** over WebSockets (Laravel Reverb). A periodic check takes over only if the live connection drops. |
| **Settings** | Post-login loading screen, an SMTP override that takes effect without redeploying, a live test email, and email template branding. |
| **UI** | Mobile-first, with a glass bottom sheet, a notched shortcut bar, Dynamic Island–style toasts, in-app 403 and 404 pages, and support for reduced motion. |

## Tech stack

- **Backend:** Laravel 13 (PHP 8.3+), Livewire 4 single-file components
- **Frontend:** Tailwind CSS 4, Alpine.js (bundled with Livewire), Vite, Quill (rich text)
- **Real time:** Laravel Reverb (WebSockets) with Laravel Echo and pusher-js
- **Database:** MySQL 8 / MariaDB 10.6+ (the test suite uses in-memory SQLite)
- **Packages:** `spatie/laravel-permission` for roles and permissions, `spatie/laravel-activitylog` for the audit trail
- **Tooling:** PHPUnit 12, Laravel Pint, Laravel Pail, Laravel Boost (AI-assisted development guidelines)

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.3 or newer | Extensions: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `openssl`, `fileinfo`. `pcntl` is optional and enables the log viewer in `composer dev`. |
| Composer | 2.x | |
| Node.js | 20.19+ or 22.12+ | Required by Vite 8. npm comes with it. |
| MySQL / MariaDB | 8.0+ / 10.6+ | Any database Laravel supports should work. MySQL is the tested target. |
| A mail server | optional | For example, a Mailtrap sandbox locally, or any SMTP provider in production. |

> **Tip:** [Laravel Herd](https://herd.laravel.com) (macOS/Windows) and [Valet](https://laravel.com/docs/valet) provide PHP, a local domain and HTTPS in one install. Sail/Docker works too.

## Quick start

```bash
git clone https://github.com/nurulanam/am2am-desk.git handloft
cd handloft

# 1. Create an empty MySQL database first (default name: am2am_desk),
#    then set DB_* in .env if your credentials differ from root/no password.

composer setup     # install deps, create .env + key, migrate & seed, link storage, build assets
composer dev       # start the app server, queue listener, logs, Vite and Reverb
```

Open **http://localhost:8000** and sign in with the [default account](#first-login).

`composer setup` is safe to re-run: it only generates an app key if `.env` doesn't already have one.

## Step-by-step installation

If you'd rather run each step yourself, or need to customise one:

**1. Get the code and dependencies**

```bash
git clone https://github.com/nurulanam/am2am-desk.git handloft
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
DB_DATABASE=am2am_desk
DB_USERNAME=root
DB_PASSWORD=
```

**3. Create the database, then migrate and seed**

```bash
mysql -u root -e "CREATE DATABASE am2am_desk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
```

The seeder creates the roles, the permission matrix, default task categories and the first Super Admin. It's idempotent, so running it again won't create duplicates.

**4. Link public storage**

Profile photos and attachments are stored on the `public` disk:

```bash
php artisan storage:link
```

**5. Configure real-time notifications (Reverb)**

Generate an app ID, key and secret, and add them to `.env`:

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

The `VITE_REVERB_*` lines in `.env.example` reference these values. Avoid `php artisan reverb:install`: the project already ships a customised `config/reverb.php`, and the installer can overwrite it.

**6. Build the frontend**

```bash
npm run build      # or `npm run dev` for hot reloading
```

Rebuild whenever you change a `VITE_*` value in `.env`, because those values are compiled into the JavaScript bundle.

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

Herd and Valet serve PHP for you. In that case, run only Vite and Reverb:

```bash
npm run dev
php artisan reverb:start
```

Set `APP_URL` to your site (for example, `https://handloft.test`). For HTTPS sites, see [Real-time notifications over HTTPS](#real-time-notifications-over-https).

## First login

| Field | Value |
| --- | --- |
| User ID / Email | `admin` / `admin@am2amdesk.test` |
| Password | `password` |

Then:

1. **Change the password** under the avatar menu → **My profile** → *Change password*.
2. **Add your team** under **Manage → Team → Add member**. There's no public sign-up; admins create every account.
3. **Configure email** under **Manage → Settings → Email / SMTP**, and send a test.
4. **Create a project**, add tasks, and start handing work off.

> ⚠️ **Change or remove the default account before deploying.** Its credentials are public in this repository.

## Configuration

Settings come from `.env`, with a few business settings editable in the app under **Settings**.

### Application

| Key | Purpose |
| --- | --- |
| `APP_NAME` | Display name. Defaults to `Handloft`. |
| `APP_URL` | The full public URL. Used in email links and asset URLs. |
| `APP_ENV` / `APP_DEBUG` | Use `production` / `false` in production. |
| `APP_KEY` | Encrypts sessions **and the SMTP password saved in Settings**. Rotating it makes that saved password unreadable, so re-enter it afterwards. |

### Mail

There are two layers:

1. **`.env`** (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_*`): the default mailer.
2. **Settings → Email / SMTP**: an override stored in the database, encrypted, that takes effect immediately. When it's set, it wins over `.env`.

With `MAIL_MAILER=log` (the default), emails are written to `storage/logs` instead of being sent, which is useful locally. A failed email is logged and never breaks the action that triggered it. The in-app notification is still delivered.

Email branding (colours and footer note) is under **Settings → Email Template**.

### Real-time notifications

The server pushes each notification to the recipient's private channel `App.Models.User.{id}` the moment it's created:

- **Order:** the database record is written first, then the broadcast, then the email.
- **Delivery:** broadcasts go out synchronously, so no queue worker is needed.
- **If Reverb is down:** the push failure is logged and the database record and email still go out (see `App\Notifications\Channels\SafeBroadcastChannel`).
- **Browser fallback:** the browser checks once a minute while the WebSocket is disconnected, and catches up once after reconnecting.

| Key | Purpose |
| --- | --- |
| `BROADCAST_CONNECTION` | `reverb` (use `null` to disable live push) |
| `REVERB_APP_ID` / `_KEY` / `_SECRET` | Credentials shared by the app and the Reverb server |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | Where the app and the browser reach Reverb. `REVERB_SCHEME=https` makes the app use TLS. |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | The interface and port Reverb binds to (default `0.0.0.0:8080`) |
| `REVERB_TLS_CERT` / `REVERB_TLS_KEY` | Certificate and key that let Reverb serve `wss://` directly |
| `REVERB_TLS_CA` | A CA bundle the app should trust when posting to Reverb (for example, Valet's local CA) |

The browser always uses the page's own scheme: `wss` on an HTTPS page and `ws` on HTTP. Browsers block insecure WebSockets on HTTPS pages.

#### Real-time notifications over HTTPS

**Local (Valet example):**

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

On macOS Valet, certificates live under `~/.config/valet/`. Herd keeps them under its own config directory. After changing these values, run `php artisan config:clear`, restart Reverb and rebuild the assets.

**Production:** terminate TLS at your web server and proxy WebSockets to Reverb. See [Deploying to production](#deploying-to-production).

### Files

Uploads (profile photos, task and comment attachments) go to the `public` disk under `storage/app/public`, served through the `public/storage` link. Profile photos are cropped in the browser and saved as 512×512 JPEGs. Any Laravel filesystem driver (for example S3) can be configured in `config/filesystems.php`.

### Queues

Notifications are sent **after the HTTP response** has been returned (`App\Support\DeferredNotification`), and broadcasts are sent synchronously, so Handloft runs with **no queue worker**. `QUEUE_CONNECTION=database` is configured, and `composer dev` starts a listener, for any jobs you add later.

### Sessions and cache

Both use the database by default (`SESSION_DRIVER=database`, `CACHE_STORE=database`). Redis works too: set the drivers and `REDIS_*`.

## Roles and permissions

Roles and permissions are seeded by `Database\Seeders\RoleAndAdminSeeder` and enforced by policies in `app/Policies`.

| Permission | Super Admin | Manager | Team Member |
| --- | :---: | :---: | :---: |
| Manage users (Team page) | ✅ | | |
| Manage settings | ✅ | | |
| View all tasks | ✅ | ✅ | |
| Create and assign tasks | ✅ | ✅ | ✅ |
| Reassign tasks | ✅ | | |
| Manage projects | ✅ | ✅ | |
| View all work history | ✅ | ✅ | |
| View own work history | ✅ | ✅ | ✅ |
| Edit completed hours | ✅ | | |

Without *view all tasks*, a person sees tasks they reported, are assigned to, review as QA, or have handed to someone else. This rule lives in one place: `Task::visibleTo()`, mirrored by `TaskPolicy::view()`. Super Admin accounts are hidden from the Team list.

Other permissions in the seeder (`manage-roles-permissions`, `manage-templates`, `view-reports`, `export-data`, `view-audit-log`) are reserved for upcoming features.

## Architecture

```
app/
├── Enums/                 TaskStatus, ProjectStatus, Role, TaskPriority, …
├── Http/Middleware/       EnsureUserIsActive (signs out deactivated users)
├── Livewire/Concerns/     Shared component behaviour (e.g. TogglesStars)
├── Models/                Task, Project, TaskAssignment, TaskTimeLog, TaskActivity, User, AppSetting, …
├── Notifications/         TaskAssigned, TaskSubmittedForQa, TaskRejected, TaskReadyToDeploy, ProjectCoordinatorAssigned
│   ├── Channels/          SafeBroadcastChannel (a failed push never blocks the email)
│   └── Concerns/          BroadcastsInstantly (database → live push → email)
├── Policies/              TaskPolicy, ProjectPolicy, …
├── Services/              TaskWorkflowService: create, reassign, submit for QA, mark done, time logs
└── Support/               DeferredNotification, MailSettings, Duration, Avatar, Html (sanitiser), …

resources/
├── views/
│   ├── layouts/           app (signed-in shell) and guest (sign-in screens)
│   ├── components/        Livewire single-file components (⚡*.blade.php) and Blade components
│   └── errors/            403 / 404 rendered inside the app shell for signed-in users
├── js/app.js, echo.js     Alpine helpers, the photo cropper, live-notification wiring
└── css/app.css            Tailwind theme (brand colours) and animations

routes/web.php             All routes (Livewire full-page components) plus a fallback for the in-app 404
routes/channels.php        Private notification channel authorisation
```

**Conventions**

- **Pages are Livewire single-file components.** The PHP class and Blade template live in one `⚡name.blade.php` under `resources/views/components`. `Route::livewire('/tasks', 'tasks.index')` maps to `components/tasks/⚡index.blade.php`.
- **Task state changes go through `TaskWorkflowService`.** It records assignment and activity history, keeps reassignments as history, and sends notifications.
- **Visibility is defined once** in `Task::visibleTo()` and `TaskPolicy::view()`. Lists, the calendar and pages all use it.
- **Time is the source of truth for hours.** Work History and the dashboard read `task_time_logs`.
- **UI patterns are shared:**
  - glass panels: `bg-white/70 backdrop-blur-xl`
  - toasts: `$this->dispatch('notify', message: '…', type: 'success|error|info|star|unstar')`
  - avatars: `<x-user-avatar :user="$user" class="…" />`
  - icons: `<x-nav-icon name="…" />`, from Lucide

## Testing and code style

```bash
composer test                       # or: php artisan test --compact
php artisan test --filter=Dashboard # a single file or test
vendor/bin/pint                     # format (Laravel preset)
vendor/bin/pint --test              # check formatting in CI
```

The suite (190+ tests) runs on in-memory SQLite with broadcasting disabled, so it needs no database server, Reverb or mail setup.

## Deploying to production

**1. Server:** PHP 8.3+ with the extensions above, a web server (Nginx, Apache or Caddy) whose document root is **`public/`**, a database, and Node.js for the build step (or build assets in CI).

**2. Environment:**

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://handloft.example.com

REVERB_HOST="handloft.example.com"
REVERB_PORT=443
REVERB_SCHEME=https
```

**3. Release steps:**

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force          # first deploy only (roles, permissions, first admin)
php artisan storage:link
php artisan optimize                 # caches config, routes, views and events
```

`storage/` and `bootstrap/cache/` must be writable by the web server user.

**4. Keep Reverb running** with a process manager. Supervisor example:

```ini
[program:handloft-reverb]
command=php /var/www/handloft/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/handloft/storage/logs/reverb.log
```

Raise the open-files limit (`ulimit -n`) if you expect many simultaneous connections.

**5. Proxy WebSockets** through your TLS site. Nginx example:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_pass http://127.0.0.1:8080;
}

location /apps {
    proxy_set_header Host $http_host;
    proxy_pass http://127.0.0.1:8080;
}
```

**6. After every deploy,** restart Reverb (`php artisan reverb:restart`). Restart PHP-FPM too if you use OPcache with `validate_timestamps=0`.

**7. Security checklist:**

- [ ] Default admin password changed (or the account replaced)
- [ ] `APP_DEBUG=false`
- [ ] A real mailer configured
- [ ] HTTPS everywhere
- [ ] Database backups scheduled

There are no scheduled commands, so you don't need a cron entry for `schedule:run`.

## Updating an installation

```bash
git pull
composer install                 # --no-dev --optimize-autoloader in production
npm install && npm run build
php artisan migrate              # --force in production
php artisan optimize:clear       # then `php artisan optimize` in production
php artisan reverb:restart
```

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Pages look unstyled, or there's a Vite manifest error | Run `npm run build`, or keep `npm run dev` running. |
| Profile photos or attachments return 404 | Run `php artisan storage:link`. |
| Notifications only appear after a minute or a refresh | Reverb isn't reachable. Start it, check that `REVERB_*` matches, and on HTTPS sites make sure Reverb serves `wss` (see [above](#real-time-notifications-over-https)). Check the browser console and `storage/logs/laravel.log`. |
| A `VITE_REVERB_*` change has no effect | Those values are compiled into the bundle, so rebuild the assets. |
| Emails aren't sent | Check `MAIL_MAILER` (`log` writes emails to the log file) or **Settings → Email / SMTP** → *Send test*. Failures are recorded in `storage/logs/laravel.log`. |
| The saved SMTP password stopped working | `APP_KEY` changed. Re-enter the password in Settings. |
| "This page has expired" (419) after a while | The session expired. Sign in again, or raise `SESSION_LIFETIME`. |
| A role change doesn't apply | Run `php artisan permission:cache-reset`. |
| A deactivated user is still signed in | They're signed out on their next request (`EnsureUserIsActive`). |

## Contributing

Contributions are welcome: bug reports, fixes, features, docs and design.

1. **Open an issue first** for anything larger than a small fix, so the approach can be agreed.
2. **Fork, then branch** from `main`, for example `feature/project-templates` or `fix/calendar-timezone`.
3. **Follow the conventions** in [Architecture](#architecture) and the surrounding code. Match the existing style and keep changes focused.
4. **Add or update tests.** Every behaviour change should come with a test in `tests/Feature`.
5. **Run the checks** before pushing:
   ```bash
   vendor/bin/pint && composer test && npm run build
   ```
6. **Open a pull request** that explains what changed and why. Include screenshots for UI changes, at phone and desktop widths.

This repository includes [Laravel Boost](https://github.com/laravel/boost) guidelines (`AGENTS.md`, `CLAUDE.md`, `boost.json`) for AI coding assistants. They're optional for human contributors.

The original product requirements are in [`am2am desk requirements.md`](am2am%20desk%20requirements.md), and the phased roadmap is in [`PHASES.md`](PHASES.md). **Reports and exports** are the next planned milestone.

## Security

Please **don't open a public issue for security vulnerabilities.** Report them privately through [GitHub Security Advisories](https://github.com/nurulanam/am2am-desk/security/advisories/new) so a fix can ship before disclosure. Include steps to reproduce and the affected version or commit.

## License

Handloft is open-source software licensed under the **MIT License**. You're free to use, modify and distribute it, including commercially, as long as the copyright and licence notice are kept. See [`LICENSE`](LICENSE) for the full text.

## Credits

- [Laravel](https://laravel.com), [Livewire](https://livewire.laravel.com), [Alpine.js](https://alpinejs.dev) and [Tailwind CSS](https://tailwindcss.com)
- [Laravel Reverb](https://reverb.laravel.com) for real-time delivery
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) and [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog)
- [Lucide](https://lucide.dev) icons (ISC License) and the [Quill](https://quilljs.com) editor
