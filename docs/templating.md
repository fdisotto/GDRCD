# Templating + Theme Override + Model Layer

Documento operativo per il refactor strutturale "à la WordPress" in corso.

Vedi anche:
- `docs/view-helpers.md` — API completa degli helper UI
- `docs/quests.md` — esempio di feature completa (model + view)

## Tre layer

```
pages/<feature>.inc.php           VIEW   (markup + chiamate helper)
src/Models/<Feature>.php          DATA   (SQL + business logic)
includes/{icons,view_helpers}.php HELPER (componenti UI riusabili)
themes/<slug>/views/<feature>.inc.php  OVERRIDE (shadow tema)
```

## Theme override

Ogni view caricata tramite `main.php?page=<x>` o `popup.php?page=<x>`
viene risolta da `gdrcd_pages_path()` in due step:

1. `themes/<theme_attivo>/views/<x>.inc.php` se esiste
2. fallback su `pages/<x>.inc.php`

Tema attivo: `$_SESSION['theme']` → `$PARAMETERS['themes']['current_theme']`.

### Creare un override

Esempio: tema `dark` vuole cambiare la pagina `messages/create`.

```bash
mkdir -p themes/dark/views/messages
cp pages/messages/create.inc.php themes/dark/views/messages/create.inc.php
# modifica solo themes/dark/views/messages/create.inc.php
```

Nessuna modifica al core. Lo switch tema lo cambia automaticamente.

### View loader programmatica

Se hai bisogno di renderizzare una view da codice (non da route),
usa `gdrcd_render($view, $vars)`:

```php
gdrcd_render('messages/create.inc.php', [
    'prefill_dest' => $name,
    'prefill_body' => $template,
]);
```

`$vars` viene estratto nello scope locale via `extract()`.

Per catturare output (utile dentro callable di `gdrcd_view_card`):

```php
$html = gdrcd_partial('partials/quest_card.inc.php', ['quest' => $q]);
```

Lo stesso lookup theme → pages → root viene applicato.

## Model layer

I view file **non** devono contenere SQL diretto. Tutto va in
`src/Models/<X>.php` (namespace `GDRCD\Models`).

### Convenzione

- Una classe per dominio (`Quest`, `Personaggio`, `Messaggi`, ...).
- Metodi statici (no DI ancora, vedi roadmap).
- Solo `Db::preparedFetch` / `preparedFetchAll` / `preparedExecute` /
  `preparedAffected`. No `gdrcd_query` con concatenazione.
- Ritorni tipizzati e documentati nel PHPDoc.

### Esempio: `src/Models/Quest.php`

```php
namespace GDRCD\Models;

use Db;

final class Quest {
    public const STATUSES = ['attiva', 'completata', 'fallita'];

    public static function counts(): array { ... }
    public static function listFiltered(string $tab = 'all'): array { ... }
    public static function find(int $id): ?array { ... }
    public static function create(...): bool { ... }
    public static function update(...): bool { ... }
    public static function toggle(int $id): bool { ... }
    public static function assign(int $idQuest, string $pg, string $note = ''): int { ... }
    public static function setStatus(int $rowId, string $status, string $note = ''): bool { ... }
    public static function unassign(int $rowId): bool { ... }
    public static function assignees(int $idQuest): array { ... }
    public static function forPg(string $pg): array { ... }
    public static function latestForPg(string $pg): ?array { ... }
}
```

### Uso dalla view

```php
use GDRCD\Models\Quest;

if ($op === 'create') {
    Quest::create($titolo, $descr, $obiet, $ric, $autore);
}

$counts    = Quest::counts();
$quest_list = Quest::listFiltered($tab);
```

### Autoload

`composer.json` ha PSR-4 `GDRCD\\` → `src/`. Subnamespace come
`GDRCD\Models\` mappano su `src/Models/`. Nessuna `require` manuale.

## Page header / card / form helpers

Vedi `docs/view-helpers.md` per dettagli. Esempio sintetico:

```php
use GDRCD\Models\Quest;

$flash      = null;
$counts     = Quest::counts();
$quest_list = Quest::listFiltered($_REQUEST['tab'] ?? 'all');
?>

<div class="space-y-6">
    <?= gdrcd_view_page_header('Gestione quest', 'Crea, modifica, assegna.',
        ['icon' => 'journal']) ?>

    <?php if ($flash): ?>
        <?= gdrcd_view_alert($flash['kind'], $flash['message']) ?>
    <?php endif; ?>

    <?php foreach ($quest_list as $q): ?>
        <?= gdrcd_view_card($q['titolo'], gdrcd_partial(
            'partials/quest_row.inc.php', ['q' => $q]
        ), ['icon' => 'flag']) ?>
    <?php endforeach; ?>
</div>
```

## File rilevanti

| File | Ruolo |
|------|-------|
| `includes/functions.inc.php` | `gdrcd_pages_path()` con theme override |
| `includes/view_helpers.inc.php` | `gdrcd_render`, `gdrcd_partial`, alert, card, form, button |
| `includes/icons.inc.php` | `gdrcd_icon()` + registry SVG |
| `src/Models/Quest.php` | Esempio model con CRUD + query lookup |
| `composer.json` | PSR-4 autoload `GDRCD\\` → `src/` |

## Pagine refactorate come prova

- `pages/gestione/quests.inc.php` — usa `Quest::*` per CRUD, lista,
  conteggi; usa `gdrcd_view_page_header`, `gdrcd_field_*`,
  `gdrcd_form_open/close`, `gdrcd_alert_*`, `gdrcd_icon`.
- `pages/scheda_quest.inc.php` — usa `Quest::forPg()`.
- `pages/log_chat.inc.php` — usa `gdrcd_alert_*`, `gdrcd_icon`.
- `pages/scheda_print.inc.php` — usa `gdrcd_alert_error`, `gdrcd_icon`.
- `api/notifications.inc.php` — usa `Quest::latestForPg()`.
- `src/WebSocket/ChatHandler.php` — usa `Quest::latestForPg()`.

## Convenzione di migrazione

**"At touch"**: quando tocchi un file per altri motivi (bugfix /
feature), porta avanti anche il refactor di quel file. Niente big
bang. Le pagine non toccate continuano a funzionare invariate.

Checklist refactor "at touch":
1. SVG inline → `gdrcd_icon('<name>')`
2. Alert HTML → `gdrcd_alert_*` / `gdrcd_view_alert`
3. Form HTML → `gdrcd_form_open/_close` + `gdrcd_field_*`
4. SQL inline → estrai in `src/Models/<Feature>::*`
5. Header h1 → `gdrcd_view_page_header`

## Roadmap successiva

1. Hook system (`Hooks::action()`, `Hooks::filter()`) — base per plugin.
2. Plugin manifest + loader (`plugins/<slug>/plugin.json`).
3. Theme manifest (`themes/<slug>/theme.json`) + admin UI.
4. Plugin marketplace remoto.

Vedi conversazione del 12/05/2026 per il brainstorm completo.
