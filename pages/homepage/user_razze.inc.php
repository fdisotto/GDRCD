<?php
$theme = $PARAMETERS['themes']['current_theme'];
$query = "SELECT nome_razza, sing_m, sing_f, descrizione, url_site,
                 bonus_car0, bonus_car1, bonus_car2, bonus_car3, bonus_car4, bonus_car5,
                 immagine, icon
          FROM razza
          WHERE visibile = 1
          ORDER BY nome_razza";
$result = gdrcd_query($query, 'result');
?>

<header class="gdrcd-topbar">
    <div class="gdrcd-topbar-inner">
        <div>
            <h1 class="gdrcd-brand">
                <a href="index.php"><?= htmlspecialchars($PARAMETERS['info']['site_name']) ?></a>
            </h1>
            <div class="gdrcd-brand-subtitle">
                <?= gdrcd_filter('out', $MESSAGE['interface']['user']['races']['page_name'] . ' ' . strtolower($PARAMETERS['names']['race']['plur'])) ?>
            </div>
        </div>
        <a href="index.php" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $PARAMETERS['info']['homepage_name']) ?>
        </a>
    </div>
</header>

<main class="flex-1 w-full max-w-5xl mx-auto px-4 md:px-6 py-8">
    <div class="space-y-6">

        <header class="space-y-2">
            <h2 class="gdrcd-h1">
                <?= gdrcd_filter('out', $MESSAGE['interface']['user']['races']['page_name'] . ' ' . strtolower($PARAMETERS['names']['race']['plur'])) ?>
            </h2>
            <p class="gdrcd-muted">Caratteristiche, descrizione e bonus di ogni razza disponibile.</p>
        </header>

        <?php if (gdrcd_query($result, 'num_rows') === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessuna razza disponibile.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <?php while ($row = gdrcd_query($result, 'fetch')):
                    $img_dir = "themes/" . htmlspecialchars($theme) . "/imgs/races/";
                    $icon_src = !empty($row['icon']) ? $img_dir . htmlspecialchars($row['icon']) : null;
                    $img_src  = !empty($row['immagine']) ? $img_dir . htmlspecialchars($row['immagine']) : null;
                    $bonuses = [];
                    for ($i = 0; $i < 6; $i++) {
                        $val = (int)$row["bonus_car$i"];
                        $bonuses[] = [
                            'name' => $PARAMETERS['names']['stats']['car'.$i],
                            'val'  => $val,
                        ];
                    }
                    ?>
                    <article class="gdrcd-card flex flex-col">
                        <div class="gdrcd-card-header flex items-center gap-3">
                            <?php if ($icon_src): ?>
                                <img src="<?= $icon_src ?>" alt=""
                                     class="w-10 h-10 rounded-full object-cover border border-gdrcd-border bg-gdrcd-panel-alt"/>
                            <?php else: ?>
                                <span class="gdrcd-icon-circle w-10 h-10 text-sm">
                                    <?= strtoupper(substr($row['nome_razza'], 0, 1)) ?>
                                </span>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <h3 class="gdrcd-h3 truncate">
                                    <?php if (!empty($row['url_site'])): ?>
                                        <a class="gdrcd-link"
                                           href="http://<?= htmlspecialchars($row['url_site']) ?>"
                                           target="_blank" rel="noopener">
                                            <?= gdrcd_filter('out', $row['nome_razza']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= gdrcd_filter('out', $row['nome_razza']) ?>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-xs text-gdrcd-muted">
                                    <?= gdrcd_filter('out', $row['sing_m']) ?> · <?= gdrcd_filter('out', $row['sing_f']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="gdrcd-card-body space-y-4 flex-1">
                            <?php if ($img_src): ?>
                                <div class="flex justify-center">
                                    <img src="<?= $img_src ?>" alt=""
                                         class="max-h-48 rounded-gdrcd border border-gdrcd-border bg-gdrcd-panel-alt object-contain"/>
                                </div>
                            <?php endif; ?>

                            <div class="gdrcd-prose">
                                <?= gdrcd_bbcoder(gdrcd_filter('out', $row['descrizione'])) ?>
                            </div>

                            <div>
                                <div class="gdrcd-eyebrow mb-2">
                                    <?= gdrcd_filter('out', $MESSAGE['interface']['user']['races']['bonus']) ?>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($bonuses as $b):
                                        if ($b['val'] > 0)      $cls = 'gdrcd-badge-success';
                                        elseif ($b['val'] < 0)  $cls = 'gdrcd-badge-error';
                                        else                    $cls = 'gdrcd-badge-neutral';
                                        $sign = $b['val'] > 0 ? '+' : '';
                                        ?>
                                        <span class="<?= $cls ?>">
                                            <?= gdrcd_filter('out', $b['name']) ?>
                                            <strong class="ml-0.5"><?= $sign . $b['val'] ?></strong>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endwhile;
                gdrcd_query($result, 'free');
                ?>
            </div>
        <?php endif; ?>

    </div>
</main>
