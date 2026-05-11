<?php
/**
 * Gestione configurazioni "feature gate"
 *   main.php?page=gestione/configurazioni
 *
 * Permette agli amministratori (SUPERUSER) di modificare via UI i valori
 * delle vecchie costanti hardcoded di constant_values.inc.php
 * (ROLE_PERM, LOG_PERM, EDIT_PERM, SEND_GM, SAVE_ROLE, REG_MIN_AZIONI).
 * I valori sono memorizzati nella tabella `config_settings` e letti dal
 * codice tramite gli helper gdrcd_role_perm(), gdrcd_log_perm(), ecc.
 *
 * Il CSRF è già validato centralmente in main.php su ogni POST.
 */

if (($_SESSION['permessi'] ?? 0) < SUPERUSER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

/* ------------------------------------------------------------------
 * Definizione degli setting esposti dall'UI.
 * Ordine, label e descrizione sono mantenuti qui (non nel DB) per
 * controllare la presentazione senza migrazioni aggiuntive.
 * ------------------------------------------------------------------ */

$role_choices = [
    USER           => 'USER (0)',
    GUILDMODERATOR => 'GUILDMODERATOR (1)',
    GAMEMASTER     => 'GAMEMASTER (2)',
    MODERATOR      => 'MODERATOR (3)',
    SUPERUSER      => 'SUPERUSER (4)',
];

$settings_schema = [
    'role_perm' => [
        'label'    => 'Permessi gestione registrazioni role',
        'desc'     => 'Livello minimo per accedere alla lista delle giocate dei PG e alle segnalazioni GM.',
        'type'     => 'role',
        'fallback' => defined('ROLE_PERM') ? (int)ROLE_PERM : GAMEMASTER,
    ],
    'log_perm' => [
        'label'    => 'Permessi accesso log chat',
        'desc'     => 'Livello minimo per visualizzare i log chat (incluso il log di una specifica giocata).',
        'type'     => 'role',
        'fallback' => defined('LOG_PERM') ? (int)LOG_PERM : GAMEMASTER,
    ],
    'edit_perm' => [
        'label'    => 'Permessi modifica registrazioni',
        'desc'     => 'Livello minimo per modificare registrazioni role oltre la soglia temporale standard (30 giorni).',
        'type'     => 'role',
        'fallback' => defined('EDIT_PERM') ? (int)EDIT_PERM : GAMEMASTER,
    ],
    'send_gm' => [
        'label'    => 'Funzione "Segnala ai Master"',
        'desc'     => 'Abilita la possibilità per i giocatori di inviare segnalazioni di giocate ai Master.',
        'type'     => 'bool',
        'fallback' => defined('SEND_GM') ? (bool)SEND_GM : true,
    ],
    'save_role' => [
        'label'    => 'Download HTML delle giocate',
        'desc'     => 'Abilita il salvataggio offline (file .html) delle giocate concluse.',
        'type'     => 'bool',
        'fallback' => defined('SAVE_ROLE') ? (bool)SAVE_ROLE : true,
    ],
    'reg_min_azioni' => [
        'label'    => 'Azioni minime per registrazione',
        'desc'     => 'Numero minimo di azioni necessarie per validare una registrazione di giocata.',
        'type'     => 'int',
        'fallback' => defined('REG_MIN_AZIONI') ? (int)REG_MIN_AZIONI : 4,
        'min'      => 1,
        'max'      => 999,
    ],
];

/* ------------------------------------------------------------------
 * Handler POST: salva i nuovi valori.
 * CSRF già controllato da main.php.
 * ------------------------------------------------------------------ */

$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'save') {
    $saved = 0;
    $errors = [];

    foreach ($settings_schema as $key => $meta) {
        if (!array_key_exists($key, $_POST)) {
            continue;
        }
        $raw = $_POST[$key];

        switch ($meta['type']) {
            case 'role':
                $val = (int)$raw;
                if (!array_key_exists($val, $role_choices)) {
                    $errors[] = 'Valore non valido per ' . $meta['label'] . '.';
                    continue 2;
                }
                $ok = gdrcd_config_set($key, $val, 'int');
                break;

            case 'int':
                $val = (int)$raw;
                if (isset($meta['min']) && $val < $meta['min']) $val = (int)$meta['min'];
                if (isset($meta['max']) && $val > $meta['max']) $val = (int)$meta['max'];
                $ok = gdrcd_config_set($key, $val, 'int');
                break;

            case 'bool':
            default:
                // checkbox: presente == true. Per essere robusti accettiamo anche '1' / 'on'.
                $val = in_array(strtolower((string)$raw), ['1', 'on', 'true', 'yes', 'y'], true);
                $ok = gdrcd_config_set($key, $val, 'bool');
                break;
        }

        if ($ok) {
            $saved++;
        } else {
            $errors[] = 'Salvataggio fallito per ' . $meta['label'] . '.';
        }
    }

    // Le bool non inviate dal form (checkbox unchecked) vanno comunque normalizzate a 0.
    foreach ($settings_schema as $key => $meta) {
        if ($meta['type'] !== 'bool') continue;
        if (array_key_exists($key, $_POST)) continue;
        if (gdrcd_config_set($key, false, 'bool')) {
            $saved++;
        } else {
            $errors[] = 'Salvataggio fallito per ' . $meta['label'] . '.';
        }
    }

    $flash = [
        'kind'    => empty($errors) ? 'success' : 'warning',
        'message' => empty($errors)
            ? 'Configurazione aggiornata (' . $saved . ' valori salvati).'
            : implode(' ', $errors),
    ];
}

/* ------------------------------------------------------------------
 * Lettura valori correnti (post-save, così riflettono lo stato fresco).
 * ------------------------------------------------------------------ */

$current = [];
foreach ($settings_schema as $key => $meta) {
    $current[$key] = gdrcd_config_get($key, $meta['fallback']);
}

/* ------------------------------------------------------------------
 * Helper di rendering
 * ------------------------------------------------------------------ */

$render_field = function (string $key, array $meta, $value) use ($role_choices) {
    switch ($meta['type']) {
        case 'role':
            $val = (int)$value;
            $html  = '<select class="gdrcd-select w-full" id="cfg_' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '">';
            foreach ($role_choices as $v => $label) {
                $sel = ($v === $val) ? ' selected' : '';
                $html .= '<option value="' . (int)$v . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
            }
            $html .= '</select>';
            return $html;

        case 'int':
            $min = isset($meta['min']) ? ' min="' . (int)$meta['min'] . '"' : '';
            $max = isset($meta['max']) ? ' max="' . (int)$meta['max'] . '"' : '';
            return '<input type="number" class="gdrcd-input w-full" id="cfg_' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '"'
                 . $min . $max
                 . ' value="' . (int)$value . '">';

        case 'bool':
        default:
            $checked = $value ? ' checked' : '';
            return '<label class="inline-flex items-center gap-2 cursor-pointer">'
                 . '<input type="checkbox" class="gdrcd-checkbox" id="cfg_' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '" value="1"' . $checked . '>'
                 . '<span class="text-sm text-gdrcd-text">Abilitato</span>'
                 . '</label>';
    }
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Configurazioni di sistema</h2>
        <p class="gdrcd-muted">
            Modifica i parametri di permesso e le feature flag che prima erano costanti hardcoded
            in <code class="text-xs px-1 py-0.5 rounded bg-gdrcd-muted-bg">includes/constant_values.inc.php</code>.
            I valori vengono letti runtime dalla tabella <code class="text-xs px-1 py-0.5 rounded bg-gdrcd-muted-bg">config_settings</code>.
        </p>
    </header>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Feature gate</h3>
            <p class="gdrcd-muted text-xs">
                Le modifiche hanno effetto al prossimo caricamento di pagina.
                I valori sostituiscono le costanti tramite gli helper
                <code>gdrcd_role_perm()</code>, <code>gdrcd_log_perm()</code>,
                <code>gdrcd_edit_perm()</code>, <code>gdrcd_send_gm()</code>,
                <code>gdrcd_save_role()</code>, <code>gdrcd_reg_min_azioni()</code>.
            </p>
        </div>

        <form action="main.php?page=gestione/configurazioni" method="post" class="gdrcd-card-body space-y-5">
            <?= gdrcd_csrf_field() ?>
            <input type="hidden" name="op" value="save">

            <div class="space-y-5">
                <?php foreach ($settings_schema as $key => $meta): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 items-start pb-5 border-b border-gdrcd-border last:border-0 last:pb-0">
                        <div class="md:col-span-1">
                            <label class="gdrcd-label" for="cfg_<?= htmlspecialchars($key) ?>">
                                <?= htmlspecialchars($meta['label']) ?>
                            </label>
                            <p class="gdrcd-muted text-xs mt-1"><?= htmlspecialchars($meta['desc']) ?></p>
                            <p class="text-xs text-gdrcd-subtle mt-1">
                                Chiave: <code><?= htmlspecialchars($key) ?></code>
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <?= $render_field($key, $meta, $current[$key]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Salva configurazione
                </button>
            </div>
        </form>
    </section>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Note tecniche</h3>
        </div>
        <div class="gdrcd-card-body space-y-2 text-sm text-gdrcd-muted">
            <p>
                Le costanti PHP originarie (<code>ROLE_PERM</code>, <code>LOG_PERM</code>, ...) restano definite
                in <code>includes/constant_values.inc.php</code> e fungono da fallback se la tabella
                <code>config_settings</code> non esiste o la chiave manca.
            </p>
            <p>
                I valori del DB hanno priorità sui default delle costanti, ma <strong>solo</strong> nelle parti
                di codice già migrate ai nuovi helper. Le sezioni che usano ancora la costante mantengono il comportamento attuale.
            </p>
        </div>
    </section>

</div>
