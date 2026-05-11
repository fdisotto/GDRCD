<?php
/**
 * Scheda PG — gestione admin (MODERATOR+).
 * Modifica anagrafica, razza, sesso, immagine, media, banca, salute max, caratteristiche.
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}
if ((int)$_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['access_denied'] ?? $MESSAGE['error']['not_allowed']) . '</div>';
    return;
}

$pg     = $_REQUEST['pg'];
$alerts = [];

if (($_POST['op'] ?? '') === 'modify') {
    gdrcd_query(
        "UPDATE personaggio SET
            affetti = '" . gdrcd_filter('in', $_POST['modifica_affetti'] ?? '') . "',
            descrizione = '" . gdrcd_filter('in', $_POST['modifica_background'] ?? '') . "',
            url_media = '" . gdrcd_filter('in', gdrcd_filter('fullurl', $_POST['modifica_url_media'] ?? '')) . "',
            url_img = '" . gdrcd_filter('in', gdrcd_filter('fullurl', $_POST['modifica_url_img'] ?? '')) . "',
            car0 = " . gdrcd_filter('num', $_POST['car0']) . ",
            car1 = " . gdrcd_filter('num', $_POST['car1']) . ",
            car2 = " . gdrcd_filter('num', $_POST['car2']) . ",
            car3 = " . gdrcd_filter('num', $_POST['car3']) . ",
            car4 = " . gdrcd_filter('num', $_POST['car4']) . ",
            car5 = " . gdrcd_filter('num', $_POST['car5']) . ",
            sesso = '" . gdrcd_filter('in', $_POST['modifica_sesso']) . "',
            id_razza = " . gdrcd_filter('num', $_POST['modifica_razza']) . ",
            banca = " . gdrcd_filter('num', $_POST['modifica_banca']) . ",
            salute_max = " . gdrcd_filter('num', $_POST['modifica_salute_max']) . "
         WHERE nome = '" . gdrcd_filter('in', $pg) . "'
           AND permessi <= " . (int)$_SESSION['permessi']
    );
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
}

$record = gdrcd_query(
    "SELECT sesso, id_razza, descrizione, affetti, url_img, url_media,
            car0, car1, car2, car3, car4, car5, salute_max, banca
     FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "'"
);

$lbl_a = $MESSAGE['interface']['sheet']['modify_form']['admin'];
$lbl_m = $MESSAGE['interface']['sheet']['menu'];
$cars_cap = (int)($PARAMETERS['settings']['cars_cap'] ?? 5);
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['gst']) ?>
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

    <form action="main.php?page=scheda_gst" method="post" class="space-y-5">
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Anagrafica</h3>
            </div>
            <div class="gdrcd-card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="gdrcd-label" for="g_sesso"><?= gdrcd_filter('out', $lbl_a['gender']) ?></label>
                        <select class="gdrcd-select" id="g_sesso" name="modifica_sesso">
                            <option value="m" <?= $record['sesso'] === 'm' ? 'selected' : '' ?>>M</option>
                            <option value="f" <?= $record['sesso'] === 'f' ? 'selected' : '' ?>>F</option>
                        </select>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="g_razza"><?= gdrcd_filter('out', $PARAMETERS['names']['race']['sing']) ?></label>
                        <select class="gdrcd-select" id="g_razza" name="modifica_razza">
                            <?php $razze = gdrcd_query("SELECT id_razza, nome_razza FROM razza ORDER BY nome_razza", 'result');
                            while ($r = gdrcd_query($razze, 'fetch')):
                                $sel = ((int)$record['id_razza'] === (int)$r['id_razza']) ? 'selected' : '';
                                ?>
                                <option value="<?= (int)$r['id_razza'] ?>" <?= $sel ?>><?= gdrcd_filter('out', $r['nome_razza']) ?></option>
                            <?php endwhile;
                            gdrcd_query($razze, 'free');
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="g_img"><?= gdrcd_filter('out', $lbl_a['url_img']) ?></label>
                        <input class="gdrcd-input" type="url" id="g_img" name="modifica_url_img"
                               value="<?= gdrcd_filter('out', $record['url_img']) ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="g_med"><?= gdrcd_filter('out', $lbl_a['url_media']) ?></label>
                        <input class="gdrcd-input" type="url" id="g_med" name="modifica_url_media"
                               value="<?= gdrcd_filter('out', $record['url_media']) ?>"/>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="gdrcd-label" for="g_descr"><?= gdrcd_filter('out', $lbl_a['background']) ?></label>
                    <textarea class="gdrcd-textarea" id="g_descr" name="modifica_background" rows="6"><?= gdrcd_filter('out', $record['descrizione']) ?></textarea>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                </div>
                <div>
                    <label class="gdrcd-label" for="g_aff"><?= gdrcd_filter('out', $lbl_a['relationships']) ?></label>
                    <textarea class="gdrcd-textarea" id="g_aff" name="modifica_affetti" rows="4"><?= gdrcd_filter('out', $record['affetti']) ?></textarea>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                </div>
            </div>
        </section>

        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Risorse</h3>
            </div>
            <div class="gdrcd-card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="gdrcd-label" for="g_banca"><?= gdrcd_filter('out', $lbl_a['bank']) ?></label>
                    <input class="gdrcd-input" type="number" id="g_banca" name="modifica_banca"
                           value="<?= (int)$record['banca'] ?>"/>
                </div>
                <div>
                    <label class="gdrcd-label" for="g_hpmax"><?= gdrcd_filter('out', $lbl_a['max_hp']) ?></label>
                    <input class="gdrcd-input" type="number" id="g_hpmax" name="modifica_salute_max"
                           value="<?= (int)$record['salute_max'] ?>"/>
                </div>
            </div>
        </section>

        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['register']['fields']['stats']) ?></h3>
            </div>
            <div class="gdrcd-card-body">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <div>
                            <label class="block text-xs font-medium text-gdrcd-muted mb-1" for="g_car<?= $i ?>">
                                <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i]) ?>
                            </label>
                            <select class="gdrcd-select" id="g_car<?= $i ?>" name="car<?= $i ?>">
                                <?php for ($v = 1; $v <= $cars_cap; $v++):
                                    $sel = ((int)$record['car'.$i] === $v) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $v ?>" <?= $sel ?>><?= $v ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <input type="hidden" name="op" value="modify"/>
            <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
            <button type="submit" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
            </button>
        </div>
    </form>
</div>
