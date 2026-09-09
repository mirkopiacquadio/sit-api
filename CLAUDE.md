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

## Booster subsystem

The **Booster** is a management app (Blade `resources/views/booster/index.blade.php`, "Booster Monter") plus backend endpoints in `BoosterController` for producing analysis tables per municipality. `setDB($code_comune)` switches the `pgsql` connection to the municipality's database; the resulting tables live in that DB with generic (un-prefixed) names.

### Catasto source-table separation (dynamic per-comune, `<code>` = lowercase comune code)

- **CDU** → `<code>_catasto` (original, untouched)
- **Aree edificabili** → `<code>_catasto_base_aree_edif` (hand-made editable copy; `elabora()` uses it and returns 422 if missing)
- **Edifici fantasma** → `<code>_catasto_edifici` (derived: `WHERE "TIPOLOGIA"='EDIFICIO'`)

`<code>_catasto` mixes `TIPOLOGIA='PARTICELLA'` rows (have FOGLIO/PARTICELLA) and `TIPOLOGIA='EDIFICIO'` rows (empty FOGLIO/PARTICELLA).

### Final tables and QGIS constraint

Final tables (`aree_edificabili_finali_<date>`, `edifici_fantasma_finali_<date>`) are created via `CREATE TABLE AS ... GROUP BY` and are loaded as QGIS layers. **QGIS Server rejects a `bigint` primary key**, so they use a **`gid serial` (int4) PRIMARY KEY**. `ensureBoosterColumns($table)` migrates old tables (drops `id`, adds `gid`) on first web-app access. `lavorato`, `proprietario`, `catasto_tipo`, `sub_data` are also added; `AggiornaPropietariBooster` (generic over any table with FOGLIO/PARTICELLA) populates the owner columns.

### Edifici Fantasma feature

Web-app only (no Lizmap plugin JS). Multi-phase pipeline under `api/monter/booster/ef/*` (routes in `routes/web.php`, CSRF via `X-CSRF-TOKEN`) mapping a PostGIS workflow: FASE 1 CTR extract + 3D check, FASE 2 catasto edifici, FASE 3 geometry validity, FASE 4+5 difference/classification → `edifici_fantasma_finali_<date>` + owners. SRID auto-detected via `ST_SRID` (fallback 32633). Detail view: `resources/views/booster/edifici_fantasma_dettaglio.blade.php`.

## BoosterTributi subsystem

Separate module living **in parallel** to the Booster urbanistico/catastale above — independent controller, routes, tables, no shared code. Incrocia gli export tributari comunali (Halley – Tributi/TARI) con catasto (Sister) e anagrafe per individuare mq e componenti familiari non dichiarati ai fini TARI, quantificandone il recupero economico.

- **Controller**: `BoosterTributiController`, routes `api/monter/booster-tributi/*` (`routes/web.php`), view `resources/views/booster-tributi/index.blade.php` (stesso pattern AJAX/CSRF del Booster esistente).
- **Comune → DB**: `App\Support\BoosterTributi\ComuneConnection` (mappa comune→`<comune>-webgis` **duplicata volutamente** rispetto a `BoosterController::$nomiDb` — i due moduli sono indipendenti; la centralizzazione è nel backlog di `docs/PIANO_AMMODERNAMENTO_CATASTO_URBANISTICA.md`). `App\Services\BoosterTributi\ComuneSchema::assicura()` crea (idempotente) le tabelle `bt_*` nel DB del comune corrente; invocabile anche da CLI con `php artisan booster-tributi:migrate-comune {codice}`.
- **Tabelle** (prefisso `bt_`, per comune, tutte via migration Laravel — non `CREATE TABLE AS` raw, niente vincolo QGIS qui): `bt_tari_immobili`/`bt_tari_dettaglio_sottocategoria` (File 1/2 Halley), `bt_tariffario`/`bt_riduzioni` (File 3/4, **import annuale** — l'anno è un input del form, non è nel file), `bt_anagrafe_famiglie`/`bt_anagrafe_residenti`/`bt_anagrafe_gruppi_famiglia` (File 6/7/8), `bt_import_batch` (audit trail, un batch UUID per ogni import), `bt_anomalie_snapshot`/`bt_recupero_mq`/`bt_recupero_componenti` (output calcolati). `bt_interessi_legali` è l'unica tabella non per-comune: vive su `info-generali`, seed nazionale statico nella migration (`2026_09_09_000001_create_bt_interessi_legali_table.php`).
- **Import**: `App\Services\BoosterTributi\ExcelImportReader` legge xlsx/csv reali **e** i vecchi export `.xls` di Halley che sono in realtà tabelle HTML (sniffing automatico del contenuto). Ogni export Halley ha lo stesso layout: riga 1 "Stampa ...", riga 2 header, riga 3+ dati — `righeDati()` scarta le prime due. `App\Services\BoosterTributi\TariImportService` mappa le colonne per **posizione** (non per testo header) verso le tabelle `bt_*`.
- **Anomalie**: `App\Services\BoosterTributi\AnomalyDetector` gira subito dopo l'import di File 1 (non richiede gli altri file), produce la "fotografia" richiesta esplicitamente dal cliente (6 filtri: senza intestatario/catasto/indirizzo/mq TARI, componenti a zero sospetti, deceduto).
- **Calcolo recupero**: `App\Services\BoosterTributi\RecuperoCalculator` è **puro** (dipendenze tariffario/riduzioni/interessi iniettate come closure, non `DB::` dirette) → testato senza DB in `tests/Unit/BoosterTributi/RecuperoCalculatorTest.php`; i Job (`CalcolaRecuperoMqTari`, `CalcolaRecuperoComponentiFamiliari`, pattern `ShouldQueue` + `Cache::put("job_status_bt_{jobKey}", ...)` identico ad `AggiornaPropietariBooster`) lo istanziano via `RecuperoCalculator::conConnessioniDefault()`. Differenza mq = `0.8 * mq_catasto - mq_tari` (positiva = evasione); differenza componenti = componenti anagrafici reali − dichiarati TARI, solo persone fisiche e categorie abitative (esclude B/D/F/C01). Match indirizzo ubicazione↔residenza via `App\Services\BoosterTributi\AddressNormalizer` (unico punto fuzzy del sistema — tutti gli altri match sono su chiave esatta: codice utenza, codice fiscale, numero famiglia).

## Lizmap plugin JS (`js_lizmap/`)

Project JS served by Lizmap 3.6 from `lm_repos/monter_data/media/js/<comune>/`, mirrored here under per-comune folders (`chiusanosandomenico/`, `santagatadegoti/`, `castelpagano/`). `booster_plugin_fixed.js` is **identical across the three** (comune-agnostic, uses global `comuneUtente`): edit the chiusano copy, then `cp` to the others, verify with `node --check`. It renders the Booster viewer as a top-right modal (no backdrop) over the map, gated to users whose login contains `tributi`.
