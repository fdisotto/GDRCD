<?php
/**
 * Diario PG — lista pagine.
 */

$pg          = $_REQUEST['pg'];
$is_self     = ($pg === $_SESSION['login']);
$is_diario_p = ((int)$_SESSION['permessi'] >= PERMESSI_DIARIO);

$where_vis = ($is_self || $is_diario_p) ? '' : " AND visibile = 'si'";
$result = gdrcd_query(
    "SELECT id, data, titolo, testo, visibile FROM diario
     WHERE personaggio = '" . gdrcd_filter('in', $pg) . "'" . $where_vis . "
     ORDER BY data DESC",
    'result'
);
$num = (int)gdrcd_query($result, 'num_rows');

$lbl = $MESSAGE['interface']['sheet']['diary'];
?>

<div class="flex flex-wrap items-baseline justify-between gap-2">
    <span class="gdrcd-muted text-xs"><?= $num ?> pagine</span>
    <?php if ($is_self): ?>
        <form action="main.php?page=scheda_diario&pg=<?= urlencode($pg) ?>" method="post">
            <?= gdrcd_csrf_field() ?>
            <input type="hidden" name="op" value="new"/>
            <button type="submit" class="gdrcd-btn-primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <?= gdrcd_filter('out', $lbl['new']) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($num === 0): ?>
    <div class="gdrcd-alert-info">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>Nessuna pagina nel diario.</div>
    </div>
<?php else: ?>
    <div class="gdrcd-table-wrap">
        <table class="gdrcd-table">
            <thead>
                <tr>
                    <th class="whitespace-nowrap w-32"><?= gdrcd_filter('out', $lbl['date']) ?></th>
                    <th><?= gdrcd_filter('out', $lbl['title']) ?></th>
                    <?php if ($is_self || $is_diario_p): ?>
                        <th class="whitespace-nowrap text-center w-24"><?= gdrcd_filter('out', $lbl['visible']) ?></th>
                        <th class="text-right w-[110px]"><span class="sr-only">Azioni</span></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = gdrcd_query($result, 'fetch')): ?>
                    <tr>
                        <td class="text-gdrcd-muted whitespace-nowrap">
                            <?= gdrcd_format_date($r['data']) ?>
                        </td>
                        <td>
                            <form action="main.php?page=scheda_diario&pg=<?= urlencode($pg) ?>" method="post" class="inline">
                                <?= gdrcd_csrf_field() ?>
                                <input type="hidden" name="op" value="view"/>
                                <button type="submit" name="id" value="<?= (int)$r['id'] ?>" class="gdrcd-link font-medium text-left">
                                    <?= gdrcd_filter('out', $r['titolo']) ?>
                                </button>
                            </form>
                        </td>
                        <?php if ($is_self || $is_diario_p): ?>
                            <td class="text-center">
                                <?php if (strtolower($r['visibile']) === 'si'): ?>
                                    <span class="gdrcd-badge-success text-[10px]">Pubblica</span>
                                <?php else: ?>
                                    <span class="gdrcd-badge-neutral text-[10px]">Privata</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <form action="main.php?page=scheda_diario&pg=<?= urlencode($pg) ?>" method="post" class="inline">
                                    <?= gdrcd_csrf_field() ?>
                                    <input type="hidden" name="op" value="edit"/>
                                    <button type="submit" name="id" value="<?= (int)$r['id'] ?>"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors"
                                            title="Modifica">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                </form>
                                <form action="main.php?page=scheda_diario&pg=<?= urlencode($pg) ?>" method="post" class="inline"
                                      onsubmit="return confirm('Vuoi eliminare la pagina?');">
                                    <?= gdrcd_csrf_field() ?>
                                    <input type="hidden" name="op" value="delete"/>
                                    <button type="submit" name="id" value="<?= (int)$r['id'] ?>"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-error-soft hover:text-gdrcd-error transition-colors"
                                            title="Elimina">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile;
                gdrcd_query($result, 'free');
                ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
