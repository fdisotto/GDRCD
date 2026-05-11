<?php
/**
 * Handler POST: aggiunge un singolo esito a una serie già esistente.
 * Inviato dai form new.php (esito narrativo) e new_chat.php (esito in chat con CD).
 */

if (($_POST['op'] ?? '') !== 'add') {
    return;
}

$load_blocco = gdrcd_query(
    "SELECT pg, master, titolo, closed FROM blocco_esiti
     WHERE id = '" . gdrcd_filter('num', $_POST['id']) . "' LIMIT 1"
);

if (!empty($load_blocco['closed']) && (int)$load_blocco['closed'] === 1):
?>
<div class="gdrcd-shell">
    <div class="gdrcd-container-sm">
        <section class="gdrcd-card">
            <div class="gdrcd-card-body text-center space-y-4 py-8">
                <span class="gdrcd-icon-circle bg-gdrcd-warning-soft text-gdrcd-warning border-amber-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                </span>
                <h2 class="gdrcd-h2">Serie chiusa</h2>
                <p class="gdrcd-muted">Questa serie di esiti è al momento chiusa e non accetta nuovi esiti.</p>
                <div>
                    <a href="main.php?page=gestione_segnalazioni&segn=esiti_master" class="gdrcd-btn-primary">
                        Torna alla lista
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>
<?php
    return;
endif;

$note  = empty($_POST['note']) ? 'Nessuna' : gdrcd_filter('in', $_POST['note']);
$chat  = empty($_POST['chat'])      ? 0 : gdrcd_filter('num', $_POST['chat']);
$ab    = empty($_POST['id_ab'])     ? 0 : gdrcd_filter('num', $_POST['id_ab']);
$facce = empty($_POST['dice_face']) ? 0 : gdrcd_filter('num', $_POST['dice_face']);
$num   = empty($_POST['dice_num'])  ? 0 : gdrcd_filter('num', $_POST['dice_num']);

if ($num > 0 && $facce > 0) {
    $tiri = [];
    for ($i = 0; $i < $num; $i++) {
        $tiri[$i] = mt_rand(1, $facce);
    }
    $dice_res = implode(',', $tiri);
} else {
    $dice_res = '0';
}

$master = gdrcd_filter('in', $_SESSION['login']);

gdrcd_query(
    "INSERT INTO esiti (titolo, pg, autore, contenuto, noteoff, id_ab, chat, CD_1, CD_2, CD_3, CD_4,
                       id_blocco, master, dice_face, dice_num, dice_results, letto_master) VALUES ("
    . "'" . gdrcd_filter('in', $_POST['titolo']) . "',"
    . "'" . gdrcd_filter('in', $load_blocco['pg']) . "',"
    . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
    . "'" . gdrcd_filter('in', $_POST['contenuto'] ?? '') . "',"
    . "'" . $note . "',"
    . (int)$ab . ", " . (int)$chat . ","
    . "'" . gdrcd_filter('in', $_POST['CD_1'] ?? '') . "',"
    . "'" . gdrcd_filter('in', $_POST['CD_2'] ?? '') . "',"
    . "'" . gdrcd_filter('in', $_POST['CD_3'] ?? '') . "',"
    . "'" . gdrcd_filter('in', $_POST['CD_4'] ?? '') . "',"
    . gdrcd_filter('num', $_POST['id']) . ","
    . "'" . $master . "',"
    . gdrcd_filter('num', $facce) . ","
    . gdrcd_filter('num', $num) . ","
    . "'" . gdrcd_filter('in', $dice_res) . "', 1)"
);

// Aggiorna master del blocco se non assegnato
if ($load_blocco['master'] === '0'
    && $_SESSION['permessi'] >= ESITI_PERM
    && $load_blocco['pg'] !== $_SESSION['login']) {
    gdrcd_query(
        "UPDATE blocco_esiti SET master = '" . gdrcd_filter('in', $_SESSION['login']) . "'
         WHERE id = " . gdrcd_filter('num', $_POST['id'])
    );
}

$back_url = 'main.php?' . http_build_query(['page' => 'gestione_segnalazioni', 'segn' => 'esiti_master']);
?>
<div class="gdrcd-shell">
    <div class="gdrcd-container-sm">
        <section class="gdrcd-card">
            <div class="gdrcd-card-body text-center space-y-4 py-8">
                <span class="gdrcd-icon-circle bg-gdrcd-success-soft text-gdrcd-success border-green-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $MESSAGE['warning']['inserted']) ?></h2>
                <p class="gdrcd-muted">
                    Esito aggiunto alla serie
                    <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $load_blocco['titolo']) ?></strong>.
                </p>
                <?php if ($num > 0 && $facce > 0): ?>
                    <div class="text-sm text-gdrcd-muted">
                        Tiro <?= (int)$num ?>d<?= (int)$facce ?>:
                        <strong class="text-gdrcd-text font-mono"><?= htmlspecialchars($dice_res) ?></strong>
                    </div>
                <?php endif; ?>
                <div class="pt-2">
                    <a href="<?= htmlspecialchars($back_url) ?>" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        Torna alla lista
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>
