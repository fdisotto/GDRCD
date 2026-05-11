<?php
/**
 * Servizi/Anagrafe — ricerca personaggi.
 */

$tableSearch = '';

if (gdrcd_filter('get', $_POST['action'] ?? '') === 'searchPersonaggio') {
    if (!empty($_REQUEST['nome']) || !empty($_REQUEST['genere']) || !empty($_REQUEST['razza'])) {
        $whereFilters = [];
        if (gdrcd_filter('get', $_REQUEST['nome'] ?? '')) {
            $whereFilters[] = "personaggio.nome LIKE '%" . gdrcd_filter('get', $_REQUEST['nome']) . "%'";
        }
        if (gdrcd_filter('get', $_REQUEST['genere'] ?? '')) {
            $whereFilters[] = "personaggio.sesso = '" . gdrcd_filter('get', $_REQUEST['genere']) . "'";
        }
        if (gdrcd_filter('get', $_REQUEST['razza'] ?? '')) {
            $whereFilters[] = "personaggio.id_razza = '" . gdrcd_filter('get', $_REQUEST['razza']) . "'";
        }

        $limit_val = gdrcd_filter('num', $_REQUEST['limit'] ?? 0);
        $limit = ($limit_val > 0) ? " LIMIT $limit_val " : '';

        $querySearch = "SELECT personaggio.url_img_chat, personaggio.nome, personaggio.cognome, personaggio.sesso,
                               razza.nome_razza
                        FROM personaggio
                        LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
                        WHERE 1 " . (!empty($whereFilters) ? ' AND ' . implode(' AND ', $whereFilters) : '') . "
                        ORDER BY nome DESC $limit";
        $resultSearch = gdrcd_query($querySearch, 'result');

        if (gdrcd_query($resultSearch, 'num_rows') > 0) {
            ob_start();
            ?>
            <article class="gdrcd-card">
                <header class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Risultati ricerca</h3>
                </header>
                <div class="overflow-x-auto">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th><?= gdrcd_filter('out', $MESSAGE['interface']['pg_list']['search']['img']) ?></th>
                                <th><?= gdrcd_filter('out', $MESSAGE['interface']['pg_list']['search']['personaggio']) ?></th>
                                <th><?= gdrcd_filter('out', $MESSAGE['interface']['pg_list']['search']['sesso']) ?></th>
                                <th><?= gdrcd_filter('out', $MESSAGE['interface']['pg_list']['search']['razza']) ?></th>
                                <th class="text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($rowSearch = gdrcd_query($resultSearch, 'fetch')): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($rowSearch['url_img_chat'])): ?>
                                        <img src="<?= gdrcd_filter('out', $rowSearch['url_img_chat']) ?>" alt="" class="w-10 h-10 rounded-full object-cover border border-gdrcd-border">
                                    <?php else: ?>
                                        <span class="inline-block w-10 h-10 rounded-full bg-gdrcd-panel-alt"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="main.php?page=scheda&pg=<?= urlencode($rowSearch['nome']) ?>" class="text-gdrcd-accent hover:underline">
                                        <?= gdrcd_filter('out', $rowSearch['nome'] . ' ' . $rowSearch['cognome']) ?>
                                    </a>
                                </td>
                                <td class="text-sm"><?= gdrcd_filter('out', $MESSAGE['register']['fields']['gender_' . $rowSearch['sesso']]) ?></td>
                                <td class="text-sm"><?= gdrcd_filter('out', $rowSearch['nome_razza']) ?></td>
                                <td class="text-right">
                                    <form action="main.php?page=messages_center&op=create" method="post" class="inline">
                                        <?= gdrcd_csrf_field() ?>
                                        <input type="hidden" name="destinatario" value="<?= htmlspecialchars($rowSearch['nome']) ?>">
                                        <button type="submit" class="gdrcd-btn-ghost" title="<?= gdrcd_filter('out', $MESSAGE['interface']['messages']['reply']) ?>">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </article>
            <?php
            $tableSearch = ob_get_clean();
        } else {
            $tableSearch = '<div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessun personaggio corrisponde ai criteri di ricerca.</div></div>';
        }
    } else {
        $tableSearch = '<div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div>Selezionare almeno un criterio di ricerca.</div></div>';
    }
}

$result = gdrcd_query("SELECT id_razza, nome_razza FROM razza ORDER BY nome_razza", 'result');
$razze = [];
while ($razza = gdrcd_query($result, 'fetch')) {
    $razze[] = $razza;
}
gdrcd_query($result, 'free');

$generi = ['m', 'f'];
?>

<article class="gdrcd-card">
    <header class="gdrcd-card-header">
        <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['pg_list']['search']['title']) ?></h3>
    </header>
    <div class="gdrcd-card-body">
        <form method="POST" action="main.php?page=servizi_anagrafe" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <?= gdrcd_csrf_field() ?>
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft"><?= $MESSAGE['interface']['pg_list']['search']['personaggio'] ?></span>
                <input type="text" name="nome" value="<?= gdrcd_filter('out', $_REQUEST['nome'] ?? '') ?>" class="gdrcd-input mt-1 w-full">
            </label>
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft"><?= $MESSAGE['interface']['pg_list']['search']['sesso'] ?></span>
                <select name="genere" class="gdrcd-select mt-1 w-full">
                    <option value=""></option>
                    <?php foreach ($generi as $g): ?>
                        <option value="<?= $g ?>"<?= (gdrcd_filter('get', $_REQUEST['genere'] ?? '') == $g) ? ' selected' : '' ?>>
                            <?= gdrcd_filter('out', $MESSAGE['register']['fields']['gender_' . $g]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft"><?= $MESSAGE['interface']['pg_list']['search']['razza'] ?></span>
                <select name="razza" class="gdrcd-select mt-1 w-full">
                    <option value=""></option>
                    <?php foreach ($razze as $r): ?>
                        <option value="<?= $r['id_razza'] ?>"<?= (gdrcd_filter('get', $_REQUEST['razza'] ?? '') == $r['id_razza']) ? ' selected' : '' ?>>
                            <?= gdrcd_filter('out', $r['nome_razza']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="text-sm text-gdrcd-text-soft"><?= $MESSAGE['interface']['pg_list']['search']['limit'] ?></span>
                <input type="number" name="limit" min="0" value="<?= isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 0 ?>" class="gdrcd-input mt-1 w-full">
            </label>
            <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                <input type="hidden" name="action" value="searchPersonaggio">
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['pg_list']['search']['submit']) ?>
                </button>
            </div>
        </form>
    </div>
</article>

<?= $tableSearch ?>
