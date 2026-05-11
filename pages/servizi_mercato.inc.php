<?php
/**
 * Servizi — Mercato: categorie, oggetti, acquisto/vendita.
 */

$theme = $PARAMETERS['themes']['current_theme'];
$row = gdrcd_query("SELECT soldi FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
$money = (int)($row['soldi'] ?? 0);

$op = $_POST['op'] ?? ($_REQUEST['op'] ?? null);
$alerts = [];

if ($op === 'buy') {
    $id_oggetto = gdrcd_filter('num', $_POST['id_oggetto']);
    $costo = gdrcd_query("SELECT cariche, costo FROM oggetto WHERE id_oggetto = " . $id_oggetto);
    if ($money >= (int)$costo['costo']) {
        $check = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto
                              WHERE id_oggetto = " . $id_oggetto . "
                              AND nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'", 'result');
        if (gdrcd_query($check, 'num_rows') > 0) {
            gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero + 1
                         WHERE id_oggetto = " . $id_oggetto . "
                         AND nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
        } else {
            gdrcd_query("INSERT INTO clgpersonaggiooggetto (nome, id_oggetto, cariche, numero, posizione)
                         VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "',
                                 " . $id_oggetto . ", " . (int)$costo['cariche'] . ", 1, 0)");
        }
        gdrcd_query($check, 'free');
        gdrcd_query("UPDATE personaggio SET soldi = soldi - " . (int)$costo['costo'] . "
                     WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        $q = (gdrcd_filter('num', $_POST['numero']) > 1)
            ? "UPDATE mercato SET numero = numero - 1 WHERE id_oggetto = '" . $id_oggetto . "' LIMIT 1"
            : "DELETE FROM mercato WHERE id_oggetto = '" . $id_oggetto . "' LIMIT 1";
        gdrcd_query($q);
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['buyed'])];
        $money -= (int)$costo['costo'];
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
    }
} elseif ($op === 'sell') {
    $id_oggetto = gdrcd_filter('num', $_POST['id_oggetto']);
    $check = gdrcd_query("SELECT clgpersonaggiooggetto.numero, oggetto.costo
                          FROM clgpersonaggiooggetto
                          LEFT JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
                          WHERE clgpersonaggiooggetto.id_oggetto = " . $id_oggetto . "
                          AND clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'", 'result');
    if (gdrcd_query($check, 'num_rows') > 0) {
        $r = gdrcd_query($check, 'fetch');
        gdrcd_query($check, 'free');
        $costo_vendita = floor(($r['costo'] / 100) * (100 - $PARAMETERS['settings']['resell_price']));
        $q = ($r['numero'] > 1)
            ? "UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . $id_oggetto . " AND nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1"
            : "DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_oggetto . " AND nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1";
        gdrcd_query($q);
        gdrcd_query("UPDATE mercato SET numero = numero + 1 WHERE id_oggetto = " . $id_oggetto . " LIMIT 1");
        gdrcd_query("UPDATE personaggio SET soldi = soldi + " . (int)$costo_vendita . " WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['buyed'])];
        $money += (int)$costo_vendita;
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
    }
}

$visit = isset($_REQUEST['op']) && $_REQUEST['op'] === 'visit';
?>

<div class="space-y-6">
    <header class="space-y-1 flex flex-wrap items-center justify-between gap-3">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['market']['page_name']) ?>
        </h2>
        <span class="gdrcd-badge-accent tabular-nums">
            Saldo: <?= $money ?> <?= gdrcd_filter('out', $PARAMETERS['names']['currency']['plur']) ?>
        </span>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if (!$visit):
        $result = gdrcd_query("SELECT cod_tipo, descrizione FROM codtipooggetto ORDER BY descrizione", 'result');
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['market']['categories']) ?></h3>
            </header>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 p-4">
                <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                    <a href="main.php?page=servizi_mercato&op=visit&what=<?= urlencode($row['cod_tipo']) ?>"
                       class="gdrcd-card group hover:border-gdrcd-accent transition p-3 flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded bg-gdrcd-accent-soft text-gdrcd-accent group-hover:bg-gdrcd-accent group-hover:text-white transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </span>
                        <span class="text-sm font-display group-hover:text-gdrcd-accent transition">
                            <?= gdrcd_filter('out', $row['descrizione']) ?>
                        </span>
                    </a>
                <?php endwhile; gdrcd_query($result, 'free'); ?>
            </div>
        </article>
    <?php else:
        $pagebegin = (int)($_REQUEST['offset'] ?? 0) * $PARAMETERS['settings']['records_per_page'];
        $pagesize = $PARAMETERS['settings']['records_per_page'];
        $what = gdrcd_filter('get', $_REQUEST['what']);
        $tot_res = gdrcd_query("SELECT COUNT(*) AS N FROM oggetto
                                JOIN mercato ON oggetto.id_oggetto = mercato.id_oggetto
                                WHERE tipo = " . $what);
        $totaleresults = (int)($tot_res['N'] ?? 0);
        $result = gdrcd_query(
            "SELECT mercato.numero, oggetto.id_oggetto, oggetto.nome, oggetto.descrizione, oggetto.costo,
                    oggetto.difesa, oggetto.attacco, oggetto.cariche,
                    oggetto.bonus_car0, oggetto.bonus_car1, oggetto.bonus_car2, oggetto.bonus_car3, oggetto.bonus_car4, oggetto.bonus_car5,
                    oggetto.urlimg
             FROM oggetto
             JOIN mercato ON oggetto.id_oggetto = mercato.id_oggetto
             WHERE tipo = '" . $what . "'
             ORDER BY nome
             LIMIT " . $pagebegin . ", " . $pagesize,
            'result'
        );
        $num = gdrcd_query($result, 'num_rows');
    ?>
        <?php if ($num === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
                <div>Nessun oggetto disponibile in questa categoria.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php while ($row = gdrcd_query($result, 'fetch')):
                    $bonuses = [];
                    if ((int)$row['attacco'] !== 0) $bonuses['ATK'] = (int)$row['attacco'];
                    if ((int)$row['difesa'] !== 0)  $bonuses['DEF'] = (int)$row['difesa'];
                    for ($i = 0; $i < 6; $i++) {
                        $v = (int)$row['bonus_car' . $i];
                        if ($v !== 0) $bonuses[gdrcd_filter('out', $PARAMETERS['names']['stats']['car' . $i])] = $v;
                    }
                    $sell_price = floor(($row['costo'] / 100) * (100 - $PARAMETERS['settings']['resell_price']));
                ?>
                    <article class="gdrcd-card overflow-hidden">
                        <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3 p-4">
                            <div class="shrink-0">
                                <?php if (!empty($row['urlimg'])): ?>
                                    <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/items/<?= htmlspecialchars($row['urlimg']) ?>"
                                         alt="" class="w-20 h-20 rounded-md object-cover border border-gdrcd-border bg-gdrcd-panel-alt">
                                <?php else: ?>
                                    <div class="w-20 h-20 rounded-md bg-gdrcd-panel-alt"></div>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <h4 class="font-display font-semibold text-gdrcd-text"><?= gdrcd_filter('out', $row['nome']) ?></h4>
                                    <span class="gdrcd-badge-neutral text-[10px]"><?= $MESSAGE['interface']['market']['stock'] ?>: <?= (int)$row['numero'] ?></span>
                                    <?php if ((int)$row['cariche'] > 0): ?>
                                        <span class="gdrcd-badge-neutral text-[10px]"><?= gdrcd_filter('out', $MESSAGE['interface']['market']['item_charges']) ?>: <?= (int)$row['cariche'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($row['descrizione'])): ?>
                                    <p class="text-xs text-gdrcd-muted leading-relaxed"><?= gdrcd_filter('out', $row['descrizione']) ?></p>
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
                            </div>
                        </div>
                        <div class="border-t border-gdrcd-border bg-gdrcd-panel-alt/30 px-4 py-3 flex flex-wrap gap-2 justify-between">
                            <form action="main.php?page=servizi_mercato" method="post" class="inline-flex items-center gap-2">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="id_oggetto" value="<?= (int)$row['id_oggetto'] ?>">
                                <input type="hidden" name="costo" value="<?= (int)$row['costo'] ?>">
                                <input type="hidden" name="cariche" value="<?= (int)$row['cariche'] ?>">
                                <input type="hidden" name="numero" value="<?= (int)$row['numero'] ?>">
                                <input type="hidden" name="op" value="buy">
                                <button type="submit" class="gdrcd-btn-primary text-xs" <?= $money < $row['costo'] ? 'disabled' : '' ?>>
                                    <?= gdrcd_filter('out', $MESSAGE['interface']['market']['buy']) ?>
                                    · <?= (int)$row['costo'] ?> <?= gdrcd_filter('out', $PARAMETERS['names']['currency']['short']) ?>
                                </button>
                            </form>
                            <form action="main.php?page=servizi_mercato" method="post" class="inline-flex items-center gap-2">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="id_oggetto" value="<?= (int)$row['id_oggetto'] ?>">
                                <input type="hidden" name="op" value="sell">
                                <button type="submit" class="gdrcd-btn-ghost text-xs">
                                    <?= gdrcd_filter('out', $MESSAGE['interface']['market']['sell']) ?>
                                    · <?= (int)$sell_price ?> <?= gdrcd_filter('out', $PARAMETERS['names']['currency']['short']) ?>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endwhile; gdrcd_query($result, 'free'); ?>
            </div>

            <?php if ($totaleresults > $PARAMETERS['settings']['records_per_page']): ?>
                <nav class="gdrcd-pager flex flex-wrap gap-1 justify-center" aria-label="Paginazione">
                    <span class="text-sm text-gdrcd-text-soft mr-2"><?= gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) ?></span>
                    <?php for ($i = 0; $i < ceil($totaleresults / $PARAMETERS['settings']['records_per_page']); $i++): ?>
                        <a href="main.php?page=servizi_mercato&op=visit&what=<?= urlencode($what) ?>&offset=<?= $i ?>"
                           class="px-2 py-1 rounded text-sm <?= ((int)($_REQUEST['offset'] ?? 0) === $i) ? 'bg-gdrcd-accent text-white' : 'hover:bg-gdrcd-accent-soft text-gdrcd-text-soft' ?>">
                            <?= $i + 1 ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <div>
            <a href="main.php?page=servizi_mercato" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['market']['back']) ?>
            </a>
        </div>
    <?php endif; ?>
</div>
