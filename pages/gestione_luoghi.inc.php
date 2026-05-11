<?php
/**
 * Gestione luoghi (main.php?page=gestione_luoghi)
 * CRUD su tabella mappa (locazioni di gioco).
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl = $MESSAGE['interface']['administration']['locations'];
$op  = $_POST['op'] ?? $_GET['op'] ?? null;

$render_back = function () use ($lbl) {
    return '<a href="main.php?page=gestione_luoghi" class="gdrcd-btn-ghost">'
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
        <p class="gdrcd-muted">Gestione delle locazioni di gioco (stanze, ambienti, mappe).</p>
    </header>

    <?php if ($op === 'insert'):
        $is_chat   = (($_POST['chat']    ?? '') === 'is_chat')   ? 1 : 0;
        $is_privat = (($_POST['privata'] ?? '') === 'is_privat') ? 1 : 0;
        $immagine  = empty($_POST['immagine']) ? 'standard_luogo.png' : gdrcd_filter('in', $_POST['immagine']);

        gdrcd_query(
            "INSERT INTO mappa (nome, descrizione, stato, pagina, chat, immagine, stanza_apparente, id_mappa,
                                link_immagine, link_immagine_hover, id_mappa_collegata, x_cord, y_cord,
                                privata, proprietario, scadenza, costo, invitati) VALUES ("
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . "'" . gdrcd_filter('in', $_POST['descrizione']) . "',"
            . "'" . gdrcd_filter('in', $_POST['stato']) . "',"
            . "'" . gdrcd_filter('in', $_POST['pagina']) . "',"
            . $is_chat . ","
            . "'" . $immagine . "',"
            . "'" . gdrcd_filter('in', $_POST['stanza_apparente']) . "',"
            . gdrcd_filter('num', $_POST['mappa']) . ","
            . "'" . gdrcd_filter('in', $_POST['image_button']) . "',"
            . "'" . gdrcd_filter('in', $_POST['image_button_hover']) . "',"
            . gdrcd_filter('num', $_POST['mappa_linked']) . ","
            . gdrcd_filter('num', $_POST['x_cord']) . ","
            . gdrcd_filter('num', $_POST['y_cord']) . ","
            . $is_privat . ","
            . "'" . gdrcd_filter('in', $_POST['proprietario']) . "',"
            . "'" . gdrcd_filter('num', $_POST['year']) . "-" . gdrcd_filter('num', $_POST['month']) . "-" . gdrcd_filter('num', $_POST['day']) . " 00:00:00',"
            . gdrcd_filter('num', $_POST['costo']) . ", '')"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['inserted']));
        echo '<div>' . $render_back() . '</div>';

    elseif ($op === 'erase'):
        gdrcd_query("DELETE FROM mappa WHERE id = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1");
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['deleted']));
        echo '<div>' . $render_back() . '</div>';

    elseif ($op === 'modify'):
        $is_chat   = (($_POST['chat']    ?? '') === 'is_chat')   ? 1 : 0;
        $is_privat = (($_POST['privata'] ?? '') === 'is_privat') ? 1 : 0;

        gdrcd_query(
            "UPDATE mappa SET
                nome = '" . gdrcd_filter('in', $_POST['nome']) . "',
                descrizione = '" . gdrcd_filter('in', $_POST['descrizione']) . "',
                stato = '" . gdrcd_filter('in', $_POST['stato']) . "',
                chat = " . $is_chat . ",
                immagine = '" . gdrcd_filter('in', $_POST['immagine']) . "',
                stanza_apparente = '" . gdrcd_filter('in', $_POST['stanza_apparente']) . "',
                pagina = '" . gdrcd_filter('in', $_POST['pagina']) . "',
                id_mappa = " . gdrcd_filter('num', $_POST['mappa']) . ",
                link_immagine = '" . gdrcd_filter('in', $_POST['image_button']) . "',
                link_immagine_hover = '" . gdrcd_filter('in', $_POST['image_button_hover']) . "',
                id_mappa_collegata = " . gdrcd_filter('num', $_POST['mappa_linked']) . ",
                x_cord = " . gdrcd_filter('num', $_POST['x_cord']) . ",
                y_cord = " . gdrcd_filter('num', $_POST['y_cord']) . ",
                privata = " . $is_privat . ",
                proprietario = '" . gdrcd_filter('in', $_POST['proprietario']) . "',
                scadenza = '" . gdrcd_filter('num', $_POST['year']) . "-" . gdrcd_filter('num', $_POST['month']) . "-" . gdrcd_filter('num', $_POST['day']) . " 00:00:00',
                costo = " . gdrcd_filter('num', $_POST['costo']) . "
             WHERE id = " . gdrcd_filter('num', $_POST['id_mappa']) . " LIMIT 1"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['modified']));
        echo '<div>' . $render_back() . '</div>';

    elseif ($op === 'edit' || $op === 'new'):
        $is_edit = ($op === 'edit');
        $loaded  = $is_edit
            ? gdrcd_query("SELECT * FROM mappa WHERE id = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1")
            : [
                'id' => 0, 'nome' => '', 'descrizione' => '', 'stato' => '', 'pagina' => '',
                'chat' => 1, 'immagine' => '', 'stanza_apparente' => '', 'id_mappa' => -1,
                'link_immagine' => '', 'link_immagine_hover' => '', 'id_mappa_collegata' => 0,
                'x_cord' => 0, 'y_cord' => 0, 'privata' => 0, 'proprietario' => '',
                'scadenza' => '0000-00-00 00:00:00', 'costo' => 0,
            ];

        // Parsing scadenza
        $exp_parts = explode(' ', $loaded['scadenza'] ?? '0000-00-00 00:00:00');
        $exp_ymd   = explode('-', $exp_parts[0] ?? '0000-00-00');
        $exp_y = (int)($exp_ymd[0] ?? 0);
        $exp_m = (int)($exp_ymd[1] ?? 0);
        $exp_d = (int)($exp_ymd[2] ?? 0);

        $private_rooms_on = (($PARAMETERS['mode']['privaterooms'] ?? 'OFF') === 'ON');
        ?>

        <form action="main.php?page=gestione_luoghi" method="post" class="space-y-5">
            <?= gdrcd_csrf_field() ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica luogo' : 'Nuovo luogo' ?></h3>
                </div>
                <div class="gdrcd-card-body space-y-5">

                    <div class="gdrcd-eyebrow">Identità</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label" for="lg_nome"><?= gdrcd_filter('out', $lbl['name']) ?></label>
                            <input class="gdrcd-input" type="text" id="lg_nome" name="nome"
                                   value="<?= gdrcd_filter('out', $loaded['nome']) ?>" required/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lg_stanza"><?= gdrcd_filter('out', $lbl['screen_name']) ?></label>
                            <input class="gdrcd-input" type="text" id="lg_stanza" name="stanza_apparente"
                                   value="<?= gdrcd_filter('out', $loaded['stanza_apparente']) ?>"/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['screen_name_info']) ?></p>
                        </div>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="lg_descr"><?= gdrcd_filter('out', $lbl['description']) ?></label>
                        <textarea class="gdrcd-textarea" id="lg_descr" name="descrizione" rows="4"><?= gdrcd_filter('out', $loaded['descrizione']) ?></textarea>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="lg_stato"><?= gdrcd_filter('out', $lbl['status']) ?></label>
                        <textarea class="gdrcd-textarea" id="lg_stato" name="stato" rows="3"><?= gdrcd_filter('out', $loaded['stato']) ?></textarea>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                        <input type="checkbox" name="chat" value="is_chat"
                               <?= ((int)$loaded['chat'] === 1) ? 'checked' : '' ?>
                               class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                        <span><?= gdrcd_filter('out', $lbl['is_chat']) ?></span>
                    </label>
                    <p class="gdrcd-help -mt-3"><?= gdrcd_filter('out', $lbl['is_chat_info']) ?></p>
                </div>
            </section>

            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Visualizzazione</h3>
                </div>
                <div class="gdrcd-card-body space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label" for="lg_imm"><?= gdrcd_filter('out', $lbl['image']) ?></label>
                            <input class="gdrcd-input" type="text" id="lg_imm" name="immagine"
                                   value="<?= gdrcd_filter('out', $loaded['immagine']) ?>"/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['image_info']) ?></p>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lg_pagina"><?= gdrcd_filter('out', $lbl['page']) ?></label>
                            <input class="gdrcd-input" type="text" id="lg_pagina" name="pagina"
                                   value="<?= gdrcd_filter('out', $loaded['pagina']) ?>"/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lg_btn"><?= gdrcd_filter('out', $lbl['image_button']) ?></label>
                            <input class="gdrcd-input" type="text" id="lg_btn" name="image_button"
                                   value="<?= gdrcd_filter('out', $loaded['link_immagine']) ?>"/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['image_button_info']) ?></p>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lg_btn_h"><?= gdrcd_filter('out', $lbl['image_button_hover']) ?></label>
                            <input class="gdrcd-input" type="text" id="lg_btn_h" name="image_button_hover"
                                   value="<?= gdrcd_filter('out', $loaded['link_immagine_hover']) ?>"/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['image_button_hover_info']) ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Mappa e posizione</h3>
                </div>
                <div class="gdrcd-card-body space-y-5">
                    <?php
                    $render_mappa_select = function (string $name, $current, bool $allow_zero = false) use ($lbl) {
                        $mappe = gdrcd_query("SELECT id_click, nome FROM mappa_click", 'result');
                        if (gdrcd_query($mappe, 'num_rows') === 0) {
                            gdrcd_query($mappe, 'free');
                            return '<div class="gdrcd-alert-warning text-xs">'
                                 . '<svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>'
                                 . '<div>' . gdrcd_filter('out', $lbl['map_id_err']) . '</div></div>';
                        }
                        $out = '<select class="gdrcd-select" name="' . $name . '">';
                        $default_val = $allow_zero ? 0 : -1;
                        $out .= '<option value="' . $default_val . '">' . gdrcd_filter('out', $lbl['map_id_default']) . '</option>';
                        while ($o = gdrcd_query($mappe, 'fetch')) {
                            $sel = ((int)$current === (int)$o['id_click']) ? 'selected' : '';
                            $out .= '<option value="' . (int)$o['id_click'] . '" ' . $sel . '>' . gdrcd_filter('out', $o['nome']) . '</option>';
                        }
                        gdrcd_query($mappe, 'free');
                        $out .= '</select>';
                        return $out;
                    };
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl['map_id']) ?></label>
                            <?= $render_mappa_select('mappa', $loaded['id_mappa']) ?>
                        </div>
                        <div>
                            <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl['map_related']) ?></label>
                            <?= $render_mappa_select('mappa_linked', $loaded['id_mappa_collegata'], true) ?>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['map_related_info']) ?></p>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lg_x"><?= gdrcd_filter('out', $lbl['x']) ?></label>
                            <input class="gdrcd-input" type="number" id="lg_x" name="x_cord"
                                   value="<?= (int)$loaded['x_cord'] ?>"/>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="lg_y"><?= gdrcd_filter('out', $lbl['y']) ?></label>
                            <input class="gdrcd-input" type="number" id="lg_y" name="y_cord"
                                   value="<?= (int)$loaded['y_cord'] ?>"/>
                        </div>
                    </div>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['x_info']) ?></p>
                </div>
            </section>

            <?php if ($private_rooms_on): ?>
                <section class="gdrcd-card">
                    <div class="gdrcd-card-header">
                        <h3 class="gdrcd-h3">Stanza privata</h3>
                    </div>
                    <div class="gdrcd-card-body space-y-5">
                        <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                            <input type="checkbox" name="privata" value="is_privat"
                                   <?= ((int)$loaded['privata'] === 1) ? 'checked' : '' ?>
                                   class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                            <span><?= gdrcd_filter('out', $lbl['is_privat']) ?></span>
                        </label>
                        <p class="gdrcd-help -mt-3"><?= gdrcd_filter('out', $lbl['is_privat_info']) ?></p>

                        <div>
                            <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl['owner']) ?></label>
                            <select class="gdrcd-select" name="proprietario">
                                <option value="<?= gdrcd_filter('out', $lbl['owner_default']) ?>">
                                    <?= gdrcd_filter('out', $lbl['owner_default']) ?>
                                </option>
                                <optgroup label="<?= gdrcd_filter('out', $PARAMETERS['names']['guild_name']['plur']) ?>">
                                    <?php $gilde = gdrcd_query("SELECT id_gilda, nome FROM gilda ORDER BY nome", 'result');
                                    while ($g = gdrcd_query($gilde, 'fetch')):
                                        $sel = ((string)$loaded['proprietario'] === (string)$g['id_gilda']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= gdrcd_filter('out', $g['id_gilda']) ?>" <?= $sel ?>>
                                            <?= gdrcd_filter('out', $g['nome']) ?>
                                        </option>
                                    <?php endwhile;
                                    gdrcd_query($gilde, 'free');
                                    ?>
                                </optgroup>
                                <optgroup label="<?= gdrcd_filter('out', $PARAMETERS['names']['users_name']['plur']) ?>">
                                    <?php $pgs = gdrcd_query("SELECT nome, cognome FROM personaggio ORDER BY nome", 'result');
                                    while ($p = gdrcd_query($pgs, 'fetch')):
                                        $sel = ((string)$loaded['proprietario'] === (string)$p['nome']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= gdrcd_filter('out', $p['nome']) ?>" <?= $sel ?>>
                                            <?= gdrcd_filter('out', $p['nome'] . ' ' . $p['cognome']) ?>
                                        </option>
                                    <?php endwhile;
                                    gdrcd_query($pgs, 'free');
                                    ?>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl['expiration_date']) ?></label>
                            <div class="grid grid-cols-3 gap-2 max-w-md">
                                <select class="gdrcd-select" name="day">
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($exp_d === $i) ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="gdrcd-select" name="month">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($exp_m === $i) ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="gdrcd-select" name="year">
                                    <?php for ($i = (int)date('Y'); $i <= (int)date('Y') + 20; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($exp_y === $i) ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="gdrcd-label" for="lg_costo"><?= gdrcd_filter('out', $lbl['rent']) ?></label>
                            <input class="gdrcd-input max-w-xs" type="number" id="lg_costo" name="costo"
                                   value="<?= (int)$loaded['costo'] ?>"/>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <input type="hidden" name="proprietario" value=""/>
                <input type="hidden" name="day" value="00"/>
                <input type="hidden" name="month" value="00"/>
                <input type="hidden" name="year" value="0000"/>
                <input type="hidden" name="costo" value="0"/>
            <?php endif; ?>

            <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                <a href="main.php?page=gestione_luoghi" class="gdrcd-btn-ghost">Annulla</a>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id_mappa" value="<?= (int)$loaded['id'] ?>"/>
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

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM mappa");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT mappa.id, mappa.nome, mappa_click.nome AS mappa_click
             FROM mappa LEFT JOIN mappa_click ON mappa.id_mappa = mappa_click.id_click
             ORDER BY mappa.nome LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="gdrcd-muted text-xs"><?= $totaleresults ?> luoghi</span>
            <a href="main.php?page=gestione_luoghi&op=new" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $lbl['link']['new']) ?>
            </a>
        </div>

        <?php if ($numresults === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessun luogo presente.</div>
            </div>
        <?php else: ?>
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['name_col']) ?></th>
                            <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['map_name']) ?></th>
                            <th class="text-right w-[120px]"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                            <tr>
                                <td class="font-medium text-gdrcd-text">
                                    <?= gdrcd_filter('out', $row['nome']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['mappa_click'])): ?>
                                        <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $row['mappa_click']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gdrcd-subtle">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <form action="main.php?page=gestione_luoghi" method="post" class="inline-block">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id'] ?>"/>
                                        <input type="hidden" name="op" value="edit"/>
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                                title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                    </form>
                                    <form action="main.php?page=gestione_luoghi" method="post" class="inline-block"
                                          onsubmit="return confirm('Eliminare questo luogo?');">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id'] ?>"/>
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
                            $url = 'main.php?' . http_build_query(['page' => 'gestione_luoghi', 'offset' => $i]); ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                        <?php endif;
                    endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
