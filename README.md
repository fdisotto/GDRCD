# GDRCD — Gioco di Ruolo Chat Driven

> CMS PHP per giochi di ruolo via chat in tempo reale.

GDRCD è un CMS open source pensato per gestire mondi di gioco di ruolo
"play by chat": permette ai giocatori di interagire in stanze condivise,
gestire la propria scheda personaggio, partecipare ad economia e gilde,
mentre lo staff cura ambientazione, regolamento ed esiti delle giocate
attraverso un pannello di amministrazione integrato.

Il progetto, nato nei primi anni 2000, è ora in fase di modernizzazione:
runtime containerizzato con Docker, design system su Tailwind CSS,
refactor del frontend in JavaScript vanilla (rimossa la dipendenza da
jQuery) e progressiva introduzione di prepared statement lato database.

---

## Caratteristiche principali

- **Chat live multi-stanza** con presenze, log azioni e sussurri privati
- **Scheda personaggio** completa (descrizione, storia, diario,
  equipaggiamento, oggetti, punti esperienza, transazioni)
- **Gilde** con ruoli, gerarchie e amministrazione dedicata
- **Mercato** in-game e **banca** con conto corrente e trasferimenti
- **Sistema esiti** per la gestione narrativa degli interventi master
- **Bacheche**, **forum** e **messaggi privati** integrati nel mondo
- **Gestione editoriale** di regolamento, ambientazione, razze, luoghi,
  mappe cliccabili, oggetti e abilità
- **Log eventi** completi (chat, messaggi, azioni) consultabili dallo staff
- **Sistema permessi** granulare per distinguere staff, master e giocatori
- **Anagrafe** e **prenotazioni** per organizzare eventi e sessioni
- **Pannello di gestione** unificato per tutte le entità del mondo

---

## Stack tecnico

| Componente      | Versione / Note                              |
|-----------------|-----------------------------------------------|
| PHP             | 8.2 (Apache `mod_php`, immagine `php:8.2-apache`) |
| Database        | MariaDB 10.11 (compatibile con MySQL 8)      |
| CSS framework   | Tailwind CSS 3.4 — standalone CLI, **senza Node** |
| Orchestrazione  | Docker + `docker compose`                    |
| Frontend JS     | JavaScript vanilla (nessuna dipendenza da jQuery) |
| Estensioni PHP  | `mysqli`, `gd`, `mbstring`, `zip`            |

---

## Quick start (Docker)

Requisiti: Docker Engine 20+ e plugin `docker compose`.

```bash
git clone git@github.com:GDRCD/GDRCD.git
cd GDRCD
docker compose up -d
```

Al primo avvio apri `http://localhost:8080/installer.php` per eseguire
l'installazione dello schema. L'installer richiama
`DbMigrationEngine::updateDbSchema()` che crea le tabelle baseline e
applica tutte le migrazioni presenti in `db_versions/`.

A installazione completata accedi alla homepage `http://localhost:8080/`.

### Container avviati

| Container            | Servizio              | Porta host    | Note                                       |
|----------------------|-----------------------|---------------|--------------------------------------------|
| `gdrcd-web`          | PHP 8.2 + Apache      | `8080`        | Applicazione GDRCD                         |
| `gdrcd-db`           | MariaDB 10.11         | `3306`        | Database                                   |
| `gdrcd-pma`          | phpMyAdmin 5          | `8081`        | UI web a `http://localhost:8081`           |
| `gdrcd-tailwind`     | Tailwind watcher      | —             | Rebuild di `output.css` in dev             |
| `gdrcd-browsersync`  | BrowserSync proxy     | `3000`/`3001` | Live-reload del browser in dev             |

Il file `docker-compose.override.yml` viene caricato automaticamente in
sviluppo e aggiunge i container `gdrcd-tailwind` e `gdrcd-browsersync`,
più un bind mount del sorgente nel container `web` (così le modifiche
PHP sono immediate).

---

## Sviluppo

### Tailwind watcher

Il container `gdrcd-tailwind` esegue `docker/tailwind-watch.sh`, che
usa `inotifywait` per rilevare modifiche a file `.php`, `.js`, `.html`,
`.css` e a `tailwind.config.js`. Ad ogni salvataggio il binario
standalone `tailwindcss` rigenera `themes/tailwind/output.css` minificato.

Per intervenire sul design system basta quindi:

1. Modificare `themes/tailwind/input.css` (o aggiungere classi nei file PHP)
2. Attendere il rebuild (visibile in `docker compose logs -f tailwind`)
3. Ricaricare la pagina

### Hot reload dev (BrowserSync)

In sviluppo lo stack avvia anche `gdrcd-browsersync`, un proxy
[BrowserSync](https://browsersync.io/) che osserva i file del progetto e
ricarica automaticamente le schede del browser ad ogni salvataggio.

- URL da usare in dev: **`http://localhost:3000`** (al posto di
  `http://localhost:8080`); la UI di controllo di BrowserSync è su
  `http://localhost:3001`.
- File osservati: `**/*.php`, `**/*.inc.php`, `includes/*.js` e
  `themes/tailwind/output.css` (rigenerato dal watcher Tailwind, quindi
  modifiche a `input.css` o classi nei file PHP attivano comunque il
  reload una volta completato il rebuild del bundle).
- Debounce di 500 ms per evitare reload eccessivi durante salvataggi
  multipli ravvicinati.
- Il proxy punta direttamente al container `gdrcd-web`, quindi gli
  endpoint, le sessioni e i cookie restano gli stessi di `:8080`.
- Per disattivarlo senza toccare la compose:
  `docker compose stop gdrcd-browsersync`.

Al **primissimo** avvio l'immagine `node:20-alpine` esegue
`npm install -g browser-sync`: aspettarsi ~30 s prima che la porta 3000
sia disponibile (i log di `docker compose logs -f browsersync` mostrano
lo stato). I lanci successivi sono immediati grazie al layer cache.

### Credenziali DB di sviluppo

Definite in `docker-compose.yml` e iniettate via variabili d'ambiente:

| Variabile             | Default        |
|-----------------------|----------------|
| `GDRCD_DB_HOST`       | `db`           |
| `GDRCD_DB_NAME`       | `gdrcd`        |
| `GDRCD_DB_USER`       | `gdrcd`        |
| `GDRCD_DB_PASSWORD`   | `gdrcd`        |
| `MARIADB_ROOT_PASSWORD` | `rootpassword` |

Le credenziali si modificano direttamente nel `docker-compose.yml`
oppure con un file `.env` accanto.

### Override della configurazione

L'entrypoint del container `web` genera ad ogni avvio
`includes/config-overrides.php` con i parametri di connessione presi
dalle variabili d'ambiente. Il file è auto-incluso da `config.inc.php`,
quindi sovrascrive i valori di default **senza** modificare il sorgente
versionato.

Esempio di file generato:

```php
<?php
// Generated by Docker entrypoint - do not edit by hand.
$PARAMETERS['database']['url']           = getenv('GDRCD_DB_HOST')     ?: 'db';
$PARAMETERS['database']['username']      = getenv('GDRCD_DB_USER')     ?: 'gdrcd';
$PARAMETERS['database']['password']      = getenv('GDRCD_DB_PASSWORD') ?: 'gdrcd';
$PARAMETERS['database']['database_name'] = getenv('GDRCD_DB_NAME')     ?: 'gdrcd';
```

In ambienti non-Docker è sufficiente creare manualmente lo stesso file
per override locali (resta gitignored).

### Reset completo del database

```bash
docker compose down -v
docker compose up -d
```

Il flag `-v` rimuove anche il volume `db_data`. Al riavvio sarà
necessario rieseguire `installer.php`.

### Comandi utili

```bash
# Log dell'applicazione
docker compose logs -f web

# Shell dentro il container PHP
docker compose exec web bash

# Connessione mysql al DB
docker compose exec db mariadb -u gdrcd -pgdrcd gdrcd

# Lint sintattico di un file PHP
docker compose exec web php -l pages/scheda.inc.php
```

### Composer / PSR-4 autoloader

GDRCD include un `composer.json` con autoloader PSR-4 mappato sul namespace
`GDRCD\` (directory `src/`). L'infrastruttura e' **additiva**: oggi non ci
sono dipendenze esterne e i `require_once` legacy in `includes/required.php`
continuano a funzionare. L'autoloader si attiva automaticamente non appena
`vendor/autoload.php` esiste.

Installare Composer (una volta sola, fuori o dentro il container):

```bash
# Sull'host (Debian/Ubuntu):
sudo apt-get install -y composer

# Oppure dentro il container web (immagine ufficiale di composer):
docker compose exec web bash -c "curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
```

Generare l'autoloader (non sono richieste dipendenze esterne):

```bash
docker compose exec web composer dump-autoload --optimize
```

Layout dei sorgenti namespaced:

```
src/
├── Db.php                       # GDRCD\Db
├── PasswordHash.php             # GDRCD\PasswordHash
├── AudioController.php          # GDRCD\AudioController
└── DbMigration/
    ├── Engine.php               # GDRCD\DbMigration\Engine
    └── Migration.php            # GDRCD\DbMigration\Migration
```

Le classi storiche in `includes/*.class.php` restano in piedi e definiscono
le stesse API nel namespace globale (`Db`, `DbMigration`, ecc.). Il nuovo
codice puo' gia' usare i FQN namespaced (`use GDRCD\Db;`) sfruttando
l'autoloader, mentre i ~100 call site esistenti continuano a funzionare
senza modifiche. La migrazione verso i namespace puo' avvenire in modo
incrementale.

---

## Architettura

Struttura ad alto livello della codebase:

```
GDRCD/
├── index.php                     # Homepage anonima (pre-login)
├── login.php / logout.php        # Autenticazione
├── main.php                      # Entry point delle pagine autenticate
├── popup.php                     # Dispatcher per i popup AJAX
├── installer.php                 # Bootstrap del database
├── config.inc.php                # Configurazione globale ($PARAMETERS)
├── header.inc.php / footer.inc.php
├── ref_header.inc.php            # Header con stato sessione
│
├── pages/                        # Viste richiamate via ?page=...
│   ├── scheda.inc.php            #   scheda PG
│   ├── servizi_*.inc.php         #   banca, mercato, gilde, esiti, ...
│   ├── gestione_*.inc.php        #   pannello staff
│   ├── chat/, forum/, messages/  #   moduli dedicati
│   └── frame_*.inc.php           #   frame della UI multi-pannello
│
├── includes/                     # Core dell'applicazione
│   ├── functions.inc.php         #   funzioni utility (DB, hash, filter)
│   ├── csrf.inc.php              #   gestione token CSRF
│   ├── icons.inc.php             #   helper rendering icone
│   ├── corefunctions.js          #   utility JS condivise
│   ├── PasswordHash.php          #   phpass legacy (compat. retroattiva)
│   ├── DbMigration/              #   engine di migrazione del DB
│   └── config-overrides.php      #   override DB (auto-generato in Docker)
│
├── layouts/                      # Shell di layout (colonne left/right/top/bottom)
│
├── themes/
│   ├── tailwind/
│   │   ├── input.css             #   sorgente del design system
│   │   └── output.css            #   bundle generato
│   ├── advanced/                 #   tema legacy (chat e main.css di fallback)
│   └── homepage/                 #   asset della homepage
│
├── db_versions/                  # Migrazioni incrementali del DB
│   └── YYYYMMDDHH_NomeClasse.php
├── gdrcd_db.sql                  # Schema baseline per nuove installazioni
│
├── docker/                       # Script container (entrypoint, watcher)
├── Dockerfile                    # Build multi-stage (tailwind + php)
├── docker-compose.yml            # Stack produzione/base
├── docker-compose.override.yml   # Override dev (bind mount + watcher)
├── tailwind.config.js            # Token e content paths Tailwind
├── vocabulary/                   # Stringhe localizzate (IT)
├── plugins/                      # Estensioni opzionali
└── docs/                         # Documentazione storica
```

Il routing è volutamente minimale: `main.php?page=<nome>` include il
file `pages/<nome>.inc.php` dopo aver applicato controlli di sessione,
permessi e CSRF.

---

## Design system (Tailwind)

Il file `themes/tailwind/input.css` definisce i token (colori
`gdrcd-bg`, `gdrcd-panel`, `gdrcd-accent`, ecc.) e una libreria di
componenti utility `gdrcd-*` da utilizzare in modo coerente in tutta
l'applicazione. Le classi più importanti:

| Categoria   | Classi principali                                                  |
|-------------|---------------------------------------------------------------------|
| Layout      | `gdrcd-shell`, `gdrcd-container`, `gdrcd-container-sm`             |
| Card        | `gdrcd-card`, `gdrcd-card-elev`, `gdrcd-card-header`, `gdrcd-card-body` |
| Tipografia  | `gdrcd-h1`, `gdrcd-h2`, `gdrcd-h3`, `gdrcd-prose`, `gdrcd-muted`, `gdrcd-eyebrow` |
| Bottoni     | `gdrcd-btn-primary`, `gdrcd-btn-secondary`, `gdrcd-btn-ghost`, `gdrcd-btn-danger` |
| Form        | `gdrcd-label`, `gdrcd-input`, `gdrcd-select`, `gdrcd-textarea`, `gdrcd-help` |
| Alert       | `gdrcd-alert-success`, `gdrcd-alert-error`, `gdrcd-alert-warning`, `gdrcd-alert-info` |
| Badge       | `gdrcd-badge-neutral`, `gdrcd-badge-accent`, `gdrcd-badge-success`, `gdrcd-badge-error` |
| Tabelle     | `gdrcd-table`                                                       |
| Navigazione | `gdrcd-nav-list`                                                    |

Ogni nuova pagina dovrebbe comporre la propria UI a partire da queste
classi, evitando stili inline o CSS ad hoc; il tema `advanced/` resta
caricato come fallback per la chat e per qualche schermata legacy non
ancora migrata.

---

## Sicurezza

Lo stato della sicurezza riflette il refactor in corso:

- **Password**: hash `bcrypt` via `password_hash(PASSWORD_BCRYPT)`
  (`gdrcd_password_hash`). La verifica (`gdrcd_password_verify`) è
  compatibile sia col nuovo formato che con il vecchio `phpass`,
  effettuando un **rehash trasparente** al primo login riuscito.
- **CSRF**: token random in sessione generato in `includes/csrf.inc.php`
  e validato fail-closed all'ingresso di `main.php`, `login.php` e
  `popup.php` tramite `gdrcd_csrf_guard()`. Tutti i form devono
  includere il campo nascosto generato da `gdrcd_csrf_field()`.
- **Rate limit login**: tabella `login_attempts` (migrazione
  `2026051112_GDRCDLoginAttempts`) registra i tentativi falliti per IP.
  Soglia: 5 tentativi in 5 minuti, dopodiché il login viene bloccato
  temporaneamente. Pulizia automatica ad ogni accesso riuscito.
- **XSS**: output sempre passato per `gdrcd_filter('out', ...)` o
  `htmlspecialchars()`. Le nuove pagine devono attenersi a questa regola.
- **Sessione**: cookie `HttpOnly` impostato a livello `php.ini`
  (`session.cookie_httponly=1` in `Dockerfile`).

**Roadmap sicurezza**: completare la migrazione di tutte le query a
prepared statement tramite gli helper `gdrcd_stmt`, `gdrcd_stmt_one`
e `gdrcd_stmt_all` (vedi `CONTRIBUTING.md`).

---

## API authentication (JWT)

Tutti gli endpoint `/api/*.inc.php` accettano due meccanismi di
autenticazione **in parallelo**:

1. **Sessione PHP** (`$_SESSION['login']`) — usata dal client web
   esistente, nessun cambiamento richiesto.
2. **JWT Bearer** (header `Authorization: Bearer <token>`) — destinata
   a client mobile, app native e integrazioni esterne stateless.

Il middleware `gdrcd_api_authenticate()` (in `includes/api-auth.inc.php`)
prova prima la sessione, poi il Bearer header. La libreria JWT è una
implementazione self-contained HS256 in `includes/jwt.inc.php`, **senza
dipendenze Composer**.

### Configurazione

In `config.inc.php`:

```php
$PARAMETERS['jwt']['secret']      = '';      // vuoto: auto-generata e salvata in config_settings
$PARAMETERS['jwt']['issuer']      = 'gdrcd';
$PARAMETERS['jwt']['exp_seconds'] = 86400;   // 24h
```

Lasciando `secret` vuoto, alla prima richiesta verso `/api/auth/login`
il sistema genera una chiave random a 64 byte (hex) e la persiste in
`config_settings.jwt_secret`. Per ambienti senza DB writable in
runtime (CI, immagini immutable) valorizzare `secret` esplicitamente.

### Endpoint

- `POST /api/auth/login.inc.php` — emette un nuovo token a partire
  da `{"login", "password"}`. Rispetta lo stesso rate-limit del web
  login (5 fallimenti / 5 min per IP, status 429).
- `POST /api/auth/refresh.inc.php` — accetta un token ancora valido
  (body `{"token": "..."}` o header `Authorization: Bearer`) e ne
  emette uno nuovo con TTL resettato. Token scaduti danno 401.

### Esempio (curl)

```bash
# 1. Login: ottieni token
curl -s -X POST http://gdrcd.test/api/auth/login.inc.php \
  -H 'Content-Type: application/json' \
  -d '{"login":"Alice","password":"segreta"}'
# => {"token":"eyJhbG...","token_type":"Bearer","expires_in":86400,"user":{"login":"Alice","permessi":1}}

# 2. Chiamata autenticata
curl -s http://gdrcd.test/api/presenti.inc.php \
  -H 'Authorization: Bearer eyJhbG...'

# 3. Refresh token prima della scadenza
curl -s -X POST http://gdrcd.test/api/auth/refresh.inc.php \
  -H 'Authorization: Bearer eyJhbG...'
```

### Note di sicurezza

- Algoritmo unico **HS256**. L'header `alg` viene verificato in
  decode: token con `alg: none` o asimmetrici vengono rifiutati.
- Firma verificata in tempo costante (`hash_equals`) per evitare
  timing attack.
- Claim `exp` obbligatorio: token senza scadenza vengono rifiutati.
- I permessi vengono **riletti dal DB** ad ogni richiesta autenticata
  via JWT: modifiche ai permessi del PG hanno effetto immediato senza
  attendere la scadenza del token.

---

## Migrazione database

Lo schema iniziale è in `gdrcd_db.sql` e viene caricato solo per
nuove installazioni. Tutte le evoluzioni successive avvengono come
migrazioni incrementali in `db_versions/`, secondo il pattern di nome
`YYYYMMDDHH_NomeClasse.php`. Ogni classe estende `DbMigration` ed
implementa i metodi `up()` e `down()`.

Documentazione completa (convenzioni, template e flusso operativo) in
[`db_versions/README.md`](db_versions/README.md), file di partenza per
nuove migrazioni in [`db_versions/_TEMPLATE.php`](db_versions/_TEMPLATE.php).

Per applicare manualmente le migrazioni (anche in CI o restore) e'
disponibile il wrapper CLI [`bin/gdrcd-migrate`](bin/gdrcd-migrate):

```bash
# Stato corrente (applicate vs pendenti)
bin/gdrcd-migrate --status

# Applica tutte le migrazioni pendenti
bin/gdrcd-migrate --up

# Rollback fino ad una migrazione specifica
bin/gdrcd-migrate --down=2026051112

# Dentro Docker
docker compose exec web bin/gdrcd-migrate --status
```

Lo stesso codice viene richiamato dall'installer web (`installer.php`)
al primo avvio. Le migrazioni vengono applicate dentro una transazione
quando possibile, ma le istruzioni DDL (CREATE/ALTER TABLE) provocano
un commit implicito in MySQL/MariaDB: il rollback ha effetti limitati
su quel tipo di operazioni — preferire sempre operazioni idempotenti.

---

## Backup & Restore

GDRCD include in `bin/` un piccolo set di strumenti shell/PHP per
salvare e ripristinare un'istanza completa (database + immagini caricate).
Tutto resta scritto in bash + PHP, senza dipendenze Python/Node, e
rileva automaticamente se sta girando dentro o fuori dai container
Docker (cerca un container in esecuzione di nome `gdrcd-db`).

### Cosa viene salvato

Un archivio `backups/gdrcd-<YYYYMMDD-HHMMSS>.tar.gz` contenente:

- `db.sql` — dump completo del database (mysqldump,
  `--single-transaction --routines --triggers --events --add-drop-table`)
- `imgs/items/` e `imgs/avatars/` (se presenti)
- `themes/<current_theme>/imgs/{items,locations,races,guilds}/`
- `manifest.json` — versione GDRCD, hash git, tema, lista file e dimensioni

Le credenziali del DB e il tema corrente vengono letti caricando
`config.inc.php` headlessly via `bin/gdrcd-backup-config.php`,
rispettando anche le sovrascritture in `includes/config-overrides.php`
(quindi le variabili d'ambiente Docker `GDRCD_DB_*`).

### Backup on-demand

```bash
# Backup standard nella directory ./backups/
bin/gdrcd-backup

# Output personalizzato
bin/gdrcd-backup --out=/var/backups/gdrcd --name=prod

# Modalità silenziosa (utile per script): emette solo il path dell'archivio
bin/gdrcd-backup --quiet
```

Esempio di output:

```
[gdrcd-backup] DB source: docker container gdrcd-db
[gdrcd-backup] staging in /tmp/gdrcd-backup-0N1tZy
[gdrcd-backup] dumping database 'gdrcd'...
[gdrcd-backup]   -> 41556 bytes
[gdrcd-backup]   + imgs/avatars
[gdrcd-backup]   + themes/advanced/imgs/items
[gdrcd-backup]   + themes/advanced/imgs/locations
[gdrcd-backup]   + themes/advanced/imgs/races
[gdrcd-backup]   + themes/advanced/imgs/guilds
[gdrcd-backup] creating /home/.../backups/gdrcd-20260511-174328.tar.gz
[gdrcd-backup] done: /home/.../backups/gdrcd-20260511-174328.tar.gz (70792 bytes)
/home/.../backups/gdrcd-20260511-174328.tar.gz
```

### Restore

```bash
# Modalità interattiva: stampa il manifest e chiede conferma esplicita
bin/gdrcd-restore backups/gdrcd-20260511-174328.tar.gz

# Senza prompt (CI, recovery automatica)
bin/gdrcd-restore backups/gdrcd-20260511-174328.tar.gz --yes

# Solo database, lasciando intatti gli asset
bin/gdrcd-restore backups/gdrcd-20260511-174328.tar.gz --yes --db-only

# Solo file di immagini, senza toccare il DB
bin/gdrcd-restore backups/gdrcd-20260511-174328.tar.gz --yes --files-only
```

Il restore stampa una riga per ciascuna immagine sovrascritta (`~ overwrite ...`)
o creata ex-novo (`+ new ...`). Il DB viene ripristinato sfruttando
le clausole `--add-drop-table` già presenti nel dump.

### Backup pianificato

Per una rotazione automatica esiste `bin/gdrcd-cron-backup`, che esegue un
backup e poi rimuove gli archivi più vecchi di 14 giorni (configurabile
via `GDRCD_BACKUP_RETENTION`). Esempio di crontab:

```cron
0 4 * * * cd /var/www/html && bin/gdrcd-cron-backup >> logs/backup.log 2>&1
```

### Limitazioni note

- Solo un database alla volta (quello configurato in `config.inc.php`).
- Nessun point-in-time recovery: il dump è un'istantanea logica.
- Le directory `giocate/` (storico chat) e `logs/` non sono incluse:
  vivono su volumi Docker dedicati e crescono indefinitamente.
- Il restore sovrascrive immagini con lo stesso path; eventuali file
  presenti solo sull'istanza live e non nel backup vengono **preservati**
  (nessuna pulizia distruttiva).

---

## PWA / Service Worker

GDRCD include un **Service Worker** vanilla JS e un **Web App Manifest** che
permettono di installare l'app su mobile (Android: "Aggiungi alla schermata
Home"; iOS Safari 16.4+: "Aggiungi a Home") e di mostrare una pagina di
fallback quando l'utente è offline. Non è una vera modalità "full offline":
i contenuti di gioco restano server-driven, ma la app shell (CSS, JS, icone)
viene servita dalla cache anche su rete instabile.

### File coinvolti

| Path                                | Ruolo                                                       |
|-------------------------------------|-------------------------------------------------------------|
| `manifest.webmanifest`              | Nome app, icone, `theme_color`, `start_url=/main.php`       |
| `service-worker.js`                 | Cache strategy (vedi sotto), versione `gdrcd-v1`            |
| `offline.html`                      | Pagina di fallback statica con stile inline                 |
| `includes/pwa.js`                   | Registrazione SW, install prompt, notifica updates          |

I `<link rel="manifest">` e i meta `theme-color` / `apple-mobile-web-app-*`
sono iniettati in `header.inc.php`, `index.php`, `login.php` e `logout.php`.

### Cache strategy

- **Navigazioni HTML** → *network-first* con fallback a `/offline.html`
  quando la rete fallisce.
- **Asset statici** (CSS, JS in `includes/*.js`, immagini, font, manifest)
  → *stale-while-revalidate* su una runtime cache.
- **Pre-cache** all'install: `output.css`, `favicon.ico`, i principali
  script in `includes/` e la pagina offline.
- **/api/*** e qualsiasi `*.php` non-navigation → **non intercettati**
  (rimangono request server-side, dipendono dalla sessione).

### Browser supportati

Service Worker è supportato da Chrome/Edge 40+, Firefox 44+, Safari 11.1+,
Opera 27+. Il codice degrada silenziosamente sui browser legacy
(controllo `'serviceWorker' in navigator`).

### Come testare

1. Apri il sito su Chrome/Firefox via **HTTPS o `http://localhost`**
   (i SW non funzionano su HTTP non locale).
2. DevTools → **Application** → *Manifest*: verifica icone, `start_url`,
   `theme_color`.
3. DevTools → **Application** → *Service Workers*: verifica che
   `service-worker.js` sia "activated and running".
4. DevTools → **Network** → spunta *Offline*, ricarica la pagina:
   dovrebbe apparire `offline.html`.
5. DevTools → **Application** → *Cache Storage*: dovresti vedere
   `gdrcd-v1-precache` e `gdrcd-v1-runtime`.

### Invalidare la cache

Per forzare il rinnovo degli asset modificare `CACHE_VERSION` in
`service-worker.js` (es. da `gdrcd-v1` a `gdrcd-v2`). Al prossimo caricamento
il nuovo SW entrerà in fase di waiting, eliminerà le cache versionate
precedenti e l'utente vedrà il banner "Nuova versione disponibile".

### TODO icone

Il manifest dichiara `icon-192.png` e `icon-512.png` in `imgs/`, ma per
ora **non sono presenti**: il browser ricade su `favicon.ico` (valido come
icona generica). Per ottenere il badge installabile completo su tutti i
device generare due PNG (192x192 e 512x512, sfondo `#a47e3b` con simbolo
GDRCD) e collocarli in `imgs/icon-192.png` e `imgs/icon-512.png`.

### Apache headers (opzionale)

Per evitare che proxy/browser cachino aggressivamente il SW conviene
aggiungere in `.htaccess` (se introdotto in futuro):

```apache
<FilesMatch "^service-worker\.js$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
</FilesMatch>
```

---

## Internazionalizzazione (i18n)

Tutte le stringhe di interfaccia visualizzate all'utente devono passare
dal vocabolario in `vocabulary/IT-it.vocabulary.php`, mai essere
hardcoded nei file PHP delle pagine.

- Il vocabolario popola un singolo array nidificato `$MESSAGE`, accessibile
  in tutto il codice dopo l'include di `header.inc.php`.
- Le stringhe ricorrenti dell'UI (azioni, stati vuoti, label di campi,
  feedback) vivono sotto `$MESSAGE['ui']` con sotto-categorie
  `actions`, `empty`, `feedback`, `fields`, `confirm`, `nav`.
- Pattern di accesso canonico:

  ```php
  <?= gdrcd_filter('out', $MESSAGE['ui']['actions']['save']) ?>
  ```

  `gdrcd_filter('out', ...)` applica l'escaping HTML in uscita; non
  serve aggiungere `htmlspecialchars` a valle.
- Per aggiungere una nuova lingua copiare `vocabulary/IT-it.vocabulary.php`
  in `vocabulary/<CODICE>.vocabulary.php` (es. `EN-en.vocabulary.php`)
  e tradurre i valori delle chiavi mantenendone struttura e nomi.

---

## Contribuire

Le linee guida complete sono in [`CONTRIBUTING.md`](CONTRIBUTING.md).
In breve:

1. **Coordinarsi su GitHub**: prendere in carico solo issue libere
   della milestone in corso e dichiarare la propria disponibilità
   nel commento della issue.
2. **Branch base**: lavorare su fork e branch dedicato; per la 5.7.0
   il branch base obbligatorio è `dev57`.
3. **Convenzione commit**: messaggi in italiano, sintetici e descrittivi.
4. **Query sicure**: usare sempre `gdrcd_stmt` / `gdrcd_stmt_one` /
   `gdrcd_stmt_all` per qualsiasi nuova query.
5. **Lint**: prima della PR verificare almeno `php -l` sui file toccati.
6. **CSS**: dopo modifiche al design system il container Tailwind
   ricostruisce `output.css` automaticamente; commitare anche il
   bundle aggiornato.
7. **PR**: apri la pull request sul repo upstream linkando la issue
   di riferimento e descrivendo le modifiche.

Per discussioni rapide è disponibile il canale
[Discord del progetto](https://discord.gg/zh69CDUf3V), ma ogni
decisione operativa va riportata sulla issue per tracciabilità.

---

## Licenza

GDRCD è distribuito sotto licenza
**Creative Commons Attribuzione - Condividi allo stesso modo 3.0**
(CC BY-SA 3.0). Testo completo della licenza:
<http://creativecommons.org/licenses/by-sa/3.0/deed.it>.

I termini specifici del progetto sono riportati in
[`license.md`](license.md).
