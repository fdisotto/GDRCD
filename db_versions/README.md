# Database migrations

Questa cartella contiene le **migrazioni incrementali** dello schema del
database di GDRCD. Lo schema baseline per nuove installazioni e' in
`/gdrcd_db.sql`; tutte le evoluzioni successive vivono qui come file PHP
separati, applicati in ordine cronologico dall'engine
`includes/DbMigration/DbMigrationEngine.class.php`.

---

## Convenzione di naming

```
YYYYMMDDHH_NomeClasse.php
```

| Componente     | Regola                                                            |
|----------------|-------------------------------------------------------------------|
| `YYYYMMDDHH`   | Timestamp UTC al momento della creazione (anno/mese/giorno/ora). Serve da **migration id** e definisce l'ordine di applicazione. Deve essere unico. |
| `_`            | Separatore obbligatorio.                                          |
| `NomeClasse`   | PascalCase. Deve essere **identico** al nome della classe PHP definita dentro al file. |

Esempi reali:

```
2020072500_GDRCD551.php
2021103018_GDRCD56.php
2026051112_GDRCDLoginAttempts.php
2026051113_GDRCDPerformanceIndexes.php
```

> L'engine deduce automaticamente il `migration_id` dal prefisso numerico
> del nome del file — non serve impostarlo dentro la classe.

I file il cui nome **non** inizia con un id numerico (come `_TEMPLATE.php`
o questo `README.md`) vengono ignorati dall'engine.

---

## Struttura della classe

Ogni migrazione e' una classe PHP che:

1. Estende `DbMigration` (base class astratta).
2. Ha **lo stesso nome** del file (dopo il prefisso `YYYYMMDDHH_`).
3. Implementa `public function up()` — applica la modifica.
4. Implementa `public function down()` — annulla la modifica (rollback).

Scheletro minimale:

```php
<?php

class NomeClasse extends DbMigration
{
    public function up()
    {
        gdrcd_query("...");
    }

    public function down()
    {
        gdrcd_query("...");
    }
}
```

Un template pronto all'uso e' disponibile in
[`_TEMPLATE.php`](_TEMPLATE.php).

---

## Esecuzione delle migrazioni

L'engine `DbMigrationEngine` viene invocato in tre punti del codice:

| Trigger                         | Cosa fa                                                |
|---------------------------------|--------------------------------------------------------|
| `index.php` (homepage anonima)  | Se il DB non e' installato (`dbNeedsInstallation()`), redirige a `installer.php`. |
| `installer.php` (form web)      | All'invio del form chiama `DbMigrationEngine::updateDbSchema()` e applica tutte le migrazioni pendenti. |
| `bin/gdrcd-migrate` (CLI)       | Wrapper introdotto per uso manuale / automatizzato. Vedi sotto. |

**Non esiste un trigger automatico ad ogni boot**: ogni accesso a
`index.php` esegue `dbNeedsInstallation()`, ma le migrazioni successive
all'installazione iniziale sono applicate solo passando dall'installer
web o dalla CLI.

### CLI

```
bin/gdrcd-migrate [--status] [--up] [--down=<migration_id>]
```

| Flag                | Effetto                                                                   |
|---------------------|---------------------------------------------------------------------------|
| `--status`          | Lista le migrazioni disponibili indicando quali sono gia' state applicate.|
| `--up` (default)    | Applica tutte le migrazioni pendenti.                                     |
| `--down=<id>`       | Riporta il DB alla migrazione indicata, eseguendo `down()` delle versioni successive. |

Esempi:

```bash
# Stato corrente
bin/gdrcd-migrate --status

# Aggiorna alla versione piu' recente
bin/gdrcd-migrate --up

# Rollback fino alla migrazione 2026051112 (esegue down() delle versioni piu' recenti)
bin/gdrcd-migrate --down=2026051112
```

Dentro Docker:

```bash
docker compose exec web bin/gdrcd-migrate --status
# oppure
docker exec gdrcd-web bin/gdrcd-migrate --up
```

Il wrapper bash richiama internamente `bin/gdrcd-migrate-runner.php`, che
puo' anche essere eseguito direttamente:

```bash
php bin/gdrcd-migrate-runner.php --status
```

---

## Aggiungere una migrazione

1. Copia `_TEMPLATE.php` in `YYYYMMDDHH_NomeClasse.php`.
2. Rinomina la classe e implementa `up()` / `down()`.
3. Esegui `php -l db_versions/YYYYMMDDHH_NomeClasse.php` per verificare la sintassi.
4. Lancia `bin/gdrcd-migrate --up` (o passa per `installer.php`).
5. **Solo se la migrazione e' destinata anche a installazioni nuove**:
   aggiorna `gdrcd_db.sql` come descritto al punto seguente.

### Allineamento con `gdrcd_db.sql` (nuove installazioni)

Lo schema baseline `gdrcd_db.sql` viene caricato **una sola volta** per
le nuove installazioni e gia' contiene le tabelle nel loro stato finale.
Subito dopo definisce la tabella di tracciamento e fa un INSERT delle
migrazioni considerate "gia' applicate":

```sql
CREATE TABLE IF NOT EXISTS _gdrcd_db_versions (
  `migration_id` varchar(255) NOT NULL,
  `applied_on` DATETIME NOT NULL ,
  PRIMARY KEY (`migration_id`)
);

INSERT INTO _gdrcd_db_versions (migration_id, applied_on) VALUES
  ('2020072500', NOW()),
  ('2021103018', NOW()),
  ('2026051112', NOW()),
  ('2026051113', NOW());
```

Quando aggiungi una migrazione il cui effetto e' **gia' incluso** nello
schema baseline (perche' hai aggiornato direttamente `gdrcd_db.sql`),
devi:

1. Aggiungere la riga corrispondente nel `CREATE TABLE` / `INSERT` di
   `gdrcd_db.sql` (cosi' le nuove installazioni partono col DB allineato).
2. Aggiungere l'id della migrazione nell'INSERT su `_gdrcd_db_versions`
   (cosi' l'engine **non** la riapplichera' su una installazione nuova).

Se invece la migrazione modifica solo installazioni esistenti (es: aggiunta
di un indice, di una tabella che non e' nello schema baseline), basta
crearla in `db_versions/` senza toccare `gdrcd_db.sql`.

---

## Esempio completo

`db_versions/2026051112_GDRCDLoginAttempts.php` — aggiunge la tabella per
il rate-limit dei tentativi di login:

```php
<?php

class GDRCDLoginAttempts extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                username VARCHAR(50) NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                success TINYINT(1) NOT NULL DEFAULT 0,
                INDEX idx_login_attempts_ip_time (ip, attempted_at),
                INDEX idx_login_attempts_user_time (username, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS login_attempts");
    }
}
```

Punti chiave:

- File: `2026051112_GDRCDLoginAttempts.php` → `migration_id = 2026051112`.
- Classe `GDRCDLoginAttempts` = parte del nome file dopo `_`.
- `up()` usa `CREATE TABLE IF NOT EXISTS` per essere idempotente.
- `down()` usa `DROP TABLE IF EXISTS` per essere idempotente.

---

## Note sull'engine

- Le migrazioni vengono ordinate per `migration_id` numerico crescente
  ed eseguite dentro una `BEGIN TRANSACTION`. **Attenzione**: in
  MySQL/MariaDB le istruzioni DDL (CREATE/ALTER TABLE) provocano un
  commit implicito, quindi il rollback ha effetti limitati su quelle
  istruzioni. Una migrazione che fallisce a meta' di un `ALTER` potrebbe
  lasciare il DB in stato parzialmente modificato; preferire operazioni
  idempotenti (`IF NOT EXISTS`, controlli espliciti, ...).
- La tabella `_gdrcd_db_versions` viene creata automaticamente al primo
  accesso se non esiste gia'.
- Se l'engine trova un DB con tabelle ma senza tabella di tracciamento
  (installazione pre-5.6), assume che la prima migrazione storica sia
  gia' applicata e prosegue dalla seconda.
