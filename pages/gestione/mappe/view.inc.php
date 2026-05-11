<?php
/**
 * Vista lista mappe cliccabili.
 */

if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maps']['access_level'])) {
    return;
}

$offset    = (int)($_REQUEST['offset'] ?? 0);
$per_page  = (int)$PARAMETERS['settings']['records_per_page'];
$pagebegin = $offset * $per_page;

$lbl_m = $MESSAGE['interface']['administration']['maps'];

$count_row     = gdrcd_query("SELECT COUNT(*) AS c FROM mappa_click");
$totaleresults = (int)$count_row['c'];

$main_count_row = gdrcd_query("SELECT COUNT(*) AS c FROM mappa_click WHERE principale = 1");
$mainMaps       = (int)$main_count_row['c'];

$result = gdrcd_query(
    "SELECT id_click, nome, mobile, posizione, principale
     FROM mappa_click ORDER BY nome LIMIT " . $pagebegin . ", " . $per_page,
    'result'
);
$numresults = (int)gdrcd_query($result, 'num_rows');
?>

<?php if ($numresults > 0 && $mainMaps === 0): ?>
    <div class="gdrcd-alert-warning">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $lbl_m['no_main']) ?></div>
    </div>
<?php endif; ?>

<div class="flex flex-wrap items-baseline justify-between gap-2">
    <span class="gdrcd-muted text-xs"><?= $totaleresults ?> mappe</span>
    <a href="main.php?page=gestione/mappe&op=create" class="gdrcd-btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <?= gdrcd_filter('out', $lbl_m['link']['create']) ?>
    </a>
</div>

<?php if ($numresults === 0): ?>
    <div class="gdrcd-alert-info">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>Nessuna mappa presente.</div>
    </div>
<?php else: ?>
    <div class="gdrcd-table-wrap">
        <table class="gdrcd-table">
            <thead>
                <tr>
                    <th><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['name_col']) ?></th>
                    <th class="whitespace-nowrap text-right w-24"><?= gdrcd_filter('out', $lbl_m['position']) ?></th>
                    <th class="whitespace-nowrap text-center w-24"><?= gdrcd_filter('out', $lbl_m['is_mobile']) ?></th>
                    <th class="whitespace-nowrap text-center w-24"><?= gdrcd_filter('out', $lbl_m['is_main']) ?></th>
                    <th class="text-right w-[120px]"><span class="sr-only">Azioni</span></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                    <tr>
                        <td class="font-medium text-gdrcd-text"><?= gdrcd_filter('out', $row['nome']) ?></td>
                        <td class="text-right text-gdrcd-muted tabular-nums"><?= (int)$row['posizione'] ?></td>
                        <td class="text-center">
                            <?php if ((int)$row['mobile'] === 1): ?>
                                <span class="gdrcd-badge-accent"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['yes']) ?></span>
                            <?php else: ?>
                                <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['no']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$row['principale'] === 1): ?>
                                <span class="gdrcd-badge-success"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['yes']) ?></span>
                            <?php else: ?>
                                <span class="gdrcd-badge-neutral"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['no']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <form action="main.php?page=gestione/mappe&op=edit" method="post" class="inline-block">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="id_click" value="<?= (int)$row['id_click'] ?>"/>
                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                        title="<?= gdrcd_filter('out', $MESSAGE['interface']['administration']['ops']['edit']) ?>">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            </form>
                            <form action="main.php?page=gestione/mappe" method="post" class="inline-block"
                                  onsubmit="return confirm('Eliminare questa mappa?');">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="id_click" value="<?= (int)$row['id_click'] ?>"/>
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
            <?php $pages = (int)ceil($totaleresults / $per_page) - 1;
            for ($i = 0; $i <= $pages; $i++):
                if ($i === $offset): ?>
                    <span class="is-current" aria-current="page"><?= $i + 1 ?></span>
                <?php else:
                    $url = 'main.php?' . http_build_query(['page' => 'gestione/mappe', 'offset' => $i]); ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                <?php endif;
            endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
