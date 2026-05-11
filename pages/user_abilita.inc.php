<?php
/**
 * Utente — elenco abilità del sistema.
 */
$result = gdrcd_query("SELECT nome, car, descrizione FROM abilita ORDER BY nome", 'result');
?>
<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['skills']['page_name']) ?>
        </h2>
    </header>

    <article class="gdrcd-card">
        <div class="overflow-x-auto">
            <table class="gdrcd-table">
                <thead>
                    <tr>
                        <th><?= gdrcd_filter('out', $MESSAGE['interface']['skills']['skill']) ?></th>
                        <th><?= gdrcd_filter('out', $MESSAGE['interface']['skills']['car']) ?></th>
                        <th><?= gdrcd_filter('out', $MESSAGE['interface']['skills']['desc']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                        <tr>
                            <td class="font-display"><?= gdrcd_filter('out', $row['nome']) ?></td>
                            <td>
                                <span class="gdrcd-badge-accent">
                                    <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car' . $row['car']]) ?>
                                </span>
                            </td>
                            <td class="text-sm text-gdrcd-text-soft">
                                <?= gdrcd_bbcoder(gdrcd_filter('out', $row['descrizione'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; gdrcd_query($result, 'free'); ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['skills']['sys_tit']) ?></h3>
        </header>
        <div class="gdrcd-card-body text-gdrcd-text leading-relaxed text-justify">
            <?= gdrcd_filter('out', $MESSAGE['interface']['skills']['sys']) ?>
        </div>
    </article>
</div>
