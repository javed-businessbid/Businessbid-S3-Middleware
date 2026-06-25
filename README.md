<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

## Hierys Roots API

Multi-tenant Laravel API for workspace operations, projects, tasks, approvals, files, messaging, finance, settings, and reporting.

## Setup

1. Install dependencies:
   - `composer install`
2. Configure environment:
   - copy `.env.example` to `.env`
   - update DB and app settings
3. Generate app key:
   - `php artisan key:generate`
4. Run migrations and seeders:
   - `php artisan migrate --seed`
5. Start API server:
   - `php artisan serve`

## Workspace Status Rules

Workspace statuses are restricted to:

- `Active`
- `Inactive`

Behavior:

- Default workspace status is `Active`
- Input values `active` and `inactive` are normalized to `Active` and `Inactive`
- Invalid values return a `ValidationException` with field-level errors

## Project Status Flow

Project statuses are restricted to:

- `Backlog`
- `Planned` (default)
- `In Progress`
- `In Review`
- `Blocked`
- `Testing`
- `Done`
- `Archived`

Behavior:

- Default project status is `Planned` (database + application)
- Status input is normalized for common variants:
  - `in progress`, `in-progress`, `in_progress` => `In Progress`
  - `in review`, `in-review`, `in_review` => `In Review`
  - `to do`, `todo`, `to-do` => `Planned`
- Unknown statuses are rejected with validation errors

## Status Validation Error Shape

Validation failures on API endpoints return:

- `error.code`: `ValidationException`
- `error.message`: validation summary
- `error.fields`: per-field validation errors

Example keys inside `error.fields`:

- `status`
- `workspace_name`
- `admin.email`

## Key Endpoints

- `GET /api/workspaces`
- `POST /api/workspaces`
- `PATCH /api/workspaces/{workspace}`
- `GET /api/projects`
- `POST /api/projects`
- `GET /api/clients`

All tenant-scoped endpoints require `X-Workspace-Id` where applicable.

## Workspaces Response Fields

`GET /api/workspaces` includes admin metadata for each workspace item:

- `admin_id`
- `admin_name`
- `admin_phone`

## Clients Endpoint Metrics

`GET /api/clients` supports analytics metadata in `meta.stats`:

- `from_date` (optional, query param, format: `YYYY-MM-DD`)
- `to_date` (optional, query param, format: `YYYY-MM-DD`)
- if omitted, the API defaults to the last 7 days window

Returned stats include:

- `date_range` (`from_date`, `to_date`)
- `totals` (`total_clients`, `total_monthly_budget`, `total_active_clients`)
- `window_totals` for the selected date range
- `recent_clients` (latest 5 clients in the selected date range)
