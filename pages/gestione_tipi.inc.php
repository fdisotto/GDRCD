<?php
/**
 * Gestione tipi oggetto/gilda — CRUD admin.
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div></div>';
    return;
}

$types = isset($_REQUEST['types']) ? gdrcd_filter('get', $_REQUEST['types']) : 'items';
$tbl_codtipo  = $types === 'items' ? 'codtipooggetto' : 'codtipogilda';
$tbl_resource = $types === 'items' ? 'oggetto'       : 'gilda';
$ref_back_url = 'main.php?page=gestione_tipi&types=' . urlencode($types);

$op = $_POST['op'] ?? ($_REQUEST['op'] ?? null);
$alerts = [];

if ($op === 'insert' && !empty($_POST['nome'])) {
    gdrcd_query("INSERT INTO {$tbl_codtipo} (descrizione) VALUES ('" . gdrcd_filter('in', $_POST['nome']) . "')");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['inserted'])];
} elseif ($op === 'erase' && !empty($_POST['id_record'])) {
    $id = gdrcd_filter('num', $_POST['id_record']);
    gdrcd_query("DELETE FROM {$tbl_codtipo} WHERE cod_tipo = " . $id . " LIMIT 1");
    gdrcd_query("UPDATE {$tbl_resource} SET tipo = 0 WHERE tipo = " . $id);
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['deleted'])];
} elseif ($op === 'modify' && !empty($_POST['id_record'])) {
    gdrcd_query("UPDATE {$tbl_codtipo} SET descrizione = '" . gdrcd_filter('in', $_POST['nome']) . "'
                 WHERE cod_tipo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
}

$is_edit_form = ($op === 'edit' || $op === 'new');
$loaded_record = null;
if ($op === 'edit' && !empty($_POST['id_record'])) {
    $loaded_record = gdrcd_query("SELECT * FROM {$tbl_codtipo} WHERE cod_tipo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1");
}

$lbl  = gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['page_name'][$types]);
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </span>
            <?= $lbl ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($is_edit_form):
        $operation = $loaded_record ? 'modify' : 'insert';
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3">
                    <?= $loaded_record
                        ? gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['submit']['edit'])
                        : gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['submit']['insert']) ?>
                </h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="<?= htmlspecialchars($ref_back_url) ?>" method="post" class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 items-end">
                    <?= gdrcd_csrf_field() ?>
                    <label class="block">
                        <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">
                            <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['name']) ?>
                        </span>
                        <input type="text" name="nome"
                               value="<?= $loaded_record ? gdrcd_filter('out', $loaded_record['descrizione']) : '' ?>"
                               class="gdrcd-input mt-1 w-full" required>
                    </label>
                    <div class="flex gap-2">
                        <?php if ($loaded_record): ?>
                            <input type="hidden" name="id_record" value="<?= (int)$loaded_record['cod_tipo'] ?>">
                            <input type="hidden" name="op" value="modify">
                            <button type="submit" class="gdrcd-btn-primary">
                                <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['submit']['edit']) ?>
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="op" value="insert">
                            <button type="submit" class="gdrcd-btn-primary">
                                <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['submit']['insert']) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </article>

        <div>
            <a href="<?= htmlspecialchars($ref_back_url) ?>" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['link']['back']) ?>
            </a>
        </div>
    <?php else:
        // Visualizzazione di base con paginazione
        $offset = (int)($_REQUEST['offset'] ?? 0);
        $per_page = (int)$PARAMETERS['settings']['records_per_page'];
        $pagebegin = $offset * $per_page;

        $tot_res = gdrcd_query("SELECT COUNT(*) AS n FROM {$tbl_codtipo}");
        $totale = (int)($tot_res['n'] ?? 0);
        $result = gdrcd_query(
            "SELECT cod_tipo, descrizione FROM {$tbl_codtipo}
             ORDER BY descrizione LIMIT " . $pagebegin . ", " . $per_page,
            'result'
        );
        $num = gdrcd_query($result, 'num_rows');
    ?>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm text-gdrcd-text-soft tabular-nums"><?= $totale ?> element<?= $totale === 1 ? 'o' : 'i' ?></span>
            <a href="<?= htmlspecialchars($ref_back_url) ?>&op=new" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['link']['new']) ?>
            </a>
        </div>

        <?php if ($num === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
                <div>Nessun tipo registrato.</div>
            </div>
        <?php else: ?>
            <article class="gdrcd-card">
                <div class="overflow-x-auto">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['name']) ?></th>
                                <th class="tabular-nums"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['code']) ?></th>
                                <th class="text-right"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops_col']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                                <tr>
                                    <td class="font-display"><?= gdrcd_filter('out', $row['descrizione']) ?></td>
                                    <td class="tabular-nums text-sm text-gdrcd-text-soft"><?= (int)$row['cod_tipo'] ?></td>
                                    <td class="text-right">
                                        <div class="inline-flex gap-1 justify-end">
                                            <form action="<?= htmlspecialchars($ref_back_url) ?>" method="post" class="inline">
                                                <?= gdrcd_csrf_field() ?>
                                                <input type="hidden" name="id_record" value="<?= (int)$row['cod_tipo'] ?>">
                                                <input type="hidden" name="op" value="edit">
                                                <button type="submit" class="gdrcd-btn-ghost p-1.5" title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                            </form>
                                            <form action="<?= htmlspecialchars($ref_back_url) ?>" method="post" class="inline" onsubmit="return confirm('Eliminare definitivamente?');">
                                                <?= gdrcd_csrf_field() ?>
                                                <input type="hidden" name="id_record" value="<?= (int)$row['cod_tipo'] ?>">
                                                <input type="hidden" name="op" value="erase">
                                                <button type="submit" class="gdrcd-btn-ghost p-1.5 text-gdrcd-error" title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['erase']) ?>">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22m-9 0V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; gdrcd_query($result, 'free'); ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <?php if ($totale > $per_page): ?>
                <nav class="gdrcd-pager flex flex-wrap gap-1 justify-center" aria-label="Paginazione">
                    <span class="text-sm text-gdrcd-text-soft mr-2"><?= gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) ?></span>
                    <?php for ($i = 0; $i <= floor($totale / $per_page); $i++): ?>
                        <a href="<?= htmlspecialchars($ref_back_url) ?>&offset=<?= $i ?>"
                           class="px-2 py-1 rounded text-sm <?= ($offset === $i) ? 'bg-gdrcd-accent text-white' : 'hover:bg-gdrcd-accent-soft text-gdrcd-text-soft' ?>">
                            <?= $i + 1 ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <div class="flex flex-wrap gap-2">
            <?php if ($types === 'guilds'): ?>
                <a href="main.php?page=gestione_gilde" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['link']['guilds']) ?>
                </a>
            <?php endif; ?>
            <?php if ($types === 'items'): ?>
                <a href="main.php?page=gestione_mercato" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['administration']['types']['link']['items']) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
