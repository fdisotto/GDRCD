<?php
/**
 * Discord bridge - configurazione admin.
 *   main.php?page=gestione/discord
 *
 * Riservato a SUPERUSER. Permette di:
 *   - Attivare/disattivare il bridge.
 *   - Salvare webhook URL outgoing, stanza inbound, nome PG di sistema,
 *     tipi chat relayati.
 *   - Generare un nuovo token di autenticazione per il bot Discord.
 *   - Eseguire un test del webhook (invio di un messaggio "ping").
 *
 * I valori sono memorizzati in `config_settings` (chiavi `discord_*`); i
 * default di config.inc.php fungono da fallback se il DB non e' inizializzato
 * o se la migrazione `2026051114_GDRCDConfigSettings` non e' stata applicata.
 *
 * Il CSRF e' gia' validato centralmente in main.php su ogni POST.
 *
 * @see includes/discord.inc.php
 * @see api/discord-inbound.inc.php
 */

if (($_SESSION['permessi'] ?? 0) < SUPERUSER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

/* ------------------------------------------------------------------
 * Helpers locali
 * ------------------------------------------------------------------ */

/**
 * Lista delle stanze (mappa) disponibili come destinazione inbound.
 * Usiamo solo le mappe non private per evitare leak verso stanze 1:1.
 */
$rooms = [];
$rs = gdrcd_query("SELECT id, nome FROM mappa WHERE privata = 0 ORDER BY nome ASC", 'result');
while ($row = gdrcd_query($rs, 'fetch')) {
    $rooms[(int)$row['id']] = (string)$row['nome'];
}
gdrcd_query($rs, 'free');

/* ------------------------------------------------------------------
 * Handler POST
 * ------------------------------------------------------------------ */
$flash         = null;
$rotated_token = null; // valorizzato solo dopo rotate, per mostrare una volta sola.

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $op = (string)($_POST['op'] ?? '');

    switch ($op) {
        case 'save':
            $enabled        = !empty($_POST['enabled']);
            $webhook_url    = trim((string)($_POST['webhook_url'] ?? ''));
            $bridge_room_id = (int)($_POST['bridge_room_id'] ?? 0);
            $bot_name       = trim((string)($_POST['bot_name'] ?? ''));
            $relay_types_in = isset($_POST['relay_types']) && is_array($_POST['relay_types'])
                ? $_POST['relay_types']
                : [];

            // Valida webhook URL: deve essere https Discord (o vuoto).
            if ($webhook_url !== '') {
                if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                    $flash = ['kind' => 'warning', 'message' => 'Webhook URL non valido.'];
                    break;
                }
                $host = parse_url($webhook_url, PHP_URL_HOST);
                if (!is_string($host) || stripos($host, 'discord') === false) {
                    $flash = ['kind' => 'warning', 'message' => 'Il webhook deve puntare a un host Discord.'];
                    break;
                }
            }

            // Sanitizza relay_types: solo lettere singole valide nella chat.
            $allowed_types = ['P', 'A', 'M', 'O', 'N', 'C', 'D'];
            $relay_types   = [];
            foreach ($relay_types_in as $rt) {
                $rt = strtoupper(trim((string)$rt));
                if (in_array($rt, $allowed_types, true) && !in_array($rt, $relay_types, true)) {
                    $relay_types[] = $rt;
                }
            }
            if (empty($relay_types)) {
                $relay_types = ['P', 'A', 'M'];
            }

            // Stanza inbound: deve esistere fra quelle pubbliche caricate sopra
            // (o 0 se non ancora configurata + bridge spento).
            if ($bridge_room_id !== 0 && !isset($rooms[$bridge_room_id])) {
                $flash = ['kind' => 'warning', 'message' => 'La stanza inbound selezionata non esiste o e\' privata.'];
                break;
            }

            // Bot name: alfanumerico esteso, max 64.
            if ($bot_name === '') {
                $bot_name = 'Discord';
            }
            if (mb_strlen($bot_name) > 64) {
                $bot_name = mb_substr($bot_name, 0, 64);
            }

            $ok = true;
            $ok = gdrcd_config_set('discord_enabled', $enabled, 'bool') && $ok;
            $ok = gdrcd_config_set('discord_webhook_url', $webhook_url, 'string') && $ok;
            $ok = gdrcd_config_set('discord_bridge_room_id', $bridge_room_id, 'int') && $ok;
            $ok = gdrcd_config_set('discord_bot_name', $bot_name, 'string') && $ok;
            $ok = gdrcd_config_set('discord_relay_types', implode(',', $relay_types), 'string') && $ok;

            $flash = $ok
                ? ['kind' => 'success', 'message' => 'Configurazione Discord aggiornata.']
                : ['kind' => 'warning', 'message' => 'Alcuni valori non sono stati salvati (DB non disponibile?).'];
            break;

        case 'rotate_token':
            $token = gdrcd_discord_generate_token();
            if (gdrcd_config_set('discord_incoming_token', $token, 'string')) {
                $rotated_token = $token;
                $flash = [
                    'kind'    => 'success',
                    'message' => 'Nuovo token generato. Copialo subito: non sara\' piu\' mostrato dopo questa pagina.',
                ];
            } else {
                $flash = ['kind' => 'warning', 'message' => 'Impossibile salvare il nuovo token (DB?).'];
            }
            break;

        case 'test_webhook':
            $cfg_now = gdrcd_discord_config();
            if (empty($cfg_now['enabled']) || $cfg_now['webhook_url'] === '') {
                $flash = ['kind' => 'warning', 'message' => 'Webhook non configurato o bridge disattivato.'];
                break;
            }
            $ok = gdrcd_discord_relay(
                'M',
                $cfg_now['bot_name'] !== '' ? $cfg_now['bot_name'] : 'GDRCD',
                'Test webhook da pannello admin (' . date('Y-m-d H:i:s') . ').',
                'Admin'
            );
            $flash = $ok
                ? ['kind' => 'success', 'message' => 'Test inviato: controlla il canale Discord.']
                : ['kind' => 'warning', 'message' => 'Invio test fallito. Verifica log e webhook URL.'];
            break;

        default:
            // Nessuna operazione: passa.
            break;
    }
}

/* ------------------------------------------------------------------
 * Stato corrente da mostrare nel form
 * ------------------------------------------------------------------ */
$cfg = gdrcd_discord_config();

// URL inbound assoluto (best-effort, basato su host della richiesta corrente).
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host_h = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '';
$inbound_url = $host_h !== ''
    ? $scheme . '://' . $host_h . '/api/discord-inbound.inc.php'
    : '/api/discord-inbound.inc.php';

$all_types = ['P' => 'Parlato', 'A' => 'Azione', 'M' => 'Master', 'O' => 'Other', 'N' => 'PNG', 'C' => 'Caratteristica', 'D' => 'Dado'];

?>
<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Discord bridge</h2>
        <p class="gdrcd-muted">
            Inoltra i messaggi della chat di gioco verso un canale Discord (via webhook) e
            riceve i messaggi dal canale Discord come messaggi in chat di tipo <code>O</code> o <code>M</code>
            in una stanza dedicata.
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

    <?php if ($rotated_token !== null): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Nuovo token bot</h3>
                <p class="gdrcd-muted text-xs">
                    Copia ora questo token e impostalo nel bot Discord (header
                    <code>X-Discord-Token</code>). Per sicurezza non sara' piu' mostrato in chiaro.
                </p>
            </div>
            <div class="gdrcd-card-body">
                <pre class="text-xs p-3 rounded bg-gdrcd-muted-bg overflow-auto break-all"><?= htmlspecialchars($rotated_token) ?></pre>
            </div>
        </section>
    <?php endif; ?>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Configurazione</h3>
        </div>

        <form action="main.php?page=gestione/discord" method="post" class="gdrcd-card-body space-y-5">
            <?= gdrcd_csrf_field() ?>
            <input type="hidden" name="op" value="save">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 items-start pb-5 border-b border-gdrcd-border">
                <div>
                    <label class="gdrcd-label" for="cfg_enabled">Bridge abilitato</label>
                    <p class="gdrcd-muted text-xs mt-1">
                        Master switch: se OFF, nessun outgoing/inbound viene processato.
                    </p>
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="gdrcd-checkbox" id="cfg_enabled" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
                        <span class="text-sm text-gdrcd-text">Attivo</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 items-start pb-5 border-b border-gdrcd-border">
                <div>
                    <label class="gdrcd-label" for="cfg_webhook">Webhook URL Discord</label>
                    <p class="gdrcd-muted text-xs mt-1">
                        Lo crei dal canale Discord &raquo; Impostazioni &raquo; Integrazioni &raquo; Webhook.
                    </p>
                    <?php if ($cfg['webhook_url'] !== ''): ?>
                        <p class="text-xs text-gdrcd-subtle mt-1">
                            Attuale: <code><?= htmlspecialchars(gdrcd_discord_mask_webhook($cfg['webhook_url'])) ?></code>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="md:col-span-2">
                    <input type="url" class="gdrcd-input w-full" id="cfg_webhook" name="webhook_url"
                           placeholder="https://discord.com/api/webhooks/..."
                           value="<?= htmlspecialchars($cfg['webhook_url']) ?>">
                    <p class="gdrcd-muted text-xs mt-1">Lascia vuoto per non modificarlo? No: viene salvato esattamente cio' che inserisci. Per cancellarlo, svuota il campo.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 items-start pb-5 border-b border-gdrcd-border">
                <div>
                    <label class="gdrcd-label" for="cfg_room">Stanza inbound</label>
                    <p class="gdrcd-muted text-xs mt-1">
                        Mappa (stanza) in cui appariranno i messaggi ricevuti da Discord.
                    </p>
                </div>
                <div class="md:col-span-2">
                    <select class="gdrcd-select w-full" id="cfg_room" name="bridge_room_id">
                        <option value="0"<?= (int)$cfg['bridge_room_id'] === 0 ? ' selected' : '' ?>>&mdash; nessuna (inbound disabilitato) &mdash;</option>
                        <?php foreach ($rooms as $rid => $rname): ?>
                            <option value="<?= (int)$rid ?>"<?= (int)$cfg['bridge_room_id'] === (int)$rid ? ' selected' : '' ?>>
                                <?= htmlspecialchars($rname) ?> (#<?= (int)$rid ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 items-start pb-5 border-b border-gdrcd-border">
                <div>
                    <label class="gdrcd-label" for="cfg_botname">Nome PG di sistema</label>
                    <p class="gdrcd-muted text-xs mt-1">
                        Etichetta usata accanto ai messaggi inbound. Default: <code>Discord</code>.
                    </p>
                </div>
                <div class="md:col-span-2">
                    <input type="text" class="gdrcd-input w-full" id="cfg_botname" name="bot_name"
                           maxlength="64"
                           value="<?= htmlspecialchars($cfg['bot_name']) ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-6 items-start pb-5 border-b border-gdrcd-border">
                <div>
                    <label class="gdrcd-label">Tipi chat da rilanciare</label>
                    <p class="gdrcd-muted text-xs mt-1">
                        Solo i messaggi di questi tipi vengono inviati al webhook outgoing.
                    </p>
                </div>
                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <?php foreach ($all_types as $t => $label): ?>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" class="gdrcd-checkbox" name="relay_types[]" value="<?= htmlspecialchars($t) ?>"
                                   <?= in_array($t, $cfg['relay_types'], true) ? 'checked' : '' ?>>
                            <span class="text-sm text-gdrcd-text"><?= htmlspecialchars($t . ' - ' . $label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['ui']['actions']['save'] ?? 'Salva') ?>
                </button>
            </div>
        </form>
    </section>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Token bot inbound</h3>
            <p class="gdrcd-muted text-xs">
                Il bot Discord deve presentare questo token nell'header
                <code>X-Discord-Token</code> quando POSTa su <code><?= htmlspecialchars($inbound_url) ?></code>.
                Per sicurezza il valore non viene mai mostrato: ruotando il token l'attuale smette di funzionare.
            </p>
        </div>
        <div class="gdrcd-card-body space-y-3">
            <p class="text-sm text-gdrcd-muted">
                Stato attuale:
                <?php if ($cfg['incoming_token'] !== ''): ?>
                    <span class="text-gdrcd-text">configurato</span>
                    (lunghezza: <?= (int)strlen($cfg['incoming_token']) ?> chars).
                <?php else: ?>
                    <span class="text-red-600">non configurato</span>.
                <?php endif; ?>
            </p>
            <form action="main.php?page=gestione/discord" method="post">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="op" value="rotate_token">
                <button type="submit" class="gdrcd-btn-secondary">Genera nuovo token</button>
            </form>
        </div>
    </section>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Test webhook</h3>
            <p class="gdrcd-muted text-xs">
                Invia un messaggio di prova al canale Discord per verificare la connettivita'.
            </p>
        </div>
        <div class="gdrcd-card-body">
            <form action="main.php?page=gestione/discord" method="post">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="op" value="test_webhook">
                <button type="submit" class="gdrcd-btn-secondary"
                    <?= empty($cfg['enabled']) || $cfg['webhook_url'] === '' ? 'disabled' : '' ?>>
                    Invia messaggio di test
                </button>
            </form>
        </div>
    </section>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Endpoint inbound</h3>
        </div>
        <div class="gdrcd-card-body space-y-2 text-sm text-gdrcd-muted">
            <p>Il bot Discord deve POSTare a:</p>
            <pre class="text-xs p-3 rounded bg-gdrcd-muted-bg overflow-auto break-all"><?= htmlspecialchars($inbound_url) ?></pre>
            <p>
                Header richiesto: <code>X-Discord-Token: &lt;token&gt;</code>.
                Body JSON: <code>{"author":"...", "content":"...", "type":"O"}</code>.
                Vedi <code>docs/discord-bridge.md</code> per uno snippet bot di esempio.
            </p>
        </div>
    </section>

</div>
