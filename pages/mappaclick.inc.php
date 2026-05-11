<?php
/* ============================================================
 * Mappa cliccabile (main.php?page=mappaclick&map_id=...)
 * ============================================================ */

// POST: cambio posizione / arrivo di una mappa mobile
if (isset($_POST['op']) &&
    ($_POST['op'] === gdrcd_filter('out', $MESSAGE['interface']['maps']['leave']) ||
     $_POST['op'] === gdrcd_filter('out', $MESSAGE['interface']['maps']['arrive']))) {
    gdrcd_query("UPDATE mappa_click SET posizione = " . gdrcd_filter('num', $_POST['destination']) .
                " WHERE id_click = " . gdrcd_filter('num', $_REQUEST['map_id']) . " LIMIT 1");
}

// POST: aggiornamento meteo manuale
if (isset($_POST['op']) && $_POST['op'] === gdrcd_filter('out', $MESSAGE['interface']['maps']['set_meteo'])) {
    gdrcd_query("UPDATE mappa_click SET meteo = '" . gdrcd_filter('num', $_POST['temperature']) . "°C - " .
                gdrcd_filter('in', $_POST['climate']) . "' WHERE id_click = " .
                gdrcd_filter('num', $_REQUEST['map_id']) . " LIMIT 1");
}

// Mappa corrente (fallback su sessione)
$current_map = isset($_GET['map_id']) ? gdrcd_filter('num', $_GET['map_id']) : $_SESSION['mappa'];

$result_check = gdrcd_query("SELECT id_click FROM mappa_click WHERE id_click = {$current_map} LIMIT 1", 'result');
if (gdrcd_query($result_check, 'num_rows') === 0) {
    $result_check = gdrcd_query("SELECT id_click FROM mappa_click WHERE principale = 1 LIMIT 1", 'result');
}
$current_map = gdrcd_query($result_check, 'fetch')['id_click'] ?? null;

if (empty($current_map)):
?>
<div class="space-y-4">
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['can_t_find_any_map']) ?></div>
    </div>
    <?php if (gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])): ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['error']['can_t_find_main_map']) ?></div>
        </div>
    <?php endif; ?>
</div>
<?php
else:
    $result = gdrcd_query(
        "SELECT mappa.id, mappa.nome, mappa.chat, mappa.link_immagine, mappa.descrizione,
                mappa.link_immagine_hover, mappa.id_mappa_collegata, mappa.x_cord, mappa.y_cord,
                mappa.id_mappa, mappa.pagina,
                mappa_click.nome AS nome_mappa, mappa_click.immagine, mappa_click.posizione,
                mappa_click.id_click, mappa_click.mobile, mappa_click.larghezza, mappa_click.altezza
         FROM mappa_click
         LEFT JOIN mappa ON mappa.id_mappa = mappa_click.id_click
         WHERE mappa_click.id_click = " . (int)$current_map,
        'result'
    );

    // Pre-fetch tutti i record per separare meta-mappa da link
    $rows = [];
    while ($r = gdrcd_query($result, 'fetch')) {
        $rows[] = $r;
    }

    if (!$rows) {
        // Nessun record (mappa esistente ma senza meta): ricarico solo meta-mappa
        $rows = [gdrcd_query("SELECT NULL AS id, NULL AS nome, NULL AS chat, NULL AS link_immagine, NULL AS descrizione,
                                     NULL AS link_immagine_hover, NULL AS id_mappa_collegata, NULL AS x_cord, NULL AS y_cord,
                                     NULL AS id_mappa, NULL AS pagina,
                                     nome AS nome_mappa, immagine, posizione, id_click, mobile, larghezza, altezza
                              FROM mappa_click WHERE id_click = " . (int)$current_map . " LIMIT 1")];
    }

    $meta     = $rows[0];
    $vicinato = (int)$meta['posizione'];
    $self     = (int)$meta['id_click'];
    $mobile   = (int)$meta['mobile'];

    // Aggiorno posizione del PG
    gdrcd_query("UPDATE personaggio SET ultima_mappa=" . $self .
                " WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");

    $bg_url = 'themes/' . $PARAMETERS['themes']['current_theme'] . '/imgs/maps/' . $meta['immagine'];
    $base_img_link = 'themes/' . $PARAMETERS['themes']['current_theme'] . '/imgs/maps/';
?>
<div class="space-y-6">

    <header class="flex items-center justify-between gap-4">
        <div>
            <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $meta['nome_mappa']) ?></h2>
            <?php if ($vicinato === INVIAGGIO): ?>
                <p class="gdrcd-muted text-xs mt-1">
                    <?= gdrcd_filter('out', $MESSAGE['interface']['maps']['traveling']) ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($vicinato === INVIAGGIO): ?>
            <span class="gdrcd-badge-accent">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                In viaggio
            </span>
        <?php endif; ?>
    </header>

    <?php if ($PARAMETERS['mode']['map_tooltip'] === 'ON'): ?>
        <div id="descriptionLoc"
             class="hidden absolute z-50 bg-gdrcd-panel border border-gdrcd-border rounded-gdrcd shadow-gdrcd-elev px-3 py-2 text-xs max-w-xs"></div>
    <?php endif; ?>

    <div class="gdrcd-card overflow-auto">
        <div class="relative bg-gdrcd-panel-alt"
             style="background:url('<?= htmlspecialchars($bg_url) ?>') top left no-repeat;
                    width:<?= (int)$meta['larghezza'] ?>px;
                    height:<?= (int)$meta['altezza'] ?>px;">
            <?php foreach ($rows as $row):
                if (empty($row['id'])) continue; // skip meta-only row

                if ($row['chat'] == 1) {
                    $qstring_link = 'dir=' . (int)$row['id'];
                } elseif ((int)$row['id_mappa_collegata'] !== 0) {
                    $qstring_link = 'page=mappaclick&map_id=' . (int)$row['id_mappa_collegata'];
                } else {
                    $qstring_link = 'page=' . gdrcd_filter('out', $row['pagina']);
                }

                $tooltip_attrs = '';
                if ($PARAMETERS['mode']['map_tooltip'] === 'ON' && !empty($row['descrizione'])) {
                    $desc = trim(nl2br(gdrcd_filter('in', $row['descrizione'])));
                    $desc = strtr($desc, ["\n\r" => '', "\n" => '', "\r" => '', '"' => '&quot;']);
                    $tooltip_attrs = 'onmouseover="show_desc(event, \'' . $desc . '\');" onmouseout="hide_desc();"';
                }

                if (!empty($row['link_immagine'])) {
                    $hover_attr = '';
                    if (!empty($row['link_immagine_hover'])) {
                        $hover_attr = 'onmouseover="this.src=\'' . $base_img_link . htmlspecialchars($row['link_immagine_hover']) . '\';" '
                                    . 'onmouseout="this.src=\'' . $base_img_link . htmlspecialchars($row['link_immagine']) . '\'"';
                    }
                    $label = '<img src="' . $base_img_link . htmlspecialchars($row['link_immagine']) . '" '
                           . 'alt="' . htmlspecialchars($row['nome']) . '" ' . $hover_attr
                           . ' class="block max-w-none transition-opacity hover:opacity-80"/>';
                } else {
                    $label = '<span class="gdrcd-badge-neutral bg-gdrcd-panel/90 backdrop-blur shadow-gdrcd-card hover:bg-gdrcd-accent hover:text-white transition-colors">'
                           . gdrcd_filter('out', $row['nome']) . '</span>';
                }
                ?>
                <div class="absolute"
                     style="top:<?= (int)$row['y_cord'] ?>px; left:<?= (int)$row['x_cord'] ?>px;">
                    <a href="main.php?<?= $qstring_link ?>" target="_top"
                       class="inline-block focus:outline-none focus-visible:ring-2 focus-visible:ring-gdrcd-accent-ring rounded"
                       <?= $tooltip_attrs ?>>
                        <?= $label ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($vicinato !== INVIAGGIO): ?>
        <section>
            <h3 class="gdrcd-h3 mb-3"><?= gdrcd_filter('out', $MESSAGE['interface']['maps']['more']) ?></h3>

            <?php
            $near = gdrcd_query("SELECT id_click, nome FROM mappa_click WHERE posizione = " . $vicinato .
                                " AND id_click <> " . $self . " ORDER BY nome", 'result');
            if (gdrcd_query($near, 'num_rows') > 0): ?>
                <div class="flex flex-wrap gap-2">
                    <?php while ($n = gdrcd_query($near, 'fetch')): ?>
                        <a href="main.php?page=mappaclick&map_id=<?= (int)$n['id_click'] ?>" target="_top"
                           class="gdrcd-btn-secondary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0L6.343 16.657a8 8 0 1111.314 0zM12 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                            <?= gdrcd_filter('out', $n['nome']) ?>
                        </a>
                    <?php endwhile;
                    gdrcd_query($near, 'free');
                    ?>
                </div>
            <?php else: ?>
                <p class="gdrcd-muted"><?= gdrcd_filter('out', $MESSAGE['interface']['maps']['no_more']) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($_SESSION['permessi'] >= GAMEMASTER): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Controlli Master</h3>
            </div>
            <div class="gdrcd-card-body space-y-5">

                <?php if ($mobile === 1): ?>
                    <form action="main.php?page=mappaclick&map_id=<?= (int)$_SESSION['mappa'] ?>"
                          method="post" class="space-y-3">
                        <div class="gdrcd-eyebrow">Mappa mobile</div>
                        <?php if ($vicinato !== INVIAGGIO): ?>
                            <div class="flex items-end gap-3 flex-wrap">
                                <input type="hidden" name="destination" value="<?= INVIAGGIO ?>"/>
                                <button type="submit" name="op"
                                        value="<?= gdrcd_filter('out', $MESSAGE['interface']['maps']['leave']) ?>"
                                        class="gdrcd-btn-primary">
                                    <?= gdrcd_filter('out', $MESSAGE['interface']['maps']['leave']) ?>
                                </button>
                            </div>
                        <?php else:
                            $dests = gdrcd_query("SELECT posizione, nome FROM mappa_click WHERE posizione <> -1 AND id_click <> " . (int)$_SESSION['mappa'] . " ORDER BY nome", 'result');
                            ?>
                            <div class="flex items-end gap-3 flex-wrap">
                                <?php if (gdrcd_query($dests, 'num_rows') > 0): ?>
                                    <div class="flex-1 min-w-[12rem]">
                                        <label class="gdrcd-label" for="mc_destination">Destinazione</label>
                                        <select id="mc_destination" name="destination" class="gdrcd-select">
                                            <?php while ($d = gdrcd_query($dests, 'fetch')): ?>
                                                <option value="<?= (int)$d['posizione'] ?>"><?= gdrcd_filter('out', $d['nome']) ?></option>
                                            <?php endwhile;
                                            gdrcd_query($dests, 'free');
                                            ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="destination" value="0"/>
                                <?php endif; ?>
                                <button type="submit" name="op"
                                        value="<?= gdrcd_filter('out', $MESSAGE['interface']['maps']['arrive']) ?>"
                                        class="gdrcd-btn-primary">
                                    <?= gdrcd_filter('out', $MESSAGE['interface']['maps']['arrive']) ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php if ($PARAMETERS['mode']['auto_meteo'] === 'OFF'): ?>
                    <?php if ($mobile === 1): ?><div class="gdrcd-divider !my-2"></div><?php endif; ?>
                    <form action="main.php?page=mappaclick&map_id=<?= (int)$_SESSION['mappa'] ?>"
                          method="post" class="space-y-3">
                        <div class="gdrcd-eyebrow">Meteo</div>
                        <div class="flex items-end gap-3 flex-wrap">
                            <div>
                                <label class="gdrcd-label" for="mc_temperature">Temperatura</label>
                                <select id="mc_temperature" name="temperature" class="gdrcd-select">
                                    <?php for ($i = 45; $i >= -45; $i--): ?>
                                        <option value="<?= $i ?>" <?= $i === 0 ? 'selected' : '' ?>>
                                            <?= $i ?>° C
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="mc_climate">Clima</label>
                                <select id="mc_climate" name="climate" class="gdrcd-select">
                                    <?php foreach ($MESSAGE['interface']['meteo']['status'] as $climate): ?>
                                        <option value="<?= htmlspecialchars($climate) ?>"><?= htmlspecialchars($climate) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="meteo" value="meteo_change"/>
                            <button type="submit" name="op"
                                    value="<?= gdrcd_filter('out', $MESSAGE['interface']['maps']['set_meteo']) ?>"
                                    class="gdrcd-btn-secondary">
                                <?= gdrcd_filter('out', $MESSAGE['interface']['maps']['set_meteo']) ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

</div>
<?php endif; ?>
