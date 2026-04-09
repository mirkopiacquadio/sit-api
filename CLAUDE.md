# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 10 REST API** for an Italian real estate/cadastral and urban planning information system (SIT — Sistema Informativo Territoriale). It serves queries on property registries (*catasto terreni/fabbricati*), urban planning certificates (*CDU*), and NTA regulations for Italian municipalities.

## Commands

```bash
# Development server
php artisan serve

# Run all tests
php artisan test
./vendor/bin/phpunit

# Run a single test
php artisan test --filter=TestName

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Queue worker (processes async jobs)
php artisan queue:work

# Database migrations
php artisan migrate

# Build frontend assets
npm run build
npm run dev
```

## Architecture

### Dual Database Setup

The app uses two PostgreSQL connections defined in `config/database.php`:

- **`pgsql`** (`info-generali`) — general info, users, jobs tables
- **`pgsql2`** (`informativo-immobili`, default) — cadastral/real estate data

Controllers dynamically switch database connections per-request based on a `comune` (municipality) code passed by the client. Many queries are raw SQL against cadastral tables not managed by Laravel migrations.

### Routes

- **`routes/api.php`** — JSON REST endpoints (prefix `/api`): catasto queries, CDU, booster elaborations, Sanctum auth
- **`routes/web.php`** — Web routes returning Blade views: PDF print pages, Excel/TXT import interfaces, booster UI

### Key Controllers

- `BoosterController` — batch processing of property owner data; dispatches `AggiornaPropietariBooster` jobs
- `CatastoImmobileController` — queries the land registry (catasto fabbricati/terreni)
- `CDUController` — generates urban planning certificates
- `ExcelTxtController` — Excel/TXT file imports, previews, and generation
- `NtaController` — urban planning regulations
- `ComunitaMontanaController` / `ComunitaMontanaSquadraController` — mountain community management

### Async Jobs

`app/Jobs/AggiornaPropietariBooster.php` runs with a 2-hour timeout using the database queue. Job status is tracked via Laravel's Cache (file driver). Queue connection: `database` (jobs table).

### PDF / Spreadsheet Generation

- PDF: `barryvdh/laravel-dompdf` — rendered from Blade templates in `resources/views/print/`
- Spreadsheets: `phpoffice/phpspreadsheet` — used in `ExcelTxtController`

### Authentication

Laravel Sanctum token-based auth. Tokens stored in `personal_access_tokens` table on the `pgsql` connection.

### Helpers

`app/Helpers/AppHelper.php` — custom utility functions used across controllers.
