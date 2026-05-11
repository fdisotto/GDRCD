<?php
/**
 * Scheda PG — inventario zaino (oggetti posizione = 0).
 * Solo proprietario può modificare/cedere/abbandonare.
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = $_REQUEST['pg'];
$check = gdrcd_query("SELECT nome FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "'", 'result');
if (gdrcd_query($check, 'num_rows') === 0) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}
gdrcd_query($check, 'free');

$is_self = ($_SESSION['login'] === $pg);
$op      = $_POST['op'] ?? null;
$alerts  = [];

if ($is_self) {
    if ($op === 'togli') {
        gdrcd_query("UPDATE clgpersonaggiooggetto SET posizione = 0 WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
    }
    if ($op === 'commenta') {
        gdrcd_query(
            "UPDATE clgpersonaggiooggetto SET commento = '" . gdrcd_filter('in', $_POST['commento'] ?? '') . "'
             WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) .
            "   AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
    }
    if ($op === 'abbandona') {
        if ((int)$_POST['numero'] <= 1) {
            gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        } else {
            gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        }
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['sheet']['items']['warning']['done'] ?? 'Oggetto abbandonato.')];
    }
    if ($op === 'cedi') {
        $id_obj  = gdrcd_filter('num', $_POST['id_oggetto']);
        $num     = (int)$_POST['numero'];
        $dest    = gdrcd_filter('in', $_POST['give_item']);
        $cariche = gdrcd_filter('num', $_POST['cariche']);

        $exists = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj, 'result');
        if ((int)gdrcd_query($exists, 'num_rows') > 0) {
            gdrcd_query($exists, 'free');

            // Decrementa o rimuove sorgente
            if ($num <= 1) {
                gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
            } else {
                gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . $id_obj . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
            }

            // Aggiunge a destinatario
            $dest_check = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj . " AND nome = '" . $dest . "'", 'result');
            if ((int)gdrcd_query($dest_check, 'num_rows') > 0) {
                gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero + 1 WHERE id_oggetto = " . $id_obj . " AND nome = '" . $dest . "'");
            } else {
                gdrcd_query("INSERT INTO clgpersonaggiooggetto (nome, id_oggetto, cariche, numero) VALUES ('" . $dest . "', " . $id_obj . ", " . $cariche . ", 1)");
            }
            gdrcd_query($dest_check, 'free');

            gdrcd_query(
                "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES ("
                . "'" . $dest . "',"
                . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
                . "NOW(), " . BONIFICO . ","
                . "'" . gdrcd_filter('in', $_POST['checosa'] ?? '') . "')"
            );
            $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
        } else {
            $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
        }
    }
}

$theme = $PARAMETERS['themes']['current_theme'];
$result = gdrcd_query(
    "SELECT oggetto.id_oggetto, oggetto.nome AS nome_oggetto, oggetto.descrizione, oggetto.urlimg,
            oggetto.ubicabile, oggetto.difesa, oggetto.attacco,
            oggetto.bonus_car0, oggetto.bonus_car1, oggetto.bonus_car2, oggetto.bonus_car3, oggetto.bonus_car4, oggetto.bonus_car5,
            clgpersonaggiooggetto.*
     FROM clgpersonaggiooggetto LEFT JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
     WHERE clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $pg) . "'
       AND clgpersonaggiooggetto.posizione = 0
     ORDER BY oggetto.nome DESC",
    'result'
);
$numresults = (int)gdrcd_query($result, 'num_rows');

$lbl_i = $MESSAGE['interface']['sheet']['items']['list'];
$lbl_m = $MESSAGE['interface']['sheet']['menu'];

// Personaggi disponibili per "cedi" (stessa location o tutti se mode off)
$chars = null;
if ($is_self) {
    if (($PARAMETERS['mode']['give_only_if_online'] ?? 'OFF') !== 'ON') {
        $chars = gdrcd_query(
            "SELECT nome FROM personaggio
             WHERE ultimo_luogo = " . (int)($_SESSION['luogo'] ?? 0) . "
               AND ultimo_luogo <> -1
               AND nome <> '" . gdrcd_filter('in', $_SESSION['login']) . "'
               AND DATE_ADD(ultimo_refresh, INTERVAL 2 MINUTE) > NOW()
             ORDER BY nome",
            'result'
        );
    } else {
        $chars = gdrcd_query("SELECT nome FROM personaggio ORDER BY nome", 'result');
    }
}
$chars_list = [];
if ($chars !== null) {
    while ($c = gdrcd_query($chars, 'fetch')) {
        $chars_list[] = $c['nome'];
    }
    gdrcd_query($chars, 'free');
}
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['inventory']) ?>
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

    <?php if ($numresults === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>Lo zaino è vuoto.</div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <?php while ($r = gdrcd_query($result, 'fetch')):
                $bonuses = [
                    'ATK' => (int)$r['attacco'],
                    'DEF' => (int)$r['difesa'],
                ];
                for ($i = 0; $i < 6; $i++) {
                    $v = (int)$r['bonus_car'.$i];
                    if ($v !== 0) {
                        $bonuses[gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i])] = $v;
                    }
                }
                $has_atk_def = ((int)$r['attacco'] !== 0 || (int)$r['difesa'] !== 0);
                ?>
                <article class="gdrcd-card overflow-hidden">
                    <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3 p-4">
                        <div class="shrink-0">
                            <?php if (!empty($r['urlimg'])): ?>
                                <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/items/<?= htmlspecialchars($r['urlimg']) ?>"
                                     alt="" class="w-20 h-20 rounded-md object-cover border border-gdrcd-border bg-gdrcd-panel-alt"/>
                            <?php else: ?>
                                <div class="w-20 h-20 rounded-md bg-gdrcd-panel-alt flex items-center justify-center text-gdrcd-subtle">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <h3 class="font-display font-semibold text-gdrcd-text">
                                    <?= gdrcd_filter('out', $r['nome_oggetto']) ?>
                                </h3>
                                <span class="gdrcd-badge-accent text-[10px]">× <?= (int)$r['numero'] ?></span>
                                <?php if ((int)$r['cariche'] > 0): ?>
                                    <span class="gdrcd-badge-neutral text-[10px]">
                                        <?= gdrcd_filter('out', $lbl_i['charges']) ?>: <?= (int)$r['cariche'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($r['descrizione'])): ?>
                                <p class="text-xs text-gdrcd-muted leading-relaxed">
                                    <?= gdrcd_filter('out', $r['descrizione']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($has_atk_def || count($bonuses) > 2): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($bonuses as $lbl => $v):
                                        if ($v === 0) continue;
                                        $cls = $v > 0 ? 'gdrcd-badge-success' : 'gdrcd-badge-error';
                                        $sign = $v > 0 ? '+' : '';
                                        ?>
                                        <span class="<?= $cls ?> text-[10px]">
                                            <?= htmlspecialchars($lbl) ?>
                                            <strong class="ml-0.5"><?= $sign . $v ?></strong>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($r['commento']) && !$is_self): ?>
                                <div class="text-xs italic text-gdrcd-muted border-l-2 border-gdrcd-border pl-2">
                                    <?= gdrcd_filter('out', $r['commento']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($is_self): ?>
                        <div class="border-t border-gdrcd-border bg-gdrcd-panel-alt/30 px-4 py-3 space-y-2">

                            <!-- Commento -->
                            <form action="main.php?page=scheda_oggetti" method="post" class="space-y-2">
                                <textarea class="gdrcd-textarea text-xs" name="commento" rows="2" placeholder="Note personali..."><?= gdrcd_filter('out', $r['commento']) ?></textarea>
                                <div class="flex justify-end">
                                    <input type="hidden" name="op" value="commenta"/>
                                    <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                    <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                    <button type="submit" class="gdrcd-btn-ghost text-xs">
                                        <?= gdrcd_filter('out', $lbl_i['add_note']) ?>
                                    </button>
                                </div>
                            </form>

                            <div class="flex flex-wrap gap-2 pt-2 border-t border-gdrcd-border">
                                <!-- Abbandona -->
                                <form action="main.php?page=scheda_oggetti" method="post" class="inline"
                                      onsubmit="return confirm('Abbandonare un esemplare di questo oggetto?');">
                                    <input type="hidden" name="op" value="abbandona"/>
                                    <input type="hidden" name="numero" value="<?= (int)$r['numero'] ?>"/>
                                    <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                    <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                    <button type="submit" class="gdrcd-btn-ghost text-xs">
                                        <?= gdrcd_filter('out', $lbl_i['drop']) ?>
                                    </button>
                                </form>

                                <?php if ((int)$r['ubicabile'] > 0): ?>
                                    <form action="main.php?page=scheda_equip" method="post" class="inline">
                                        <input type="hidden" name="op" value="in_zaino"/>
                                        <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                        <button type="submit" class="gdrcd-btn-secondary text-xs">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                            <?= gdrcd_filter('out', $lbl_i['put_in']) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (count($chars_list) > 0): ?>
                                    <form action="main.php?page=scheda_oggetti" method="post" class="inline-flex items-center gap-1">
                                        <input type="hidden" name="op" value="cedi"/>
                                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                        <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                        <input type="hidden" name="cariche" value="<?= (int)$r['cariche'] ?>"/>
                                        <input type="hidden" name="numero" value="<?= (int)$r['numero'] ?>"/>
                                        <input type="hidden" name="checosa" value="<?= gdrcd_filter('out', $r['nome_oggetto']) ?>"/>
                                        <select class="gdrcd-select text-xs py-1" name="give_item">
                                            <?php foreach ($chars_list as $n): ?>
                                                <option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="gdrcd-btn-secondary text-xs">
                                            <?= gdrcd_filter('out', $lbl_i['give']) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endwhile;
            gdrcd_query($result, 'free');
            ?>
        </div>
    <?php endif; ?>

    <div>
        <a href="main.php?page=scheda_equip&pg=<?= urlencode($pg) ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
            Vai all'equipaggiamento
        </a>
    </div>
</div>
