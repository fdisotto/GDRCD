<?php
/**
 * Forum — elenco bacheche raggruppate per tipo.
 */

$result = gdrcd_query("SELECT id_araldo, nome, tipo, proprietari FROM araldo ORDER BY tipo, nome", 'result');

// Pre-fetch e raggruppa per tipo, con count nuovi topic per bacheca
$groups = [];
$total_boards = 0;
$total_new    = 0;

while ($row = gdrcd_query($result, 'fetch')) {
    if (!gdrcd_controllo_permessi_forum(gdrcd_filter('out', $row['tipo']), $row['proprietari'])) {
        continue;
    }

    $tipo  = (int)$row['tipo'];
    $letti = gdrcd_query("SELECT COUNT(id) AS n FROM araldo_letto WHERE araldo_id = " . (int)$row['id_araldo'] . " AND nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
    $tot   = gdrcd_query("SELECT COUNT(id_messaggio) AS n FROM messaggioaraldo WHERE id_araldo = " . (int)$row['id_araldo'] . " AND id_messaggio_padre = -1");

    $has_new = ((int)$tot['n'] > (int)$letti['n']);
    if ($has_new) $total_new++;
    $total_boards++;

    $groups[$tipo][] = [
        'id'      => (int)$row['id_araldo'],
        'nome'    => $row['nome'],
        'has_new' => $has_new,
        'topics'  => (int)$tot['n'],
    ];
}
gdrcd_query($result, 'free');
?>

<div class="space-y-6">

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-2">
            <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $PARAMETERS['names']['forum']['plur']) ?></h2>
            <p class="gdrcd-muted">
                <?= $total_boards ?> bacheche accessibili
                <?php if ($total_new > 0): ?>
                    <span class="text-gdrcd-subtle">·</span>
                    <span class="text-gdrcd-accent font-medium"><?= $total_new ?> con novità</span>
                <?php endif; ?>
            </p>
        </div>
        <form action="main.php?page=forum" method="post">
            <input type="hidden" name="op" value="readall"/>
            <button type="submit" class="gdrcd-btn-secondary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Segna tutto come letto
            </button>
        </form>
    </header>

    <?php if (empty($groups)): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>Nessuna bacheca disponibile.</div>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $tipo => $boards):
            $section_label = $MESSAGE['interface']['forums']['type'][$tipo] ?? 'Altro';
            ?>
            <section class="space-y-3">
                <header class="flex items-baseline gap-2">
                    <h3 class="gdrcd-h3">
                        <?= gdrcd_filter('out', $PARAMETERS['names']['forum']['plur'] . ' ' . strtolower($section_label)) ?>
                    </h3>
                    <span class="gdrcd-badge-neutral"><?= count($boards) ?></span>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($boards as $b): ?>
                        <a href="main.php?page=forum&op=visit&what=<?= $b['id'] ?>"
                           class="group gdrcd-card h-full block hover:border-gdrcd-accent-ring/60 hover:shadow-gdrcd-elev transition-all">
                            <div class="p-4 flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg <?= $b['has_new'] ? 'bg-gdrcd-accent text-white' : 'bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30' ?> shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                    </svg>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gdrcd-text group-hover:text-gdrcd-accent-hover transition-colors line-clamp-2">
                                        <?= gdrcd_filter('out', $b['nome']) ?>
                                    </div>
                                    <div class="text-xs text-gdrcd-muted mt-0.5">
                                        <?= $b['topics'] ?> topic
                                        <?php if ($b['has_new']): ?>
                                            <span class="gdrcd-badge-error ml-1 text-[10px]">
                                                <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['topic']['new_posts_forum']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-gdrcd-subtle group-hover:text-gdrcd-accent transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
