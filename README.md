# Teamoria API

[![Laravel CI/CD](https://github.com/Teamoria/api/actions/workflows/laravel-ci-cd.yml/badge.svg)](https://github.com/Teamoria/api/actions/workflows/laravel-ci-cd.yml)

Teamoria API is the Laravel backend for the Teamoria workspace platform. It exposes a versioned JSON API for authentication, company workspaces, project and task management, file uploads, subscriptions, AI chat, notifications, and administration.

## Stack

- PHP 8.4 in CI, with Composer allowing PHP `^8.3`
- Laravel 13, Sanctum, Socialite, Reverb, queues, mail, and notifications
- Pest 4 and PHPUnit 12 for tests
- MySQL for local/production database configuration
- SQLite for CI and fast isolated test runs
- Vite, Tailwind CSS 4, Laravel Echo, and Pusher JS for frontend/realtime assets

## Requirements

- PHP 8.4 recommended
- Composer 2
- Node.js and npm
- MySQL or another Laravel-compatible database
- Optional: Postman for the bundled API collection

## Local Setup

Install dependencies and prepare the environment:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Update `.env` for your machine. The important local values are:

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=https://teamoria.online

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teamoria_api
DB_USERNAME=root
DB_PASSWORD=

API_KEY=local-dev-key

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Then migrate and seed the demo scenario:

```bash
php artisan migrate --seed
npm run build
```

You can also run the project bootstrap script after configuring the database:

```bash
composer run setup
```

That script installs dependencies, creates `.env` when needed, generates the app key, runs migrations, installs npm packages, and builds Vite assets. It does not seed demo data, so run `php artisan db:seed` when you want the sample workspace.

## Running Locally

For the full local development process:

```bash
composer run dev
```

This starts the Laravel server, database queue listener, Laravel Pail logs, and Vite dev server together.

For API-only work, these are usually enough:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
```

The local health check is:

```text
GET http://localhost:8000/api/health
```

If you enable Reverb broadcasting, run Reverb separately and set the broadcast environment values accordingly:

```bash
php artisan reverb:start
```

## Environment Notes

All `/api/v1/*` routes require the `x-api-key` header. Set `API_KEY` in `.env`, then send the same value with API requests.

Google authentication uses:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

AI chat processing posts queued messages to `AI_CHAT_ENDPOINT` and includes `AI_SERVICE_API_KEY` as `X-Internal-API-Key` when configured:

```dotenv
AI_CHAT_ENDPOINT=
AI_SERVICE_API_KEY=
AI_SERVICE_TIMEOUT=120
```

The default queue connection is `database`, so keep a queue listener or worker running for uploads, AI chat, notifications, and broadcast jobs that need background processing.

## API Usage

Login with the API key header:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'x-api-key: local-dev-key' \
  -d '{"email":"owner@teamoria.test","password":"password"}'
```

Authenticated routes also require the Sanctum bearer token returned by login:

```text
Authorization: Bearer <token>
x-api-key: local-dev-key
Accept: application/json
```

Successful responses use:

```json
{
  "success": true,
  "message": "...",
  "data": {}
}
```

Error responses use:

```json
{
  "success": false,
  "message": "...",
  "error_code": "VALIDATION_ERROR",
  "data": {}
}
```

## Route Groups

- `GET /api/health` - API health check
- `/api/v1/auth/*` - login, register, logout, password reset, and Google auth
- `/api/v1/otp/*` - send and verify one-time passwords
- `/api/v1/billing/plans` - public plan listing for authenticated users
- `/api/v1/uploads/*` - personal, company, project, and task uploads
- `/api/v1/chat/*` - AI chat sessions and messages
- `/api/v1/notifications/*` - notification listing and read state
- `/api/v1/admin/*` - platform administration for users, companies, plans, subscriptions, payments, projects, tasks, profile, and dashboard
- `/api/v1/company/*` - company workspace dashboard, subscription, staff, projects, tasks, and profile

Use Artisan when you need the complete route list:

```bash
php artisan route:list --path=api --except-vendor
```

## Demo Data

The default seeder runs `DemoScenarioSeeder`, which creates plans, companies, users, subscriptions, payments, projects, tasks, uploads, meeting summaries, extracted decisions, and knowledge chunks.

Useful seeded accounts:

| Role | Email | Password |
| --- | --- | --- |
| Platform admin | `admin@teamoria.test` | `1234568` |
| Company owner | `owner@teamoria.test` | `password` |
| Company manager | `manager@teamoria.test` | `password` |
| Company member | `member@teamoria.test` | `password` |
| Project viewer | `viewer@teamoria.test` | `password` |

The active demo company is `Teamoria Demo`, with an active `Business` subscription and the `Active Product Launch` project.

## Postman

Import both files from `postman/`:

- `Teamoria API - v1.postman_collection.json`
- `Teamoria.local.postman_environment.json`

Set the environment values before running requests:

- `api_origin` should match your Laravel server, usually `http://localhost:8000`
- `api_version` should stay `v1`
- `api_key` must match `.env` `API_KEY`
- `access_token` is filled after login, or can be pasted manually

## Testing

Run the full test suite:

```bash
php artisan test --compact
```

Run a focused test file:

```bash
php artisan test --compact tests/Feature/LoginTest.php
```

Format changed PHP files before committing:

```bash
vendor/bin/pint --dirty --format agent
```

The GitHub Actions workflow runs migrations and tests on PHP 8.4 with SQLite for pull requests and pushes to `main`.

## Deployment

The included workflow deploys `main` to production after tests pass. The deploy job connects over SSH, pulls the latest code, installs Composer dependencies, runs migrations, clears and caches Laravel optimization files, and brings the app back up.

Production workflow URL:

```text
https://api.teamoria.online/
```

Configure production secrets in GitHub Actions before relying on automated deployment:

- `SERVER_HOST`
- `SERVER_SSH_PORT`
- `SERVER_USERNAME`
- `SERVER_SSH_KEY`
- `PROJECT_PATH`

## Repository Map

- `app/Http/Controllers/Api/V1` - API controllers
- `app/Http/Requests` - validation request objects
- `app/Http/Resources` - API response resources
- `app/Models` - Eloquent models
- `app/Enums` - domain enums used by models, requests, and seeders
- `app/Jobs` - upload and AI chat background jobs
- `app/Notifications` - user and subscription notifications
- `database/migrations` - schema definition
- `database/seeders` - repeatable demo scenario data
- `routes/api.php` - versioned API routes
- `tests/Feature` - API and workflow tests
- `postman` - local API collection and environment 
