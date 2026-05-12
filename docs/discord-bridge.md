# Discord bridge

Il Discord bridge di GDRCD collega la chat di gioco a un canale Discord in entrambe le direzioni:

- **Outgoing**: i messaggi pubblici di gioco (P, A, M per default) vengono spediti a un webhook Discord configurato.
- **Inbound**: un piccolo bot Discord ascolta il canale e fa POST a un endpoint REST di GDRCD; i messaggi appaiono in una stanza dedicata come `tipo='O'` o `tipo='M'` sotto un mittente di sistema marcato `[Discord] <author>`.

Il bridge e' disabilitato di default. Va attivato esplicitamente dal pannello admin.

## File coinvolti

| File | Ruolo |
| --- | --- |
| `config.inc.php` | Default `$PARAMETERS['integrations']['discord']` |
| `includes/discord.inc.php` | Helper `gdrcd_discord_config()`, `gdrcd_discord_relay()`, `gdrcd_discord_generate_token()` |
| `ref_header.inc.php` | Hook outgoing dopo l'INSERT nel flusso `new_chat_message` |
| `api/discord-inbound.inc.php` | Endpoint POST per il bot Discord |
| `pages/gestione/discord.inc.php` | Admin UI (SUPERUSER) |
| `db_versions/2026051114_GDRCDConfigSettings.php` | Tabella `config_settings` per gli override runtime |

## Setup admin

1. Apri il pannello admin: **Gestione &raquo; Discord bridge** (`main.php?page=gestione/discord`).
2. Crea un webhook nel canale Discord (Impostazioni canale &raquo; Integrazioni &raquo; Webhook &raquo; Nuovo webhook). Copia l'URL.
3. Incolla il webhook URL nel form, scegli la stanza inbound (`bridge_room_id`), il nome PG di sistema e i tipi di chat da rilanciare, poi **Salva**.
4. Premi **Genera nuovo token**: il token mostrato e' visibile **una sola volta**, copialo subito nel `.env` del bot.
5. Premi **Invia messaggio di test** per verificare l'outgoing.
6. Abilita la spunta **Bridge abilitato** e salva: da questo momento i messaggi P/A/M (o quelli che hai scelto) vengono inoltrati a Discord.

I valori della UI sono persistiti in `config_settings` (chiavi `discord_enabled`, `discord_webhook_url`, `discord_incoming_token`, `discord_bridge_room_id`, `discord_bot_name`, `discord_relay_types`). I default di `config.inc.php` fungono da fallback se la migrazione non e' stata applicata.

## Endpoint inbound

```
POST <site>/api/discord-inbound.inc.php
Headers:
  Content-Type: application/json
  X-Discord-Token: <token generato dal pannello admin>
Body:
  {
    "author":  "Faber#1234",        // username Discord
    "content": "Ciao a tutti!",     // testo (max 1900 chars)
    "type":    "O"                  // opzionale, "O" (default) o "M"
  }
```

Risposte:

| Code | Body | Significato |
| --- | --- | --- |
| 200 | `{"ok":true,"id":12345}` | Inserito in chat |
| 400 | `{"ok":false,"error":"bad_request","detail":"..."}` | Body invalido |
| 401 | `{"ok":false,"error":"unauthorized"}` | Token mancante/errato |
| 405 | `{"ok":false,"error":"method_not_allowed"}` | Metodo != POST |
| 503 | `{"ok":false,"error":"disabled"}` | Bridge OFF lato GDRCD |

## Esempio bot Python

Dipende da [`discord.py`](https://discordpy.readthedocs.io/) e [`aiohttp`](https://docs.aiohttp.org/).

```python
import os, aiohttp, discord

TOKEN     = os.environ["DISCORD_BOT_TOKEN"]
CHANNEL   = int(os.environ["DISCORD_CHANNEL_ID"])
GDRCD_URL = os.environ["GDRCD_INBOUND_URL"]      # https://miosito/api/discord-inbound.inc.php
GDRCD_TOK = os.environ["GDRCD_INBOUND_TOKEN"]    # token generato dal pannello admin

intents = discord.Intents.default()
intents.message_content = True
client = discord.Client(intents=intents)

@client.event
async def on_message(message: discord.Message):
    if message.author.bot or message.channel.id != CHANNEL:
        return
    payload = {"author": message.author.display_name, "content": message.content, "type": "O"}
    headers = {"X-Discord-Token": GDRCD_TOK, "Content-Type": "application/json"}
    async with aiohttp.ClientSession() as session:
        async with session.post(GDRCD_URL, json=payload, headers=headers, timeout=10) as r:
            if r.status != 200:
                print("GDRCD bridge error:", r.status, await r.text())

client.run(TOKEN)
```

Avvialo come servizio (systemd, docker, supervisor, ...) e mantieni il token fuori dal codice sorgente.

## Note di sicurezza

- L'endpoint inbound autentica esclusivamente via `X-Discord-Token` (timing-safe compare). Non utilizza la sessione PHP, quindi non e' soggetto al CSRF guard.
- I default escludono le stanze private dall'outgoing (no leak di sessioni 1:1).
- Il webhook URL viene mascherato nell'admin UI; il token incoming non viene mai mostrato dopo la generazione.
- Errori di rete sono loggati come warning (`logs/`); l'invio outgoing non blocca mai il flusso chat (timeout cumulativo 5 s).
