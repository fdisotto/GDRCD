<?php
/**
 * Gestione bacheche/araldi (main.php?page=gestione_bacheche)
 * CRUD su tabella araldo: nome, tipo, proprietari (razza o gilda).
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl = $MESSAGE['interface']['administration']['forums'];
$op  = $_POST['op'] ?? $_GET['op'] ?? null;

$forum_types = [INGIOCO, PERTUTTI, SOLORAZZA, SOLOGILDA, SOLOMASTERS, SOLOMODERATORS];

$render_back = function () use ($lbl) {
    return '<a href="main.php?page=gestione_bacheche" class="gdrcd-btn-ghost">'
         . '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
         . gdrcd_filter('out', $lbl['link']['back'])
         . '</a>';
};
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Gestione delle bacheche araldo: nome, tipologia, proprietari (razza o gilda).</p>
    </header>

    <?php
    /* Normalizza owner in base a tipo: solo SOLORAZZA/SOLOGILDA hanno proprietari. */
    $sanitize_owner = function (int $tipo, $owner_raw) {
        if ($tipo === SOLORAZZA || $tipo === SOLOGILDA) {
            return gdrcd_filter('num', $owner_raw);
        }
        return -1;
    };

    if ($op === 'insert'):
        $tipo_post  = (int)gdrcd_filter('num', $_POST['tipo']);
        $owner_post = $sanitize_owner($tipo_post, $_POST['owner'] ?? -1);
        gdrcd_query(
            "INSERT INTO araldo (nome, tipo, proprietari) VALUES ("
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . $tipo_post . ","
            . $owner_post . ")"
        );
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div>
                <?= gdrcd_filter('out', $MESSAGE['warning']['inserted']) ?>
                <span class="text-gdrcd-muted">·</span>
                <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['nome']) ?></strong>
            </div>
        </div>
        <div><?= $render_back() ?></div>

    <?php elseif ($op === 'erase'):
        gdrcd_query("DELETE FROM messaggioaraldo WHERE id_araldo = " . gdrcd_filter('num', $_POST['id_record']));
        gdrcd_query("DELETE FROM araldo WHERE id_araldo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1");
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['warning']['deleted']) ?></div>
        </div>
        <div><?= $render_back() ?></div>

    <?php elseif ($op === 'doedit'):
        $tipo_post  = (int)gdrcd_filter('num', $_POST['tipo']);
        $owner_post = $sanitize_owner($tipo_post, $_POST['owner'] ?? -1);
        gdrcd_query(
            "UPDATE araldo SET
                nome = '" . gdrcd_filter('in', $_POST['nome']) . "',
                tipo = " . $tipo_post . ",
                proprietari = " . $owner_post . "
             WHERE id_araldo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1"
        );
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div>
                <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
                <span class="text-gdrcd-muted">·</span>
                <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['nome']) ?></strong>
            </div>
        </div>
        <div><?= $render_back() ?></div>

    <?php elseif ($op === 'edit' || $op === 'new'):
        $is_edit = ($op === 'edit');
        $loaded = $is_edit
            ? gdrcd_query("SELECT * FROM araldo WHERE id_araldo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1")
            : ['id_araldo' => 0, 'nome' => '', 'tipo' => INGIOCO, 'proprietari' => -1];
        ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica bacheca' : 'Nuova bacheca' ?></h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=gestione_bacheche" method="post" class="space-y-5">
                    <div>
                        <label class="gdrcd-label" for="bk_nome"><?= gdrcd_filter('out', $lbl['name']) ?></label>
                        <input class="gdrcd-input" type="text" id="bk_nome" name="nome"
                               value="<?= gdrcd_filter('out', $loaded['nome']) ?>" required/>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="bk_tipo"><?= gdrcd_filter('out', $lbl['type']['name']) ?></label>
                        <select class="gdrcd-select" id="bk_tipo" name="tipo">
                            <?php foreach ($forum_types as $t):
                                $sel = ((int)$loaded['tipo'] === $t) ? 'selected' : '';
                                ?>
                                <option value="<?= $t ?>" <?= $sel ?>>
                                    <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['type'][$t]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['type']['info']) ?></p>
                    </div>

                    <div id="bk_owner_wrap" data-show-on="<?= SOLORAZZA ?>,<?= SOLOGILDA ?>" class="hidden">
                        <label class="gdrcd-label" for="bk_owner"><?= gdrcd_filter('out', $lbl['owner']) ?></label>

                        <!-- Owner Razza -->
                        <select class="gdrcd-select"
                                id="bk_owner_razza"
                                name="owner_razza"
                                data-tipo="<?= SOLORAZZA ?>"
                                disabled>
                            <?php $razze = gdrcd_query("SELECT id_razza, nome_razza FROM razza ORDER BY nome_razza", 'result');
                            while ($r = gdrcd_query($razze, 'fetch')):
                                $sel = ((int)$loaded['proprietari'] === (int)$r['id_razza'] && (int)$loaded['tipo'] === SOLORAZZA) ? 'selected' : '';
                                ?>
                                <option value="<?= (int)$r['id_razza'] ?>" <?= $sel ?>>
                                    <?= gdrcd_filter('out', $r['nome_razza']) ?>
                                </option>
                            <?php endwhile;
                            gdrcd_query($razze, 'free');
                            ?>
                        </select>

                        <!-- Owner Gilda -->
                        <select class="gdrcd-select hidden"
                                id="bk_owner_gilda"
                                name="owner_gilda"
                                data-tipo="<?= SOLOGILDA ?>"
                                disabled>
                            <?php $gilde = gdrcd_query("SELECT id_gilda, nome FROM gilda ORDER BY nome", 'result');
                            while ($g = gdrcd_query($gilde, 'fetch')):
                                $sel = ((int)$loaded['proprietari'] === (int)$g['id_gilda'] && (int)$loaded['tipo'] === SOLOGILDA) ? 'selected' : '';
                                ?>
                                <option value="<?= (int)$g['id_gilda'] ?>" <?= $sel ?>>
                                    <?= gdrcd_filter('out', $g['nome']) ?>
                                </option>
                            <?php endwhile;
                            gdrcd_query($gilde, 'free');
                            ?>
                        </select>

                        <!-- owner finale che parte al submit, popolato via JS dal select attivo -->
                        <input type="hidden" name="owner" id="bk_owner" value="<?= (int)$loaded['proprietari'] ?>"/>
                    </div>

                    <script>
                        (function () {
                            const tipo  = document.getElementById('bk_tipo');
                            const wrap  = document.getElementById('bk_owner_wrap');
                            const razza = document.getElementById('bk_owner_razza');
                            const gilda = document.getElementById('bk_owner_gilda');
                            const owner = document.getElementById('bk_owner');

                            const SOLORAZZA = <?= SOLORAZZA ?>;
                            const SOLOGILDA = <?= SOLOGILDA ?>;

                            function sync() {
                                const t = parseInt(tipo.value, 10);
                                const showRazza = (t === SOLORAZZA);
                                const showGilda = (t === SOLOGILDA);
                                const showOwner = showRazza || showGilda;

                                wrap.classList.toggle('hidden', !showOwner);
                                razza.classList.toggle('hidden', !showRazza);
                                gilda.classList.toggle('hidden', !showGilda);
                                razza.disabled = !showRazza;
                                gilda.disabled = !showGilda;

                                if (showRazza)      owner.value = razza.value;
                                else if (showGilda) owner.value = gilda.value;
                                else                owner.value = -1;
                            }

                            tipo.addEventListener('change', sync);
                            razza.addEventListener('change', () => owner.value = razza.value);
                            gilda.addEventListener('change', () => owner.value = gilda.value);
                            sync();
                        })();
                    </script>

                    <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                        <a href="main.php?page=gestione_bacheche" class="gdrcd-btn-ghost">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            <?= gdrcd_filter('out', $lbl['submit']['undo'] ?? 'Annulla') ?>
                        </a>
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="id_record" value="<?= (int)$loaded['id_araldo'] ?>"/>
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

    <?php else:
        $offset    = (int)($_REQUEST['offset'] ?? 0);
        $per_page  = (int)$PARAMETERS['settings']['records_per_page'];
        $pagebegin = $offset * $per_page;

        $count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM araldo");
        $totaleresults = (int)$count_row['c'];

        $result = gdrcd_query(
            "SELECT id_araldo, nome, tipo FROM araldo ORDER BY nome LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $numresults = (int)gdrcd_query($result, 'num_rows');
        ?>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="gdrcd-muted text-xs"><?= $totaleresults ?> bacheche</span>
            <a href="main.php?page=gestione_bacheche&op=new" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $lbl['link']['new']) ?>
            </a>
        </div>

        <?php if ($numresults === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessuna bacheca presente.</div>
            </div>
        <?php else: ?>
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th><?= gdrcd_filter('out', $lbl['name']) ?></th>
                            <th class="whitespace-nowrap"><?= gdrcd_filter('out', $lbl['type']['name']) ?></th>
                            <th class="text-right w-[120px]"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = gdrcd_query($result, 'fetch')):
                            $type_label = $MESSAGE['interface']['forums']['type'][(int)$row['tipo']] ?? '?';
                            ?>
                            <tr>
                                <td class="font-medium text-gdrcd-text">
                                    <?= gdrcd_filter('out', $row['nome']) ?>
                                </td>
                                <td>
                                    <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $type_label) ?></span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <form action="main.php?page=gestione_bacheche" method="post" class="inline-block">
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_araldo'] ?>"/>
                                        <input type="hidden" name="op" value="edit"/>
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                                title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                    </form>
                                    <form action="main.php?page=gestione_bacheche" method="post" class="inline-block"
                                          onsubmit="return confirm('Eliminare questa bacheca e tutti i suoi messaggi?');">
                                        <input type="hidden" name="id_record" value="<?= (int)$row['id_araldo'] ?>"/>
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
                            $url = 'main.php?' . http_build_query([
                                'page' => 'gestione_bacheche', 'offset' => $i,
                            ]); ?>
                            <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                        <?php endif;
                    endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
