# AM2AM Desk

Team Management & Task Tracking — an internal Laravel + Livewire application for managing team members, projects and tasks with full assignment history, daily time logging, automatic work-history tracking, and notifications.

The full requirements live in [`am2am desk requirements.md`](am2am%20desk%20requirements.md); the phased build plan is in [`PHASES.md`](PHASES.md).

## Stack

- Laravel 13 (PHP 8.3)
- Livewire 4 (single-file components under `resources/views/components`)
- MySQL
- Tailwind CSS 4 + Vite
- `spatie/laravel-permission` for roles & permissions
- `spatie/laravel-activitylog` for audit logging
- Laravel Notifications (database + mail channels) for in-app and email alerts

## Features

- **Auth & Users** — custom login (no public registration), Super Admin / Manager / Team Member roles with a configurable permission matrix
- **Projects** — name, description, status, start/deadline dates, an assignable Coordinator, and progress rolled up from child tasks
- **Tasks** — Kanban board and list views, full assignment/reassignment history (nothing is overwritten), subtasks, categories, attachments, comments, and a QA workflow (To Do → In Progress → QA Testing → Rejected / Ready to Deploy → Done)
- **Daily Time Logs** — hours + minutes logged per day per task by whoever's actually connected to it (assignee any time; QA/Reviewer once it reaches QA Testing; Reporter once it reaches Ready to Deploy), auto-summed per task
- **Work History** — built from the real daily time logs (not a one-off snapshot), with a per-task breakdown, Today/This Week/This Month/Custom-range filters, and a side-by-side team comparison view
- **Notifications** — an in-app bell + full notifications page, and email, covering: a new project coordinator assignment, a task being assigned/reassigned, a task submitted for QA testing, a task rejected, and a task marked ready to deploy. Sent after the HTTP response has already been returned to the browser (no queue worker required — see `DeferredNotification`)
- **Calendar** — Month/Week/Day views of task and project deadlines, scoped to what each viewer can see, with click-through to the record
- **Settings** — a sidebar of app-wide preferences: a configurable post-login loading screen (duration, transparency, blur), and an SMTP server override (with a live "Send Test Email" check) that takes effect immediately, no redeploy needed
- **Dashboard** — team/task/hours summary cards and a 14-day hours-logged chart

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

### Mail

Configure `MAIL_*` in `.env` to actually deliver notification emails (e.g. a Mailtrap sandbox inbox for local dev). Without a configured mailer, the app still works — the in-app bell and Notifications page are unaffected either way, and a failed email send is caught and logged rather than breaking the triggering action.

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
| Extra — Projects & Time Tracking | Projects with Coordinators, subtasks, daily time logging (hours + minutes), work history rebuilt from real daily logs | ✅ Done (ahead of the original phase plan) |
| 3 — Reporting | Productivity reports, exports, charts | Not started |
| 4 — Enhancement | Templates, **calendar**, **notifications**, search, attachments, full settings | Calendar ✅ done, Notifications ✅ done; rest not started |

See [`PHASES.md`](PHASES.md) for the detailed scope of each phase.
