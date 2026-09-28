# AM2AM Desk

Team Management, Task Tracking & Lead CRM — an internal Laravel + Livewire application for managing team members, tasks with full assignment history, automatic work-history tracking, and (in later phases) a lead CRM with outreach and reporting.

The full requirements live in [`am2am desk requirements.md`](am2am%20desk%20requirements.md); the phased build plan is in [`PHASES.md`](PHASES.md).

## Stack

- Laravel 13 (PHP 8.3)
- Livewire 4 (single-file components under `resources/views/components`)
- MySQL
- Tailwind CSS 4 + Vite
- `spatie/laravel-permission` for roles & permissions
- `spatie/laravel-activitylog` for audit logging

## Getting Started

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database matching `.env` (`DB_DATABASE=am2am_desk` by default), then:

```bash
php artisan migrate --seed
npm install
```

Run everything (server, queue worker, log tailing, Vite) with one command:

```bash
composer dev
```

The app is available at `http://localhost:8000` (or your configured Valet/Herd domain).

### Default login

The seeder creates a Super Admin account:

- **User ID / Email:** `admin` / `admin@am2amdesk.test`
- **Password:** `password`

There is no public registration route — all accounts are created by an admin from the Team page.

## Testing

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

## Build Status

| Phase | Scope | Status |
| --- | --- | --- |
| 0 — Foundation | Auth scaffold, roles/permissions, audit log, dashboard shell | ✅ Done |
| 1 — Core | Users, tasks with assignment/reassignment history, task completion, automatic work history | ✅ Done |
| 2 — CRM | Leads, outreach, follow-ups | Not started |
| 3 — Reporting | Productivity/lead/outreach reports, exports, charts | Not started |
| 4 — Enhancement | Templates, calendar, notifications, search, attachments, full settings | Not started |

See [`PHASES.md`](PHASES.md) for the detailed scope of each phase.
