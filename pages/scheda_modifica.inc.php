<?php
/**
 * Scheda PG — modifica (utente + master).
 * 3 form: dati utente (self), status/salute (GUILDMODERATOR+), esilio (GAMEMASTER+).
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg     = $_REQUEST['pg'];
$op     = $_POST['op'] ?? null;
$alerts = [];

$confirm = true;

/* ============================================================
 * Handler POST
 * ============================================================ */
if ($op !== null) {

    // Check audio mime
    if (($PARAMETERS['mode']['allow_audio'] ?? 'OFF') === 'ON'
        && !empty($_POST['modifica_url_media'])) {
        $ext = '.' . strtolower(pathinfo($_POST['modifica_url_media'], PATHINFO_EXTENSION));
        if (!isset($PARAMETERS['settings']['audiotype'][$ext])) {
            $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['media_not_allowed'])];
            $confirm = false;
        }
    } elseif ($op === 'modify') {
        $_POST['modifica_url_media'] = '';
    }
}

if ($confirm && $op !== null) {
    $blocca_media = (strtolower($_POST['blocca_media'] ?? '') === 'on') ? 1 : 0;
    if ($_SESSION['login'] === $pg) {
        $_SESSION['blocca_media'] = $blocca_media;
    }

    if ($op === 'modify' && $pg === $_SESSION['login']) {
        $bbcode_on = (($PARAMETERS['mode']['user_bbcode'] ?? 'OFF') === 'ON');
        $free_html = (($PARAMETERS['settings']['bbd']['free_html'] ?? 'OFF') === 'ON'
                     && ($PARAMETERS['settings']['forum_bbcode']['type'] ?? '') === 'bbd');
        $filter_text = (!$bbcode_on || $free_html) ? 'addslashes' : 'in';

        $online_state = (($PARAMETERS['mode']['user_online_state'] ?? 'OFF') === 'ON')
            ? gdrcd_filter('in', $_POST['online_state'] ?? '')
            : '';

        gdrcd_query(
            "UPDATE personaggio SET
                cognome = '" . gdrcd_filter('in', $_POST['modifica_cognome'] ?? '') . "',
                storia = '" . gdrcd_filter($filter_text, $_POST['modifica_storia'] ?? '') . "',
                affetti = '" . gdrcd_filter($filter_text, $_POST['modifica_affetti'] ?? '') . "',
                descrizione = '" . gdrcd_filter($filter_text, $_POST['modifica_background'] ?? '') . "',
                url_media = '" . gdrcd_filter('in', gdrcd_filter('fullurl', $_POST['modifica_url_media'] ?? '')) . "',
                blocca_media = " . (int)$blocca_media . ",
                url_img = '" . gdrcd_filter('in', gdrcd_filter('fullurl', $_POST['modifica_url_img'] ?? '')) . "',
                url_img_chat = '" . gdrcd_filter('in', gdrcd_filter('fullurl', $_POST['modifica_url_img_chat'] ?? '')) . "',
                online_status = '" . $online_state . "'
             WHERE nome = '" . gdrcd_filter('in', $pg) . "'"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];

    } elseif ($op === 'modify_status' && (int)$_SESSION['permessi'] >= GUILDMODERATOR) {
        gdrcd_query(
            "UPDATE personaggio SET
                stato = '" . gdrcd_filter('in', $_POST['modifica_status'] ?? '') . "',
                salute = " . gdrcd_filter('num', $_POST['modifica_salute'] ?? 0) . "
             WHERE nome = '" . gdrcd_filter('in', $pg) . "'"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];

    } elseif ($op === 'exile' && (int)$_SESSION['permessi'] >= GAMEMASTER) {
        gdrcd_query(
            "UPDATE personaggio SET
                esilio = '" . gdrcd_filter('num', $_POST['year']) . '-'
                          . gdrcd_filter('num', $_POST['month']) . '-'
                          . gdrcd_filter('num', $_POST['day']) . "',
                data_esilio = NOW(),
                autore_esilio = '" . gdrcd_filter('in', $_SESSION['login']) . "',
                motivo_esilio = '" . gdrcd_filter('in', $_POST['causale'] ?? '') . "'
             WHERE nome = '" . gdrcd_filter('in', $pg) . "'
               AND permessi <= " . (int)$_SESSION['permessi']
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['error']['unknown_operation'] ?? 'Operazione non riconosciuta')];
    }
}

$record = gdrcd_query(
    "SELECT descrizione, storia, affetti, cognome, online_status, url_img, url_img_chat,
            url_media, blocca_media, stato, salute
     FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "'"
);

$lbl_mf = $MESSAGE['interface']['sheet']['modify_form'];
$lbl_m  = $MESSAGE['interface']['sheet']['menu'];
$is_self = ($_SESSION['login'] === $pg);
$is_gmod = ((int)$_SESSION['permessi'] >= GUILDMODERATOR);
$is_gm   = ((int)$_SESSION['permessi'] >= GAMEMASTER);

// Mirror each accumulated alert as a toast notification (additivo).
foreach ($alerts as [$kind, $msg]) {
    gdrcd_toast($kind, strip_tags($msg));
}
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['update']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $pg) ?></span>
        </h2>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($is_self): ?>
        <!-- Form utente: dati personali -->
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">I miei dati</h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=scheda_modifica" method="post" class="space-y-5">
                    <?= gdrcd_csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label" for="sm_cog"><?= gdrcd_filter('out', $lbl_mf['last_name']) ?></label>
                            <input class="gdrcd-input" type="text" id="sm_cog" name="modifica_cognome"
                                   value="<?= gdrcd_filter('out', $record['cognome']) ?>"/>
                        </div>
                        <?php if (($PARAMETERS['mode']['user_online_state'] ?? 'OFF') === 'ON'): ?>
                            <div>
                                <label class="gdrcd-label" for="sm_online"><?= gdrcd_filter('out', $lbl_mf['online_state']) ?></label>
                                <input class="gdrcd-input" type="text" id="sm_online" name="online_state"
                                       value="<?= gdrcd_filter('out', $record['online_status']) ?>" maxlength="100"/>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label class="gdrcd-label" for="sm_img"><?= gdrcd_filter('out', $lbl_mf['url_img']) ?></label>
                            <input class="gdrcd-input" type="url" id="sm_img" name="modifica_url_img"
                                   value="<?= gdrcd_filter('out', $record['url_img']) ?>"/>
                        </div>
                        <?php if (($PARAMETERS['mode']['chat_avatar'] ?? 'OFF') === 'ON'): ?>
                            <div>
                                <label class="gdrcd-label" for="sm_imgc"><?= gdrcd_filter('out', $lbl_mf['url_img_chat']) ?></label>
                                <input class="gdrcd-input" type="url" id="sm_imgc" name="modifica_url_img_chat"
                                       value="<?= gdrcd_filter('out', $record['url_img_chat']) ?>"/>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="sm_descr"><?= gdrcd_filter('out', $lbl_m['description']) ?></label>
                        <textarea class="gdrcd-textarea" id="sm_descr" name="modifica_background" rows="6"><?= gdrcd_filter('out', $record['descrizione']) ?></textarea>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="sm_sto"><?= gdrcd_filter('out', $lbl_mf['background']) ?></label>
                        <textarea class="gdrcd-textarea" id="sm_sto" name="modifica_storia" rows="8"><?= gdrcd_filter('out', $record['storia']) ?></textarea>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="sm_aff"><?= gdrcd_filter('out', $lbl_mf['relationships']) ?></label>
                        <textarea class="gdrcd-textarea" id="sm_aff" name="modifica_affetti" rows="5"><?= gdrcd_filter('out', $record['affetti']) ?></textarea>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                    </div>

                    <?php if (($PARAMETERS['mode']['allow_audio'] ?? 'OFF') === 'ON'): ?>
                        <div>
                            <label class="gdrcd-label" for="sm_med"><?= gdrcd_filter('out', $lbl_mf['url_media']) ?></label>
                            <input class="gdrcd-input" type="url" id="sm_med" name="modifica_url_media"
                                   value="<?= gdrcd_filter('out', $record['url_media']) ?>"/>
                        </div>
                    <?php endif; ?>

                    <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                        <input type="checkbox" name="blocca_media"
                               <?= !empty($record['blocca_media']) ? 'checked' : '' ?>
                               class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                        <span><?= gdrcd_filter('out', $lbl_mf['block_media']) ?></span>
                    </label>

                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <input type="hidden" name="op" value="modify"/>
                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($is_gmod): ?>
        <!-- Form master: status + salute -->
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Stato e salute (master)</h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=scheda_modifica" method="post" class="space-y-4">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <label class="gdrcd-label" for="sm_status"><?= gdrcd_filter('out', $lbl_mf['status']) ?></label>
                        <textarea class="gdrcd-textarea" id="sm_status" name="modifica_status" rows="4"><?= gdrcd_filter('out', $record['stato']) ?></textarea>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="sm_hp"><?= gdrcd_filter('out', $lbl_mf['healt']) ?></label>
                        <div class="flex items-center gap-2 max-w-xs">
                            <input class="gdrcd-input" type="number" id="sm_hp" name="modifica_salute"
                                   value="<?= (int)$record['salute'] ?>" min="0" max="100"/>
                            <span class="text-gdrcd-muted">/ 100</span>
                        </div>
                    </div>
                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <input type="hidden" name="op" value="modify_status"/>
                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($is_gm): ?>
        <!-- Form admin: esilio -->
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl_mf['exile']) ?></h3>
                <p class="gdrcd-muted text-xs">Imposta data fine esilio e motivazione.</p>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=scheda_modifica" method="post" class="space-y-4">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl_mf['exile']) ?> (data fine)</label>
                        <div class="grid grid-cols-3 gap-2 max-w-md">
                            <select class="gdrcd-select" name="day">
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?= $i ?>" <?= (int)date('d') === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select class="gdrcd-select" name="month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= (int)date('m') === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <select class="gdrcd-select" name="year">
                                <?php for ($i = (int)date('Y'); $i <= (int)date('Y') + 20; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="sm_causale"><?= gdrcd_filter('out', $lbl_mf['why_exiled']) ?></label>
                        <input class="gdrcd-input" type="text" id="sm_causale" name="causale" required/>
                    </div>
                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <input type="hidden" name="op" value="exile"/>
                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                        <button type="submit" class="gdrcd-btn-danger"
                                onclick="return confirm('Confermi esilio fino alla data selezionata?');">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Applica esilio
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

</div>
