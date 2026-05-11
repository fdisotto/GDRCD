# GDRCD - Docker setup

Stack containerizzato di GDRCD: PHP 8.2 + Apache, MariaDB 10.11, phpMyAdmin.

## Avvio rapido

```bash
docker compose up -d --build
```

Servizi esposti:

| Servizio    | URL                       |
|-------------|---------------------------|
| GDRCD       | http://localhost:8080     |
| phpMyAdmin  | http://localhost:8081     |
| MariaDB     | `localhost:3306`          |

## Prima installazione

1. Apri http://localhost:8080 → reindirizza a `installer.php`.
2. Clicca **Installa**: la `DbMigrationEngine` crea schema e dati.
3. Login con utente admin creato dalla migrazione (vedi `db_versions/`).

## Configurazione

Le credenziali DB sono iniettate via env nel container `web`:

| Variabile             | Default |
|-----------------------|---------|
| `GDRCD_DB_HOST`       | `db`    |
| `GDRCD_DB_NAME`       | `gdrcd` |
| `GDRCD_DB_USER`       | `gdrcd` |
| `GDRCD_DB_PASSWORD`   | `gdrcd` |

L'entrypoint genera `includes/config-overrides.php` ad ogni avvio sovrascrivendo i parametri di `config.inc.php` senza modificare il sorgente.

## Persistenza

- `db_data` — volume named, dati MariaDB.
- `giocate_data` — volume named, log chat (`giocate/`).
- `./imgs/avatars` — bind mount, avatar caricati dagli utenti.

## Comandi utili

```bash
# log applicazione
docker compose logs -f web

# shell nel container web
docker compose exec web bash

# reset completo (ATTENZIONE: cancella DB)
docker compose down -v
```

## Produzione

Prima del deploy:

- Cambia `MARIADB_ROOT_PASSWORD` e `GDRCD_DB_PASSWORD`.
- Rimuovi `phpmyadmin` o limitalo a rete interna.
- Non esporre `3306` pubblicamente.
- Metti Apache dietro reverse proxy con TLS.
