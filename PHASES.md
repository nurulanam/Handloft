# Development Phase Breakdown

**Project:** Team Management, Task Tracking & Lead CRM (internal codename: AM2AM Desk) **Stack:** Laravel + Livewire (Volt/Livewire components) + MySQL + Tailwind **Source of truth:** `am2am desk requirements.md`

This document breaks the SRS into implementation phases small enough to build, test, and demo independently. Each phase lists scope, the DB tables/models it introduces, the main Livewire components/pages, and a "done when" checklist. We will implement strictly phase by phase — no phase starts until the previous one is accepted.

---

## Phase 0 — Project Foundation

Not in the SRS's numbered phases, but required before Phase 1 can start.

**Scope**

- Laravel install, Breeze/Fortify-less custom auth scaffold (no public registration)
- Tailwind + Livewire + Alpine setup
- Base layout (sidebar nav from SRS §50, topbar, notifications bell placeholder)
- Roles & permissions package decision (spatie/laravel-permission)
- Database connection, base `.env`, migrations baseline
- Activity log package decision (spatie/laravel-activitylog) for audit trail (§40)

**Core tables**

- `users` (name, user_id/login id, email, phone, password, department, joining_date, status, profile_photo)
- `roles`, `permissions`, pivot tables (via spatie/laravel-permission)

**Done when**

- Fresh app boots, migrates, and shows a styled empty dashboard shell behind auth
- Login/logout works; no registration route exists

---

## Phase 1 — Core: Auth, Users, Tasks, Work History

Maps to SRS §51 Phase 1 + §3, §4 (partial), §5–§17.

**1a. Authentication & User Management (§2, §3)**

- Login (User ID/Email + password), Forgot Password
- Admin "Create User" form: full name, user id, email, phone, password, role, department, joining date, status, photo
- Active/Inactive enforcement on login
- Roles: Super Admin, Manager, Team Member (seeded)
- Permission matrix (§41) implemented via spatie permissions, configurable in Settings

**1b. Task Management (§5–§10)**

- Tables: `tasks`, `task_assignments` (history, not overwritten), `task_activities` (timeline), `task_categories`, `task_attachments`
- Create Task Livewire form: title, description, assigned_to, priority, start date, deadline, category, attachment, notes
- Task list views: All Tasks, My Tasks, Assigned by Me, Assigned to Me
- Assign / Reassign action → writes new `task_assignments` row + `task_activities` entry, never overwrites prior rows
- Task detail page with full activity timeline (created → assigned → reassigned → completed)
- Complete Task modal: Actual Hours Worked (required), Completion Note (optional)

**1c. Automatic Work History (§11–§16)**

- On task completion: auto-create `work_histories` row (employee, task, assigned_by, assigned_date, completed_date/time, actual_hours, status) — no duplicate data entry
- Work History page per employee with Today / This Week / This Month / Custom Range summaries
- Completed-hours edit restricted to Admin/authorized Manager, logged to audit log (`activity_log`) with before/after + reason

**1d. Dashboard (§4, partial)**

- Team card: total/active members, hours today/week/month
- Tasks card: total, pending, in progress, completed, overdue

**Done when**

- Full workflow in SRS §46 and §53 (Rahim creates → assigns Karim → Karim completes with hours → work history auto-populates → dashboard reflects it) works end-to-end
- Reassignment preserves full history (§8, §15) — verified with a 3-hop reassignment test case
- Non-admin cannot edit completed hours; admin edit is audit-logged

---

## Phase 3 — Reporting & Analytics

Maps to SRS §51 Phase 3 + §31–§33, §43–§44.

- Team Productivity report (table + filters: date, member, department, task) — §32
- Individual Report per team member — §33
- Export to Excel/CSV and PDF (§43) — via maatwebsite/excel + dompdf/snappy
- Dashboard charts (§44): working hours, task status — Chart.js via Livewire, all date-filterable

**Done when**

- Every report in §32–§33 renders with real data and correct filters
- Exports produce valid CSV/Excel/PDF matching the on-screen report

---

## Phase 4 — Enhancement

Maps to SRS §51 Phase 4 + §26–§27, §29–§30, §36–§40.

- Message Template system (§29) with categories + status, admin-managed
- Dynamic template variables `{Client Name}`, `{Company Name}`, `{Team Member Name}`, `{Website}` (§30)
- Calendar (§36): task deadlines, assignments, follow-ups, client activities; daily/weekly/monthly views; click-through to record
- Notifications (§37, §27): in-app + dashboard + optional email, for task assignment/reassignment/deadline/overdue, status change
- Global search & filters (§38)
- File attachments (§39) with admin-configurable max size/allowed types
- Full audit log UI (§40) with filters, backed by the activity log wired in from Phase 0 onward
- Admin Settings screens (§42): users/roles/permissions, task lookups, templates, notification settings

**Done when**

- All items in §49 "Important Business Rules" are verifiable in the running app
- Admin can configure every lookup list (statuses, sources, temperatures, categories, channels) without a code change

---

## Cross-Phase Non-Functional Requirements

Applied continuously, not as a separate phase:

- Mobile responsiveness (§45) — verified at end of each phase, not deferred
- Server-side validation, input sanitization, authorization on every action (§3.3, §49.20)
- Password hashing, no plaintext (§3.3)
- Audit logging wired from Phase 0, populated progressively as each feature lands
- Architecture kept open for future integrations (§52): timer on tasks, email/WhatsApp/LinkedIn/Google Calendar integrations, multi-team/workspace — none built now, but no design decision should block them later

---

## Suggested Build Order Recap

1. Foundation → 1. Core (Auth/Users/Tasks/Work History) → 3. Reporting → 4. Enhancement

We proceed one phase at a time: implement, review/demo, then move to the next.