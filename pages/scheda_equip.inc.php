<?php
/**
 * Scheda PG — equipaggiamento (oggetti posizione > 0).
 * Vista paper-doll + lista oggetti indossati/in zaino.
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = $_REQUEST['pg'];
$check = gdrcd_query("SELECT sesso FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "'", 'result');
if (gdrcd_query($check, 'num_rows') === 0) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}
$pg_data = gdrcd_query($check, 'fetch');
gdrcd_query($check, 'free');
$sesso = $pg_data['sesso'];

$can_modify = ($_SESSION['login'] === $pg) || ((int)$_SESSION['permessi'] >= GAMEMASTER);
$op         = $_POST['op'] ?? null;
$alerts     = [];

if ($can_modify) {
    if ($op === 'abbandona') {
        if ((int)$_POST['numero'] <= 1) {
            gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        } else {
            gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        }
        gdrcd_query(
            "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES ("
            . "'" . gdrcd_filter('in', $pg) . "',"
            . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
            . "NOW(), " . BONIFICO . ","
            . "' -" . gdrcd_filter('in', $_POST['checosa'] ?? '') . "')"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];

    } elseif ($op === 'cedi') {
        $id_obj  = gdrcd_filter('num', $_POST['id_oggetto']);
        $num     = (int)$_POST['numero'];
        $dest    = gdrcd_filter('in', $_POST['give_item']);
        $cariche = gdrcd_filter('num', $_POST['cariche']);

        $exists = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj, 'result');
        if ((int)gdrcd_query($exists, 'num_rows') > 0) {
            gdrcd_query($exists, 'free');
            if ($num <= 1) {
                gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
            } else {
                gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . $id_obj . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
            }
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

    } elseif ($op === 'indossa') {
        gdrcd_query(
            "UPDATE clgpersonaggiooggetto SET posizione = " . gdrcd_filter('num', $_POST['posizione']) .
            " WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) .
            "   AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];

    } elseif ($op === 'in_zaino') {
        gdrcd_query(
            "UPDATE clgpersonaggiooggetto SET posizione = 1
             WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) .
            "   AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
    }
}

/* Carico oggetti indossati (posizione > 1, slot specifici) */
$oggetti = [];
$result = gdrcd_query(
    "SELECT oggetto.id_oggetto, oggetto.nome, oggetto.urlimg AS immagine, clgpersonaggiooggetto.posizione
     FROM clgpersonaggiooggetto JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
     WHERE clgpersonaggiooggetto.posizione > 1
       AND clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $pg) . "'",
    'result'
);
while ($r = gdrcd_query($result, 'fetch')) {
    $oggetti[(int)$r['posizione']] = $r;
}
gdrcd_query($result, 'free');

$theme = $PARAMETERS['themes']['current_theme'];
$lbl_i = $MESSAGE['interface']['sheet']['items']['list'];
$lbl_m = $MESSAGE['interface']['sheet']['menu'];

/** Helper: render slot oggetto se presente, altrimenti placeholder. */
$render_slot = function (?array $item, string $label, string $icon_svg) use ($theme) {
    ob_start(); ?>
    <div class="border border-gdrcd-border rounded-md bg-gdrcd-panel p-2 text-center min-h-[5rem] flex flex-col items-center justify-center gap-1">
        <?php if ($item !== null && !empty($item['immagine'])): ?>
            <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/items/<?= htmlspecialchars($item['immagine']) ?>"
                 alt="<?= gdrcd_filter('out', $item['nome']) ?>" title="<?= gdrcd_filter('out', $item['nome']) ?>"
                 class="w-12 h-12 object-cover rounded"/>
            <div class="text-[10px] text-gdrcd-text-soft truncate w-full">
                <?= gdrcd_filter('out', $item['nome']) ?>
            </div>
        <?php else: ?>
            <span class="inline-flex items-center justify-center w-10 h-10 text-gdrcd-subtle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><?= $icon_svg ?></svg>
            </span>
            <div class="text-[10px] text-gdrcd-subtle"><?= htmlspecialchars($label) ?></div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
};

/* Personaggi disponibili per cedi */
$chars_list = [];
if ($can_modify) {
    if (($PARAMETERS['mode']['give_only_if_online'] ?? 'OFF') === 'ON') {
        $q = "SELECT nome FROM personaggio
              WHERE ultimo_luogo = " . (int)($_SESSION['luogo'] ?? 0) . "
                AND ultimo_luogo <> -1
                AND nome <> '" . gdrcd_filter('in', $_SESSION['login']) . "'
                AND DATE_ADD(ultimo_refresh, INTERVAL 2 MINUTE) > NOW()
              ORDER BY nome";
    } else {
        $q = "SELECT nome FROM personaggio ORDER BY nome";
    }
    $cres = gdrcd_query($q, 'result');
    while ($c = gdrcd_query($cres, 'fetch')) $chars_list[] = $c['nome'];
    gdrcd_query($cres, 'free');
}
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['equipment']) ?>
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

    <!-- Paper doll -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Equipaggiato</h3>
        </div>
        <div class="gdrcd-card-body">
            <div class="grid grid-cols-3 gap-3 max-w-2xl mx-auto items-center">
                <div class="space-y-3">
                    <?= $render_slot($oggetti[TESTA]  ?? null, 'Testa',  '<path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>') ?>
                    <?= $render_slot($oggetti[COLLO]  ?? null, 'Collo',  '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v4m0 0a3 3 0 110 6 3 3 0 010-6zm0 6v10"/>') ?>
                    <?= $render_slot($oggetti[MANODX] ?? null, 'Mano DX','<path stroke-linecap="round" stroke-linejoin="round" d="M9 11V6a2 2 0 014 0v5m-2 8h8"/>') ?>
                    <?= $render_slot($oggetti[ANELLO] ?? null, 'Anello', '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4a8 8 0 100 16 8 8 0 000-16zm0 4a4 4 0 100 8 4 4 0 000-8z"/>') ?>
                    <?= $render_slot($oggetti[PIEDI]  ?? null, 'Piedi',  '<path stroke-linecap="round" stroke-linejoin="round" d="M4 18h16M6 14V8m4 6V4m4 10V6m4 8V8"/>') ?>
                </div>
                <div class="flex justify-center">
                    <img src="imgs/avatars/inventory_<?= htmlspecialchars($sesso) ?>.png" alt=""
                         class="max-h-96 w-auto opacity-90"/>
                </div>
                <div class="space-y-3">
                    <?= $render_slot($oggetti[TORSO]  ?? null, 'Torso',  '<path stroke-linecap="round" stroke-linejoin="round" d="M16 4v4l4 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2v-8l4-2V4"/>') ?>
                    <?= $render_slot(null, '',  '<path d="M0 0"/>') ?>
                    <?= $render_slot($oggetti[MANOSX] ?? null, 'Mano SX','<path stroke-linecap="round" stroke-linejoin="round" d="M15 11V6a2 2 0 10-4 0v5m2 8h-8"/>') ?>
                    <?= $render_slot($oggetti[GAMBE]  ?? null, 'Gambe',  '<path stroke-linecap="round" stroke-linejoin="round" d="M9 4v8m0 0v8m6-16v8m0 0v8M5 12h14"/>') ?>
                    <?= $render_slot(null, '',  '<path d="M0 0"/>') ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Lista oggetti indossati e in zaino -->
    <?php
    $list = gdrcd_query(
        "SELECT oggetto.id_oggetto, oggetto.nome AS nome_oggetto, oggetto.descrizione, oggetto.urlimg,
                oggetto.ubicabile, oggetto.difesa, oggetto.attacco,
                oggetto.bonus_car0, oggetto.bonus_car1, oggetto.bonus_car2, oggetto.bonus_car3, oggetto.bonus_car4, oggetto.bonus_car5,
                clgpersonaggiooggetto.*
         FROM clgpersonaggiooggetto LEFT JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
         WHERE clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $pg) . "'
           AND clgpersonaggiooggetto.posizione > 0
         ORDER BY oggetto.nome DESC",
        'result'
    );
    $listcount = (int)gdrcd_query($list, 'num_rows');
    ?>
    <section>
        <header class="flex items-baseline justify-between gap-2 mb-3">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['items']['zaino']) ?></h3>
        </header>

        <?php if ($listcount === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessun oggetto indossato o nello zaino equipaggiato.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php while ($r = gdrcd_query($list, 'fetch')):
                    $bonuses = [];
                    if ((int)$r['attacco'] !== 0) $bonuses['ATK'] = (int)$r['attacco'];
                    if ((int)$r['difesa']  !== 0) $bonuses['DEF'] = (int)$r['difesa'];
                    for ($i = 0; $i < 6; $i++) {
                        $v = (int)$r['bonus_car'.$i];
                        if ($v !== 0) {
                            $bonuses[gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i])] = $v;
                        }
                    }
                    $is_zaino = ((int)$r['posizione'] === 1);
                    ?>
                    <article class="gdrcd-card overflow-hidden">
                        <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3 p-4">
                            <div class="shrink-0">
                                <?php if (!empty($r['urlimg'])): ?>
                                    <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/items/<?= htmlspecialchars($r['urlimg']) ?>"
                                         alt="" class="w-20 h-20 rounded-md object-cover border border-gdrcd-border bg-gdrcd-panel-alt"/>
                                <?php else: ?>
                                    <div class="w-20 h-20 rounded-md bg-gdrcd-panel-alt"></div>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <h4 class="font-display font-semibold text-gdrcd-text">
                                        <?= gdrcd_filter('out', $r['nome_oggetto']) ?>
                                    </h4>
                                    <span class="gdrcd-badge-accent text-[10px]">× <?= (int)$r['numero'] ?></span>
                                    <?php if ($is_zaino): ?>
                                        <span class="gdrcd-badge-neutral text-[10px]">In zaino</span>
                                    <?php else: ?>
                                        <span class="gdrcd-badge-success text-[10px]">Indossato</span>
                                    <?php endif; ?>
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

                                <?php if (!empty($bonuses)): ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($bonuses as $lbl => $v):
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

                                <?php if (!empty($r['commento'])): ?>
                                    <div class="text-xs italic text-gdrcd-muted border-l-2 border-gdrcd-border pl-2">
                                        <?= gdrcd_filter('out', $r['commento']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($can_modify): ?>
                            <div class="border-t border-gdrcd-border bg-gdrcd-panel-alt/30 px-4 py-3 flex flex-wrap gap-2">

                                <form action="main.php?page=scheda_oggetti" method="post" class="inline">
                                    <input type="hidden" name="op" value="togli"/>
                                    <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                    <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                    <button type="submit" class="gdrcd-btn-ghost text-xs">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                        <?= gdrcd_filter('out', $lbl_i['put_away']) ?>
                                    </button>
                                </form>

                                <?php if ((int)$r['ubicabile'] > ZAINO):
                                    if ($is_zaino):
                                        // Mostra azioni per indossare/impugnare
                                        if (!isset($oggetti[(int)$r['ubicabile']])): ?>
                                            <form action="main.php?page=scheda_equip" method="post" class="inline">
                                                <input type="hidden" name="op" value="indossa"/>
                                                <input type="hidden" name="posizione" value="<?= (int)$r['ubicabile'] ?>"/>
                                                <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                                <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                                <button type="submit" class="gdrcd-btn-primary text-xs">
                                                    <?= gdrcd_filter('out', $lbl_i['wear']) ?>
                                                </button>
                                            </form>
                                        <?php endif;
                                        if (!isset($oggetti[MANODX])): ?>
                                            <form action="main.php?page=scheda_equip" method="post" class="inline">
                                                <input type="hidden" name="op" value="indossa"/>
                                                <input type="hidden" name="posizione" value="<?= MANODX ?>"/>
                                                <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                                <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                                <button type="submit" class="gdrcd-btn-secondary text-xs">
                                                    <?= gdrcd_filter('out', $lbl_i['wield']) ?> (DX)
                                                </button>
                                            </form>
                                        <?php endif;
                                        if (!isset($oggetti[MANOSX])): ?>
                                            <form action="main.php?page=scheda_equip" method="post" class="inline">
                                                <input type="hidden" name="op" value="indossa"/>
                                                <input type="hidden" name="posizione" value="<?= MANOSX ?>"/>
                                                <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                                <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                                <button type="submit" class="gdrcd-btn-secondary text-xs">
                                                    <?= gdrcd_filter('out', $lbl_i['wield']) ?> (SX)
                                                </button>
                                            </form>
                                        <?php endif;
                                    else: ?>
                                        <form action="main.php?page=scheda_equip" method="post" class="inline">
                                            <input type="hidden" name="op" value="indossa"/>
                                            <input type="hidden" name="posizione" value="1"/>
                                            <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                            <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                            <button type="submit" class="gdrcd-btn-secondary text-xs">
                                                <?= gdrcd_filter('out', $lbl_i['unwear']) ?>
                                            </button>
                                        </form>
                                    <?php endif;
                                endif; ?>

                                <form action="main.php?page=scheda_equip" method="post" class="inline"
                                      onsubmit="return confirm('Abbandonare un esemplare?');">
                                    <input type="hidden" name="op" value="abbandona"/>
                                    <input type="hidden" name="numero" value="<?= (int)$r['numero'] ?>"/>
                                    <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                    <input type="hidden" name="checosa" value="<?= gdrcd_filter('out', $r['nome_oggetto']) ?>"/>
                                    <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                    <button type="submit" class="gdrcd-btn-ghost text-xs">
                                        <?= gdrcd_filter('out', $lbl_i['drop']) ?>
                                    </button>
                                </form>

                                <?php if (count($chars_list) > 0): ?>
                                    <form action="main.php?page=scheda_equip" method="post" class="inline-flex items-center gap-1">
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
                        <?php endif; ?>
                    </article>
                <?php endwhile;
                gdrcd_query($list, 'free');
                ?>
            </div>
        <?php endif; ?>
    </section>

    <div>
        <a href="main.php?page=scheda_oggetti&pg=<?= urlencode($pg) ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $lbl_m['inventory']) ?>
        </a>
    </div>
</div>
