<?php
/**
 * Gestione gilde (main.php?page=gestione_gilde)
 * CRUD su gilda + ruoli annessi (tabella ruolo).
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl  = $MESSAGE['interface']['administration']['guilds'];
$op   = $_POST['op'] ?? $_GET['op'] ?? null;

$render_back = function (string $href = 'main.php?page=gestione_gilde') use ($lbl) {
    return '<a href="' . htmlspecialchars($href) . '" class="gdrcd-btn-ghost">'
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
        <p class="gdrcd-muted">Gestione gilde di gioco e dei relativi ruoli.</p>
    </header>

    <?php
    /* ============================================================
     * Handler POST
     * ============================================================ */
    if ($op === 'role_new') {
        $is_capo  = (($_POST['capo'] ?? '') === 'is_capo') ? 1 : 0;
        $immagine = empty($_POST['immagine']) ? 'standard_gilda.png' : gdrcd_filter('in', $_POST['immagine']);

        gdrcd_query(
            "INSERT INTO ruolo (nome_ruolo, gilda, immagine, stipendio, capo) VALUES ("
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . gdrcd_filter('num', $_POST['gilda']) . ","
            . "'" . $immagine . "',"
            . "'" . gdrcd_filter('num', $_POST['stipendio']) . "',"
            . "'" . $is_capo . "')"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['inserted']));
        $op = 'edit';
        $_REQUEST['id_record'] = $_POST['gilda'];
    } elseif ($op === 'insert') {
        $is_visible = (($_POST['visible'] ?? '') === 'is_visible') ? 1 : 0;
        $url_sito   = (($_POST['url_sito'] ?? '') === 'http://') ? '' : ($_POST['url_sito'] ?? '');
        $immagine   = empty($_POST['immagine']) ? 'standard_gilda.png' : gdrcd_filter('in', $_POST['immagine']);

        gdrcd_query(
            "INSERT INTO gilda (nome, tipo, immagine, url_sito, visibile, statuto) VALUES ("
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . gdrcd_filter('num', $_POST['tipo']) . ","
            . "'" . $immagine . "',"
            . "'" . gdrcd_filter('in', $url_sito) . "',"
            . "'" . $is_visible . "',"
            . "'" . gdrcd_filter('in', $_POST['statuto']) . "')"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['inserted']));
        echo '<div>' . $render_back() . '</div>';
        $op = '__done__';
    } elseif ($op === 'erase') {
        $id = gdrcd_filter('num', $_POST['id_record']);
        $result = gdrcd_query("SELECT id_ruolo FROM ruolo WHERE gilda = " . $id, 'result');
        while ($row = gdrcd_query($result, 'fetch')) {
            gdrcd_query("DELETE FROM clgpersonaggioruolo WHERE id_ruolo = " . gdrcd_filter('num', $row['id_ruolo']));
        }
        gdrcd_query($result, 'free');
        gdrcd_query("DELETE FROM ruolo WHERE gilda = " . $id);
        gdrcd_query("DELETE FROM gilda WHERE id_gilda = " . $id . " LIMIT 1");

        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['deleted']));
        echo '<div>' . $render_back() . '</div>';
        $op = '__done__';
    } elseif ($op === 'role_delete' && ($_POST['provenienza'] ?? '') === 'ruolo') {
        gdrcd_query("DELETE FROM clgpersonaggioruolo WHERE id_ruolo = " . gdrcd_filter('num', $_POST['id_ruolo']));
        gdrcd_query("DELETE FROM ruolo WHERE id_ruolo = " . gdrcd_filter('num', $_POST['id_ruolo']) . " LIMIT 1");

        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['deleted']));
        $op = 'edit';
        $_REQUEST['id_record'] = $_POST['gilda'];
    } elseif ($op === 'doedit' && !isset($_POST['provenienza'])) {
        $is_visible = (($_POST['visible'] ?? '') === 'is_visible') ? 1 : 0;
        $url_sito   = (($_POST['url_sito'] ?? '') === 'http://') ? '' : ($_POST['url_sito'] ?? '');
        $immagine   = empty($_POST['immagine']) ? 'standard_gilda.png' : gdrcd_filter('in', $_POST['immagine']);

        gdrcd_query(
            "UPDATE gilda SET
                nome = '" . gdrcd_filter('in', $_POST['nome']) . "',
                visibile = " . $is_visible . ",
                immagine = '" . $immagine . "',
                tipo = " . gdrcd_filter('num', $_POST['tipo']) . ",
                url_sito = '" . gdrcd_filter('in', $url_sito) . "',
                statuto = '" . gdrcd_filter('in', $_POST['statuto']) . "'
             WHERE id_gilda = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['modified']));
        $op = 'edit';
    } elseif ($op === 'role_edit' && ($_POST['provenienza'] ?? '') === 'ruolo') {
        $is_capo  = (($_POST['capo'] ?? '') === 'is_capo') ? 1 : 0;
        $immagine = empty($_POST['immagine']) ? 'standard_gilda.png' : gdrcd_filter('in', $_POST['immagine']);

        gdrcd_query(
            "UPDATE ruolo SET
                nome_ruolo = '" . gdrcd_filter('in', $_POST['nome']) . "',
                capo = " . $is_capo . ",
                immagine = '" . $immagine . "',
                gilda = " . gdrcd_filter('num', $_POST['gilda']) . ",
                stipendio = " . gdrcd_filter('num', $_POST['stipendio']) . "
             WHERE id_ruolo = " . gdrcd_filter('num', $_POST['id_ruolo']) . " LIMIT 1"
        );
        echo $render_alert_success(gdrcd_filter('out', $MESSAGE['warning']['modified']));
        $op = 'edit';
        $_REQUEST['id_record'] = $_POST['gilda'];
    }
    ?>

    <?php
    /* ============================================================
     * Vista: edit (modifica gilda + gestione ruoli) / new (nuova gilda)
     * ============================================================ */
    if ($op === 'edit' || $op === 'new'):
        $id_record = (int)($_REQUEST['id_record'] ?? -1);
        $is_edit   = ($op === 'edit' && $id_record > 0);
        $loaded    = $is_edit
            ? gdrcd_query("SELECT * FROM gilda WHERE id_gilda = " . $id_record . " LIMIT 1")
            : ['id_gilda' => 0, 'nome' => '', 'tipo' => 0, 'immagine' => '', 'url_sito' => 'http://', 'statuto' => '', 'visibile' => 0];
        ?>

        <?php if ($op === 'new' || $is_edit): ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica gilda' : 'Nuova gilda' ?></h3>
                </div>
                <div class="gdrcd-card-body">
                    <form action="main.php?page=gestione_gilde" method="post" class="space-y-5">
                        <?= gdrcd_csrf_field() ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="gdrcd-label" for="gg_nome"><?= gdrcd_filter('out', $lbl['name']) ?></label>
                                <input class="gdrcd-input" type="text" id="gg_nome" name="nome"
                                       value="<?= gdrcd_filter('out', $loaded['nome']) ?>" required/>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="gg_tipo"><?= gdrcd_filter('out', $lbl['type']) ?></label>
                                <?php $tipi = gdrcd_query("SELECT cod_tipo, descrizione FROM codtipogilda", 'result');
                                if (gdrcd_query($tipi, 'num_rows') > 0): ?>
                                    <select class="gdrcd-select" id="gg_tipo" name="tipo">
                                        <?php while ($t = gdrcd_query($tipi, 'fetch')):
                                            $sel = ((int)$loaded['tipo'] === (int)$t['cod_tipo']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= (int)$t['cod_tipo'] ?>" <?= $sel ?>>
                                                <?= gdrcd_filter('out', $t['descrizione']) ?>
                                            </option>
                                        <?php endwhile;
                                        gdrcd_query($tipi, 'free');
                                        ?>
                                    </select>
                                <?php else: ?>
                                    <div class="gdrcd-alert-warning text-xs">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>
                                        <div><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['locations']['type_err']) ?></div>
                                    </div>
                                <?php endif; ?>
                                <p class="gdrcd-help">
                                    <a class="gdrcd-link" href="main.php?page=gestione_tipi&types=guilds">
                                        <?= gdrcd_filter('out', $lbl['link']['menage_types']) ?>
                                    </a>
                                </p>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="gg_imm"><?= gdrcd_filter('out', $lbl['image']) ?></label>
                                <input class="gdrcd-input" type="text" id="gg_imm" name="immagine"
                                       value="<?= gdrcd_filter('out', $loaded['immagine']) ?>"/>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="gg_url"><?= gdrcd_filter('out', $lbl['site']) ?></label>
                                <input class="gdrcd-input" type="text" id="gg_url" name="url_sito"
                                       value="<?= gdrcd_filter('out', $loaded['url_sito'] ?? 'http://') ?>"/>
                            </div>
                        </div>

                        <div>
                            <label class="gdrcd-label" for="gg_statuto">Statuto</label>
                            <textarea class="gdrcd-textarea" id="gg_statuto" name="statuto" rows="8" data-bbcode><?= gdrcd_filter('out', $loaded['statuto']) ?></textarea>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                            <input type="checkbox" name="visible" value="is_visible"
                                   <?= ((int)$loaded['visibile'] === 1) ? 'checked' : '' ?>
                                   class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                            <span><?= gdrcd_filter('out', $lbl['visible']) ?></span>
                        </label>
                        <p class="gdrcd-help -mt-3"><?= gdrcd_filter('out', $lbl['visible_info']) ?></p>

                        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                            <a href="main.php?page=gestione_gilde" class="gdrcd-btn-ghost">Annulla</a>
                            <?php if ($is_edit): ?>
                                <input type="hidden" name="id_record" value="<?= (int)$loaded['id_gilda'] ?>"/>
                                <input type="hidden" name="op" value="doedit"/>
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
                </div>
            </section>
        <?php endif; ?>

        <?php if ($is_edit): ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl['role']['page_name']) ?></h3>
                </div>
                <div class="gdrcd-card-body space-y-6">

                    <form action="main.php?page=gestione_gilde" method="post" class="border-b border-gdrcd-border pb-5 space-y-4">
                        <?= gdrcd_csrf_field() ?>
                        <div class="gdrcd-eyebrow"><?= gdrcd_filter('out', $lbl['role']['name_new']) ?></div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="gdrcd-label" for="rn_nome">Nome</label>
                                <input class="gdrcd-input" type="text" id="rn_nome" name="nome" required/>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="rn_imm"><?= gdrcd_filter('out', $lbl['image']) ?></label>
                                <input class="gdrcd-input" type="text" id="rn_imm" name="immagine"/>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="rn_pay"><?= gdrcd_filter('out', $lbl['role']['pay']) ?></label>
                                <input class="gdrcd-input" type="number" id="rn_pay" name="stipendio" value="0"/>
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                            <input type="checkbox" name="capo" value="is_capo"
                                   class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                            <span><?= gdrcd_filter('out', $lbl['role']['head']) ?></span>
                        </label>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['role']['head_info']) ?></p>
                        <div class="flex justify-end">
                            <input type="hidden" name="gilda" value="<?= (int)$loaded['id_gilda'] ?>"/>
                            <input type="hidden" name="op" value="role_new"/>
                            <button type="submit" class="gdrcd-btn-secondary">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                <?= gdrcd_filter('out', $lbl['submit']['insert']) ?>
                            </button>
                        </div>
                    </form>

                    <?php
                    $roles = gdrcd_query("SELECT * FROM ruolo WHERE gilda = " . (int)$loaded['id_gilda'] . " ORDER BY capo DESC, stipendio DESC", 'result');
                    if (gdrcd_query($roles, 'num_rows') === 0): ?>
                        <div class="gdrcd-alert-info">
                            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>Nessun ruolo in questa gilda.</div>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php while ($r = gdrcd_query($roles, 'fetch')): ?>
                                <form action="main.php?page=gestione_gilde" method="post"
                                      class="border border-gdrcd-border rounded-gdrcd p-4 bg-gdrcd-panel-alt/30 space-y-3">
                                    <?= gdrcd_csrf_field() ?>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="gdrcd-label">Nome</label>
                                            <input class="gdrcd-input" name="nome" value="<?= gdrcd_filter('out', $r['nome_ruolo']) ?>"/>
                                        </div>
                                        <div>
                                            <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl['image']) ?></label>
                                            <input class="gdrcd-input" name="immagine" value="<?= gdrcd_filter('out', $r['immagine']) ?>"/>
                                        </div>
                                        <div>
                                            <label class="gdrcd-label"><?= gdrcd_filter('out', $lbl['role']['pay']) ?></label>
                                            <input class="gdrcd-input" type="number" name="stipendio" value="<?= (int)$r['stipendio'] ?>"/>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                                            <input type="checkbox" name="capo" value="is_capo"
                                                   <?= ((int)$r['capo'] === 1) ? 'checked' : '' ?>
                                                   class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                                            <span><?= gdrcd_filter('out', $lbl['role']['head']) ?></span>
                                        </label>
                                        <div class="flex gap-2">
                                            <input type="hidden" name="provenienza" value="ruolo"/>
                                            <input type="hidden" name="id_ruolo" value="<?= (int)$r['id_ruolo'] ?>"/>
                                            <input type="hidden" name="gilda" value="<?= (int)$loaded['id_gilda'] ?>"/>
                                            <button type="submit" name="op" value="role_edit" class="gdrcd-btn-primary">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                <?= gdrcd_filter('out', $lbl['role']['submit']['edit']) ?>
                                            </button>
                                            <button type="submit" name="op" value="role_delete" class="gdrcd-btn-danger"
                                                    onclick="return confirm('Eliminare questo ruolo?');">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                                <?= gdrcd_filter('out', $lbl['role']['submit']['delete']) ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endwhile;
                            gdrcd_query($roles, 'free');
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <div><?= $render_back() ?></div>

    <?php elseif ($op === null):
        /* ============================================================
         * Lista gilde
         * ============================================================ */
        $offset    = (int)($_REQUEST['offset'] ?? 0);
        $per_page  = (int)$PARAMETERS['settings']['records_per_page'];
        $pagebegin = $offset * $per_page;

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM gilda");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT gilda.id_gilda, gilda.nome, gilda.visibile, codtipogilda.descrizione
             FROM gilda LEFT JOIN codtipogilda ON gilda.tipo = codtipogilda.cod_tipo
             ORDER BY nome LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="gdrcd-muted text-xs"><?= $totaleresults ?> gilde</span>
            <div class="flex flex-wrap gap-2">
                <a href="main.php?page=gestione_tipi&types=guilds" class="gdrcd-btn-ghost">
                    <?= gdrcd_filter('out', $lbl['link']['menage_types']) ?>
                </a>
                <a href="main.php?page=gestione_gilde&op=new" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <?= gdrcd_filter('out', $lbl['link']['new']) ?>
                </a>
            </div>
        </div>

        <?php if ($numresults === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessuna gilda presente.</div>
            </div>
        <?php else: ?>
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['name_col']) ?></th>
                            <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['type']) ?></th>
                            <th class="whitespace-nowrap text-center w-24"><?= gdrcd_filter('out', $lbl['visible']) ?></th>
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
                                    <?php if (!empty($row['descrizione'])): ?>
                                        <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $row['descrizione']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gdrcd-subtle">—</span>
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
                                    <a href="main.php?page=gestione_gilde&op=edit&id_record=<?= (int)$row['id_gilda'] ?>"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                       title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </a>
                                    <form action="main.php?page=gestione_gilde" method="post" class="inline-block"
                                          onsubmit="return confirm('Eliminare questa gilda e tutti i suoi ruoli?');">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_gilda'] ?>"/>
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
                            $url = 'main.php?' . http_build_query(['page' => 'gestione_gilde', 'offset' => $i]); ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                        <?php endif;
                    endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
