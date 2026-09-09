# Piano di ammodernamento — Catasto & Urbanistica (SIT API)

> Sintesi di un'analisi in **sola lettura** (nessun file di produzione modificato) su:
> `CatastoImmobileController`, `CDUController`, `NtaController`, la parte urbanistica/catasto di
> `BoosterController`, `AggiornaPropietariBooster`, `AppHelper`, `config/database.php`, rotte e viste `print/`.
> Priorità: **P0** sicurezza → **P1** correttezza/performance → **P2** manutenibilità.

---

## 0. Nota architetturale (da tenere presente in ogni refactor)

Ci sono **due topologie di dati** diverse:
- **Catasto** (`CatastoImmobileController`, `AppHelper`) → **un DB unico** `pgsql2` (`informativo-immobili`), segmentato per colonna `cod_com`. Nessun `setDB`.
- **Geometrie / urbanistica** (Booster, CDU) → **un DB per comune** (`*-webgis`) selezionato via `setDB()` (muta la connessione `pgsql`).

Il job `AggiornaPropietariBooster` tiene aperte **entrambe** (`pgsql` comune + `pgsql2` catasto). Qualsiasi centralizzazione della gestione connessioni deve modellare esplicitamente le due topologie.

---

## P0 — Sicurezza (da affrontare per primi)

| # | Problema | Dove | Note |
|---|----------|------|------|
| P0.1 | **Credenziali DB in chiaro committate** + `.env` tracciato in git | `config/database.php:73,103,118`; `.env` (tracciato, non in `.gitignore`) | **VERIFICATO.** Password nel repo e nella history → **compromessa**. Va **ruotata** su Postgres, non basta toglierla dai file. |
| P0.2 | **Endpoint senza autenticazione** (solo `/user` protetto) | `routes/api.php`, `routes/web.php` (gruppi catasto/CDU/NTA/booster) | Espone **dati personali** (nome, nascita, **CF/P.IVA**) → rischio GDPR, e rende sfruttabile da anonimo ogni SQLi sotto. |
| P0.3 | **SQL injection diffusa** (interpolazione di stringhe, no binding) | Catasto: `CatastoImmobileController.php:25,60,111,132,808-861`; `AppHelper.php:30-42`; CDU: `calcolaCdu` `:582-593` (nome tabella `$piano`), `elencoNormePiani` `:143` (`{tabella}` da URL); `setDB` `:70-71` / Booster `:71,78,99` (`code_comune` **prima** della whitelist) | `strtoupper`/`strtolower` non neutralizzano. Iniettabili: comune, foglio, sub, id, cat, cf, nome, **nome tabella/piano**. |
| P0.4 | **Path traversal / arbitrary file read** | `NtaController.php:16,50` (`$dir`,`$file` grezzi) | `dir=../../..` legge file fuori dallo storage. Endpoint pubblico. |
| P0.5 | **`exec()` con argomenti non-escaped** (RCE) + path Windows su Darwin | `CDUController.php:311` | `generaCDU` non funziona su questo ambiente; verificare se è usato in prod Windows prima di rimuoverlo. |
| P0.6 | **Dompdf `chroot('/')` + remote abilitato** | `CatastoImmobileController.php:977-981` | Potenziale SSRF/lettura file locali via `<img>`/`@import`. |
| P0.7 | **`APP_DEBUG=true`** + `'Errore: '.$e->getMessage()` in JSON | `.env`; es. `BoosterController.php:363,520,655,860` | Leak struttura DB/query al client in produzione. |

---

## P1 — Correttezza / Performance

| # | Problema | Dove |
|---|----------|------|
| P1.1 | **N+1 massiccio nel job** (per ogni particella e per ogni sub ricostruisce l'intera visura storica terreni+fabbricati) — è la causa del `timeout=7200` | `AggiornaPropietariBooster.php:101-215`; `BoosterController::getProprietariAttuali:109` |
| P1.2 | **`setDB` purge/reconnect su connessione globale**: race condition in concorrenza + leak di stato tra job in coda | `BoosterController.php:52`; `CDUController.php:51`; `Job:81-84,227,237` |
| P1.3 | **Mappa comuni triplicata e già divergente**: `E249`/`G243` presenti solo in CDU → **quei comuni non sono elaborabili dal Booster** (`setDB` lancia "Codice comune non valido"). `F113` duplicata in 2 mappe | `BoosterController.php:17-47`; `AggiornaPropietariBooster.php:43-72`; `CDUController.php:14-46` |
| P1.4 | **Stato "proprietario attuale" dedotto da stringa `"fino al"`** (testo di presentazione) invece che dai dati (date validità / `id_mutazione_finale IS NULL`) — si rompe se cambia il formato | `BoosterController.php:195`; `CatastoImmobileController.php:740` |
| P1.5 | **Motore CDU triplicato** (~90 righe identiche in 3 metodi, con divergenze → bug ×3) | `CDUController.php:201-331, 333-438, 440-565` |
| P1.6 | **`global $mqMinimo` mai inizializzato** → la soglia minima non filtra mai (bug logico silente) | `CDUController.php:689,725` |
| P1.7 | **Query spaziali in loop N×M** (`calcolaCdu` con `ST_Intersection`/`ST_Area` dentro doppio for) | `CDUController.php:230-274,582` |
| P1.8 | **`print_r(...); exit;` / `echo` di debug in produzione** (muore la richiesta senza status corretto) | `CDUController.php:236,329,374,480` |
| P1.9 | **`$uiu[0]`**: mostra solo la prima particella; **crash** (`Undefined array key 0`) su input vuoto | `CDUController.php:433,539` |
| P1.10 | **Rotte verso metodi commentati/privati** → 500 | `routes/api.php:38` (`intersezioniPianiUrbanistici` commentato), `:39` (`calcolaCdu` è `private`) |
| P1.11 | **`regexp_replace(campo,'0*','')`** non ancorato per togliere zeri iniziali: semantica fragile su chiavi foglio/particella | `CatastoImmobileController.php` passim (53,103,180,896…) |
| P1.12 | **Sub-select scalari correlate riga-per-riga** (decode `c_predefinito_*`) invece di `LEFT JOIN` | `CatastoImmobileController.php:176-185,320-328,631-651` |
| P1.13 | **Nessuna validazione / error handling** nei controller catasto/CDU; ritorni incoerenti (a volte array, a volte JSON) | tutti i metodi; `BoosterController.php:109-142` |
| P1.14 | **Dynamic property deprecata** (`$infoComune` non dichiarata) — PHP 8.2 | `BoosterController.php:75` |

---

## P2 — Manutenibilità

| # | Problema | Dove |
|---|----------|------|
| P2.1 | **Nessun test reale** (solo scaffold `ExampleTest`); logica pura non coperta | `tests/` |
| P2.2 | **God controllers** (Booster 1377, Catasto 1004, CDU 743 righe) e anti-pattern `new Controller()` + `Request` finta | `Job:111,122`; controller vari |
| P2.3 | **Nessun Form Request** (validazione `preg_match` inline duplicata) | `app/Http/Requests/` assente |
| P2.4 | **Nessun layer service/repository**; god-method duplicati Terreni/Fabbricati (~80% identici) | `CatastoImmobileController.php:144-283 / 285-394` |
| P2.5 | **HTML per concatenazione** + token `str_replace` (niente escaping → XSS nel documento) | `AppHelper::formattaCdu:56-169` |
| P2.6 | **Viste duplicate** (`table.blade` / `table_email.blade`) + template OpenOffice legacy `windows-1252` | `resources/views/table*.blade.php`, `print/*.blade.php` |
| P2.7 | **Bug logico**: filtri `cat/qua/piva` azzerati proprio quando valorizzati → non funzionano mai | `CatastoImmobileController.php:851-853,864` |
| P2.8 | **`exit;` nel controller PDF** (bypassa il ciclo di vita Laravel) | `CatastoImmobileController.php:1002` |
| P2.9 | **File morti / debug residuo** | `CDUController-concommenti.php`; blocco commentato `CDUController.php:147-197` |
| P2.10 | **No `strict_types`, no tipizzazione**, `config()` mancante (SRID `32633`, mappe comuni), Pint non applicato | trasversale |
| P2.11 | **Valori magici** (`'1900-01-01'`, `'9999999'`) senza costanti | `CatastoImmobileController.php` passim |

---

## Percorso di migrazione incrementale (a basso rischio)

**Fase 0 — Rete di sicurezza (NON negoziabile).**
Scrivere *characterization test* (golden master) su `elencoMutazioniCatastoTerreni/Fabbricati`, `estraiProprietariAttuali`/`getProprietariAttuali` e sul motore CDU, con 3–4 particelle reali per comune, salvando l'output JSON. Proteggono ogni step successivo. Richiede un Postgres/PostGIS di test (non SQLite).

**Fase 1 — Contenimento P0 (perimetrale, reversibile, nessun cambio di logica).**
Ruotare la password DB e spostarla in `env()`; togliere `.env` da git + valutare purge history; `APP_DEBUG=false` in prod; `auth:sanctum` su **tutti** i gruppi rotte; restringere `chroot` Dompdf + `isRemoteEnabled=false`; validare `dir/file`+`realpath` in NtaController; `escapeshellarg` (o rimuovere `generaCDU`).

**Fase 2 — Bonifica SQLi mirata (P0.3).**
Un metodo alla volta: interpolazione → **binding PDO** (`?`/`:param`), tenendo identico l'SQL prodotto; nomi tabella/piano → **whitelist** contro `pg_tables`/`nome_piani` + `quote_ident`; spostare le query di `setDB` **dopo** la validazione del comune. Ogni conversione con test di regressione (payload `' OR 1=1`) e confronto golden master. Iniziare dai metodi foglia (`selectSuperficieTerreno`, `selectPersone*`), finire con i motori mutazioni.

**Fase 3 — Fonte unica comuni (P1.3).**
`config/comuni.php` (o colonna `ana_comuni.nome_db`) letta da un `ComuneRepository`; eliminare le 3 mappe PHP; **riallineare `E249`/`G243`** e rimuovere il doppione `F113`.

**Fase 4 — `ComuneDatabaseManager` (P1.2).**
Connessioni **dinamiche per-comune** (`config(["database.connections.comune_$code" => …])` + `DB::connection("comune_$code")`) invece di mutare `pgsql` globale. Adattare **prima il Job** (elimina i workaround `deleteReserved`), poi i controller uno alla volta.

**Fase 5 — Proprietari attuali: query mirata + batch (P1.1, P1.4).**
Nuovo metodo `proprietariAttuali(comune,foglio,particella,sub)` basato su JOIN e **stato dei dati** (non testo), affiancato a quello vecchio; il job carica in batch per chunk (`WHERE (foglio,numero) IN (...)`). Confronto con la strada storica prima di dismetterla.

**Fase 6 — `CduService` unico + estrazione service/action (P1.5, P2.2, P2.4).**
Un motore CDU condiviso (i 3 metodi diventano formatter); `CatastoService`/`VisuraRepository`; presentazione in **Blade** con escaping (P2.5). Copertura garantita dai test della Fase 0.

**Fase 7 — Pulizia (P1.6–P1.12, P2.7–P2.11).**
Fix `mqMinimo`, `uiu[0]`/vuoto, rotte morte, filtri giuridici invertiti, `regexp` ancorata, `LEFT JOIN` sui decode, `exit`→`response()`, `strict_types`/tipi/costanti/`config`, rimozione file morti, `pint`.

---

## Rischi principali

1. **Ricostruzione mutazioni** (logica catastale sottile, non documentata, `9999999`/risalita ricorsiva): senza i golden master della Fase 0 ogni refactor è cieco → **Fase 0 obbligatoria**.
2. **Zero-stripping**: cambiare `regexp_replace('0*')` altera valori usati come **chiavi** (`'f'.foglio.'n'.numero`) → cambiare in un solo punto e verificare il matching proprietari↔immobili.
3. **Connessione condivisa in concorrenza**: finché `pgsql` è mutata a runtime, più job/richieste in parallelo possono leggere il DB sbagliato → **serializzare i job per comune** fino alla Fase 4; testare enqueue→run→ack reale (l'area `deleteReserved` è già stata fonte di bug).
4. **Rotazione credenziali**: impatta ogni ambiente/deploy che usa il valore hardcoded → coordinare il rollout.
5. **Aggiungere auth**: può rompere frontend/webgis che oggi chiamano in anonimo → prevedere token machine-to-machine e coordinare con i client.
6. **PostGIS non testabile su SQLite** → serve Postgres in CI.
7. **`generaCDU` (LibreOffice)** già non-funzionante su questo ambiente (path Windows): decidere se è in uso in prod Windows prima di rimuoverlo.
