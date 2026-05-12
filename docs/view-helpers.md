# View Helpers — convenzione

Helper UI riusabili per togliere HTML duplicato dalle view.

Primo step di un percorso verso templating/plugin system (vedi roadmap).

## Stato

- `includes/icons.inc.php` — registry icone SVG generiche + funzione
  `gdrcd_icon($name, $cls, $opts)`.
- `includes/view_helpers.inc.php` — `gdrcd_view_alert($kind, $msg, $opts)`
  + shortcut `gdrcd_alert_error/_warning/_success/_info($msg, $opts)`.
- Caricati automaticamente da `includes/required.php`.

## Convenzione

| Vecchio | Nuovo |
|---------|-------|
| `<svg ...><path d="M5 13l4 4L19 7"/></svg>` (check) | `gdrcd_icon('check', 'w-4 h-4')` |
| `<div class="gdrcd-alert-error">... SVG ... $msg ...</div>` | `gdrcd_alert_error($msg)` |

Tutti gli helper **return string**, non echo. Si usano come:

```php
echo gdrcd_alert_warning('Operazione fallita.');
echo gdrcd_icon('search', 'w-4 h-4 text-gdrcd-accent');
```

Per HTML pre-renderizzato come messaggio (es. `gdrcd_filter('out', $x)`)
passa `['raw' => true]` agli alert per evitare doppio escape:

```php
echo gdrcd_alert_error(gdrcd_filter('out', $msg), ['raw' => true]);
```

## Icone disponibili

Vedi `_gdrcd_icon_paths()` in `includes/icons.inc.php`. Categorie:

- **Feedback**: `alert-warning`, `alert-info`, `check`, `check-circle`,
  `x`, `x-circle`
- **Azioni**: `plus`, `minus`, `search`, `pencil`, `pencil-square`,
  `trash`, `save`, `send`, `printer`, `refresh`, `upload`, `download`,
  `filter`, `cog`, `cog-dot`
- **Navigazione**: `arrow-left/right/up/down`, `chevron-left/right`,
  `home`
- **Persone**: `user`, `users`, `user-group`, `user-circle`
- **Varie**: `mail`, `chat-bubble`, `clock`, `calendar`, `eye`,
  `eye-off`, `shield`, `bolt`, `star`, `sparkles`, `bell`, `lock`,
  `key`, `ban`, `book`, `map`, `tag`, `cart`, `chart-bar`, `document`,
  `menu`
- **Gioco**: `flag`, `puzzle`, `beaker`, `compass`

Aggiungere un'icona: aggiungi una entry in `_gdrcd_icon_paths()` con
il path SVG (24x24 outline). Valore stringa o array di path.

## Migrazione codice esistente

Non rifare in massa: refactor "at touch" — quando tocchi un file per
altri motivi, sostituisci anche i blocchi alert/icon. Le pagine non
toccate continuano a funzionare (helper additivi, non breaking).

## Test rapidi gia' migrati

- `pages/gestione/quests.inc.php` (flash + alert permessi)
- `pages/log_chat.inc.php` (5 alert + 3 icone)
- `pages/scheda_quest.inc.php` (alert PG inesistente)
- `pages/scheda_print.inc.php` (icona stampante + alert errori)

## Roadmap successiva

1. `gdrcd_view_card($title, $body_callable, $opts)` — wrapper card riusabile.
2. `gdrcd_field_text/_select/_textarea` — form fields con label/help/error.
3. `gdrcd_form_open/_close` — CSRF auto-inject.
4. `gdrcd_button/_link` — varianti normalizzate.
5. `gdrcd_render($view, $vars)` — view loader con override theme.
6. Model layer `src/Models/<X>.php` — niente piu' SQL nelle view.

Vedi brainstorm completo in conversazione del 12/05/2026.
