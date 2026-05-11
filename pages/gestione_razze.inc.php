<?php
/**
 * Gestione razze (main.php?page=gestione_razze)
 * CRUD su tabella razza.
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl = $MESSAGE['interface']['administration']['races'];
$op  = $_POST['op'] ?? $_GET['op'] ?? null;

$render_back = function () use ($lbl) {
    return '<a href="main.php?page=gestione_razze" class="gdrcd-btn-ghost">'
         . '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
         . gdrcd_filter('out', $lbl['link']['back'])
         . '</a>';
};
$render_alert_success = function (string $msg) {
    return '<div class="gdrcd-alert-success">'
         . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
         . '<div>' . $msg . '</div></div>';
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Gestione delle razze giocabili.</p>
    </header>

    <?php if ($op === 'insert'):
        $is_visible   = (($_POST['visible']   ?? '') === 'is_visible')   ? 1 : 0;
        $is_available = (($_POST['available'] ?? '') === 'is_available') ? 1 : 0;
        $immagine = empty($_POST['immagine']) ? 'standard_razza.png' : gdrcd_filter('in', $_POST['immagine']);
        $icon     = empty($_POST['icon'])     ? 'standard_razza.png' : gdrcd_filter('in', $_POST['icon']);

        gdrcd_query(
            "INSERT INTO razza (nome_razza, sing_m, sing_f, descrizione, visibile, iscrizione, immagine, icon,
                                bonus_car0, bonus_car1, bonus_car2, bonus_car3, bonus_car4, bonus_car5, url_site) VALUES ("
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . "'" . gdrcd_filter('in', $_POST['sing_m']) . "',"
            . "'" . gdrcd_filter('in', $_POST['sing_f']) . "',"
            . "'" . gdrcd_filter('in', $_POST['descrizione']) . "',"
            . $is_visible . "," . $is_available . ","
            . "'" . $immagine . "',"
            . "'" . $icon . "',"
            . gdrcd_filter('num', $_POST['car0']) . ","
            . gdrcd_filter('num', $_POST['car1']) . ","
            . gdrcd_filter('num', $_POST['car2']) . ","
            . gdrcd_filter('num', $_POST['car3']) . ","
            . gdrcd_filter('num', $_POST['car4']) . ","
            . gdrcd_filter('num', $_POST['car5']) . ","
            . "'" . gdrcd_filter('in', $_POST['url_site']) . "')"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['inserted']));
        echo '<div>' . $render_back() . '</div>';

    elseif ($op === 'erase'):
        $id = gdrcd_filter('num', $_POST['id_record']);
        gdrcd_query("DELETE FROM razza WHERE id_razza = " . $id . " LIMIT 1");
        gdrcd_query("UPDATE personaggio SET id_razza = 1000 WHERE id_razza = " . $id);
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['deleted']));
        echo '<div>' . $render_back() . '</div>';

    elseif ($op === 'modify'):
        $is_visible   = (($_POST['visible']   ?? '') === 'is_visible')   ? 1 : 0;
        $is_available = (($_POST['available'] ?? '') === 'is_available') ? 1 : 0;
        $immagine = empty($_POST['immagine']) ? 'standard_razza.png' : gdrcd_filter('in', $_POST['immagine']);
        $icon     = empty($_POST['icon'])     ? 'standard_razza.png' : gdrcd_filter('in', $_POST['icon']);

        gdrcd_query(
            "UPDATE razza SET
                nome_razza = '" . gdrcd_filter('in', $_POST['nome']) . "',
                sing_m = '" . gdrcd_filter('in', $_POST['sing_m']) . "',
                sing_f = '" . gdrcd_filter('in', $_POST['sing_f']) . "',
                descrizione = '" . gdrcd_filter('in', $_POST['descrizione']) . "',
                iscrizione = " . $is_available . ",
                visibile = " . $is_visible . ",
                icon = '" . $icon . "',
                immagine = '" . $immagine . "',
                bonus_car0 = " . gdrcd_filter('num', $_POST['car0']) . ",
                bonus_car1 = " . gdrcd_filter('num', $_POST['car1']) . ",
                bonus_car2 = " . gdrcd_filter('num', $_POST['car2']) . ",
                bonus_car3 = " . gdrcd_filter('num', $_POST['car3']) . ",
                bonus_car4 = " . gdrcd_filter('num', $_POST['car4']) . ",
                bonus_car5 = " . gdrcd_filter('num', $_POST['car5']) . ",
                url_site = '" . gdrcd_filter('in', $_POST['site']) . "'
             WHERE id_razza = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['modified']));
        echo '<div>' . $render_back() . '</div>';

    elseif ($op === 'edit' || $op === 'new'):
        $is_edit = ($op === 'edit');
        $loaded = $is_edit
            ? gdrcd_query("SELECT * FROM razza WHERE id_razza = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1")
            : [
                'id_razza' => 0, 'nome_razza' => '', 'sing_m' => '', 'sing_f' => '',
                'descrizione' => '', 'visibile' => 1, 'iscrizione' => 1,
                'immagine' => '', 'icon' => '', 'url_site' => '',
                'bonus_car0' => 0, 'bonus_car1' => 0, 'bonus_car2' => 0,
                'bonus_car3' => 0, 'bonus_car4' => 0, 'bonus_car5' => 0,
            ];
        ?>
        <form action="main.php?page=gestione_razze" method="post" class="space-y-5">
            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica razza' : 'Nuova razza' ?></h3>
                </div>
                <div class="gdrcd-card-body space-y-5">

                    <div class="gdrcd-eyebrow">Identità</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="gdrcd-label" for="ra_nome"><?= gdrcd_filter('out', $lbl['name']) ?></label>
                            <input class="gdrcd-input" type="text" id="ra_nome" name="nome"
                                   value="<?= gdrcd_filter('out', $loaded['nome_razza']) ?>" required/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="ra_sm"><?= gdrcd_filter('out', $lbl['name_sm']) ?></label>
                            <input class="gdrcd-input" type="text" id="ra_sm" name="sing_m"
                                   value="<?= gdrcd_filter('out', $loaded['sing_m']) ?>"/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="ra_sf"><?= gdrcd_filter('out', $lbl['name_sf']) ?></label>
                            <input class="gdrcd-input" type="text" id="ra_sf" name="sing_f"
                                   value="<?= gdrcd_filter('out', $loaded['sing_f']) ?>"/>
                        </div>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="ra_descr"><?= gdrcd_filter('out', $lbl['infos']) ?></label>
                        <textarea class="gdrcd-textarea" id="ra_descr" name="descrizione" rows="8"><?= gdrcd_filter('out', $loaded['descrizione']) ?></textarea>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-start gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                            <input type="checkbox" name="visible" value="is_visible"
                                   <?= ((int)$loaded['visibile'] === 1) ? 'checked' : '' ?>
                                   class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring mt-0.5"/>
                            <span>
                                <span class="font-medium"><?= gdrcd_filter('out', $lbl['is_visible']) ?></span>
                                <span class="block text-xs text-gdrcd-muted"><?= gdrcd_filter('out', $lbl['is_visible_info']) ?></span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                            <input type="checkbox" name="available" value="is_available"
                                   <?= ((int)$loaded['iscrizione'] === 1) ? 'checked' : '' ?>
                                   class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring mt-0.5"/>
                            <span>
                                <span class="font-medium"><?= gdrcd_filter('out', $lbl['is_avalaible']) ?></span>
                                <span class="block text-xs text-gdrcd-muted"><?= gdrcd_filter('out', $lbl['is_avalaible_info']) ?></span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Bonus caratteristiche</h3>
                </div>
                <div class="gdrcd-card-body">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <div>
                                <label class="block text-xs font-medium text-gdrcd-muted mb-1" for="ra_car<?= $i ?>">
                                    <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i]) ?>
                                </label>
                                <input class="gdrcd-input" type="number" id="ra_car<?= $i ?>" name="car<?= $i ?>"
                                       value="<?= (int)$loaded['bonus_car'.$i] ?>"/>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>

            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Immagini e collegamenti</h3>
                </div>
                <div class="gdrcd-card-body grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="gdrcd-label" for="ra_img"><?= gdrcd_filter('out', $lbl['image']) ?></label>
                        <input class="gdrcd-input" type="text" id="ra_img" name="immagine"
                               value="<?= gdrcd_filter('out', $loaded['immagine']) ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="ra_icon"><?= gdrcd_filter('out', $lbl['icon']) ?></label>
                        <input class="gdrcd-input" type="text" id="ra_icon" name="icon"
                               value="<?= gdrcd_filter('out', $loaded['icon']) ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="ra_site"><?= gdrcd_filter('out', $lbl['site']) ?></label>
                        <input class="gdrcd-input" type="url" id="ra_site" name="<?= $is_edit ? 'site' : 'url_site' ?>"
                               value="<?= gdrcd_filter('out', $loaded['url_site']) ?>"/>
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                <a href="main.php?page=gestione_razze" class="gdrcd-btn-ghost">Annulla</a>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id_record" value="<?= (int)$loaded['id_razza'] ?>"/>
                    <input type="hidden" name="op" value="modify"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= gdrcd_filter('out', $lbl['submit']['edit']) ?>
                    </button>
                <?php else: ?>
                    <input type="hidden" name="op" value="insert"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <?= gdrcd_filter('out', $lbl['submit']['insert']) ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>

    <?php else:
        $offset    = (int)($_REQUEST['offset'] ?? 0);
        $per_page  = (int)$PARAMETERS['settings']['records_per_page'];
        $pagebegin = $offset * $per_page;

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM razza");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT id_razza, nome_razza, visibile, iscrizione FROM razza ORDER BY nome_razza LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="gdrcd-muted text-xs"><?= $totaleresults ?> razze</span>
            <a href="main.php?page=gestione_razze&op=new" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $lbl['link']['new']) ?>
            </a>
        </div>

        <?php if ($numresults === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessuna razza presente.</div>
            </div>
        <?php else: ?>
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['name_col']) ?></th>
                            <th class="text-center w-32"><?= gdrcd_filter('out', $lbl['avalaible']) ?></th>
                            <th class="text-center w-32"><?= gdrcd_filter('out', $lbl['visible']) ?></th>
                            <th class="text-right w-[120px]"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                            <tr>
                                <td class="font-medium text-gdrcd-text"><?= gdrcd_filter('out', $row['nome_razza']) ?></td>
                                <td class="text-center">
                                    <?php if ((int)$row['iscrizione'] === 1): ?>
                                        <span class="gdrcd-badge-success"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['yes']) ?></span>
                                    <?php else: ?>
                                        <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['no']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$row['visibile'] === 1): ?>
                                        <span class="gdrcd-badge-success"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['yes']) ?></span>
                                    <?php else: ?>
                                        <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['no']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <form action="main.php?page=gestione_razze" method="post" class="inline-block">
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_razza'] ?>"/>
                                        <input type="hidden" name="op" value="edit"/>
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                                title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                    </form>
                                    <form action="main.php?page=gestione_razze" method="post" class="inline-block"
                                          onsubmit="return confirm('Eliminare questa razza? I PG correlati verranno spostati su razza id=1000.');">
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_razza'] ?>"/>
                                        <input type="hidden" name="op" value="erase"/>
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-error-soft hover:text-gdrcd-error transition-colors"
                                                title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['erase']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                        gdrcd_query($result, 'free');
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totaleresults > $per_page): ?>
                <nav class="gdrcd-pager" aria-label="Paginazione">
                    <span class="gdrcd-pager-label !border-0 !bg-transparent">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) ?>
                    </span>
                    <?php $pages = (int)floor($totaleresults / $per_page);
                    for ($i = 0; $i <= $pages; $i++):
                        if ($i === $offset): ?>
                            <span class="is-current" aria-current="page"><?= $i + 1 ?></span>
                        <?php else:
                            $url = 'main.php?' . http_build_query(['page' => 'gestione_razze', 'offset' => $i]); ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                        <?php endif;
                    endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
