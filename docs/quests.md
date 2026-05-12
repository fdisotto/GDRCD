# Sistema Quest

Quest del gioco gestite dai GM e assegnate ai PG. Ogni PG vede solo le quest
a lui assegnate nella propria scheda.

## Concetti

- **Quest** — definizione testuale (titolo, descrizione, obiettivo,
  ricompensa) creata e curata dai GM. Riusabile: una quest può essere
  assegnata a molti PG.
- **Assegnazione** — legame quest ↔ PG con stato individuale
  (`attiva`, `completata`, `fallita`), timestamp di assegnazione e
  conclusione, note del GM. Una stessa quest non può essere assegnata
  due volte allo stesso PG (vincolo `UNIQUE` su `(id_quest, personaggio)`).
- **Attivazione quest** — flag `attiva` sulla definizione. Una quest
  disattivata resta visibile ai PG che la hanno già ricevuta, ma non
  può essere assegnata a nuovi PG.

## Accesso

| Ruolo | URL | Permessi richiesti |
|-------|-----|--------------------|
| GM / Admin | `main.php?page=gestione/quests` | `GAMEMASTER` (≥2) |
| Giocatore | `main.php?page=scheda_quest&pg=<nome>` | nessuno (vista pubblica) |

La pagina `scheda_quest` è linkata dal menu scheda PG (`pages/scheda/menu.inc.php`).

## Workflow GM

1. **Creare la quest**

   - `main.php?page=gestione/quests` → bottone "Nuova quest" in alto a destra.
   - Compila: titolo (obbligatorio), descrizione (obbligatoria, BBCode),
     obiettivo (opzionale, BBCode), ricompensa (opzionale, BBCode).
   - Submit → la quest appare in lista con stato Attiva.

2. **Filtri lista**

   Tab in alto: `Tutte` / `Attive` / `Disattivate`. Conta su badge.

3. **Modificare**

   Card della quest → `<details>` "Modifica quest" → form con i 4 campi
   ridiponibili. BBCode editor con anteprima su descrizione/obiettivo/
   ricompensa.

4. **Assegnare a un PG**

   Card della quest → `<details>` "Assegna a un PG" → autocomplete sui
   nomi PG dal DB. Nota iniziale opzionale. Se la quest è già assegnata
   al PG l'INSERT IGNORE non duplica e il GM riceve un warning.

5. **Gestire assegnatari**

   Card della quest → `<details>` "Lista assegnatari (N)". Per ciascuno:
   - Cambia stato: `Attiva` → `Completata` / `Fallita` / (riapri).
   - Modifica note GM.
   - Rimuovi assegnazione (`DELETE`, irreversibile).

   `conclusa_il` viene impostato a `NOW()` quando lo stato passa a
   completata/fallita, e a `NULL` se viene riportato a `attiva`.

6. **Disattivare / Riattivare la quest**

   Bottone nell'header della card. Non tocca le assegnazioni esistenti:
   blocca solo nuove assegnazioni e rappresenta la quest come
   "archiviata" lato GM.

## Vista PG

`main.php?page=scheda_quest&pg=<nome>` mostra:

- **Quest attive** — sempre espanse, ordinate per data assegnazione
  decrescente.
- **Quest concluse** — sezione collassabile, mostra esito (completata/
  fallita) + timestamp conclusione.

Per ogni quest il PG vede: titolo, descrizione, obiettivo, ricompensa.
Le note GM (`clgquestpg.note`) NON sono visualizzate al PG — sono
private del master.

La pagina è pubblica: ogni utente può visitare la scheda quest di un
altro PG (parte della "biografia" pubblica).

## Schema DB

Migrazione: `db_versions/2026051119_GDRCDQuests.php`.

```sql
quest (
  id_quest    INT AUTO_INCREMENT PRIMARY KEY,
  titolo      VARCHAR(255) NOT NULL,
  descrizione TEXT NOT NULL,
  obiettivo   TEXT NULL,
  ricompensa  TEXT NULL,
  autore      VARCHAR(50) NOT NULL,
  creata_il   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attiva      TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_attiva (attiva)
)

clgquestpg (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  id_quest      INT NOT NULL,
  personaggio   VARCHAR(50) NOT NULL,
  status        ENUM('attiva','completata','fallita') NOT NULL DEFAULT 'attiva',
  assegnata_il  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  conclusa_il   DATETIME NULL,
  note          TEXT NULL,
  UNIQUE KEY uq_quest_pg (id_quest, personaggio),
  INDEX idx_pg_status (personaggio, status),
  FOREIGN KEY (id_quest) REFERENCES quest(id_quest) ON DELETE CASCADE
)
```

`ON DELETE CASCADE`: cancellare la riga in `quest` rimuove anche tutte
le assegnazioni associate. Per "archiviare" senza perdere lo storico
usa il toggle `attiva` invece di eliminare la quest.

## File rilevanti

| File | Ruolo |
|------|-------|
| `pages/gestione/quests.inc.php` | Backoffice GM (CRUD + assegnazioni) |
| `pages/scheda_quest.inc.php`    | Vista pubblica scheda PG |
| `pages/scheda/menu.inc.php`     | Voce menu "Quest" |
| `db_versions/2026051119_GDRCDQuests.php` | Migrazione schema |

## Sicurezza

- Tutte le mutation (`create`, `edit`, `toggle`, `assign`, `conclude`,
  `unassign`) richiedono `permessi >= GAMEMASTER` (controllo all'inizio
  di `gestione/quests.inc.php`).
- CSRF validato centralmente da `main.php` su ogni POST.
- Tutti gli statement DB usano `Db::preparedExecute` / `Db::preparedFetch`
  (prepared statements, niente concatenazione di input utente in SQL).
- Vista PG (`scheda_quest`): query usa `gdrcd_filter('in', ...)` sul
  parametro `pg` — restrizione legacy, candidato refactor verso
  prepared statement.
- Testi `descrizione` / `obiettivo` / `ricompensa` sono renderizzati via
  `gdrcd_bbcoder(gdrcd_filter('out', $txt))`: prima escape HTML, poi
  whitelist BBCode → tag sicuri.

## Estensioni future

- Notifica PG quando una quest gli viene assegnata o cambiata di stato
  (riusare il canale `notifications` WebSocket).
- Reward automatici (XP / oggetti) collegati a `conclude → completata`.
- Tagging / categoria quest per filtro extra in lista GM.
