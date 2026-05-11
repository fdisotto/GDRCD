<?php
/**
 * Servizi — Gilde: elenco e dettaglio gilda con ruoli, membri e statuto.
 */

$theme = $PARAMETERS['themes']['current_theme'];
$title = gdrcd_filter('out', $PARAMETERS['names']['guild_name']['plur']);
$id_gilda = isset($_REQUEST['id_gilda']) ? (int)gdrcd_filter('num', $_REQUEST['id_gilda']) : 0;
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87a4 4 0 100-8 4 4 0 000 8zm0-8a4 4 0 100-8 4 4 0 000 8z"/>
                </svg>
            </span>
            <?= $title ?>
        </h2>
    </header>

    <?php if ($id_gilda === 0):
        $result = gdrcd_query(
            "SELECT gilda.nome, gilda.id_gilda, gilda.tipo, gilda.immagine, codtipogilda.descrizione
             FROM gilda JOIN codtipogilda ON gilda.tipo = codtipogilda.cod_tipo
             WHERE gilda.visibile = 1
             ORDER BY gilda.tipo, gilda.nome",
            'result'
        );

        $groups = [];
        while ($row = gdrcd_query($result, 'fetch')) {
            $numb = gdrcd_query("SELECT COUNT(*) AS n FROM clgpersonaggioruolo
                                 JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
                                 WHERE ruolo.gilda = " . (int)$row['id_gilda']);
            $row['membri'] = (int)$numb['n'];
            $groups[$row['descrizione']][] = $row;
        }
        gdrcd_query($result, 'free');
    ?>
        <?php foreach ($groups as $descrizione => $gilde): ?>
            <article class="gdrcd-card">
                <header class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $descrizione) ?></h3>
                </header>
                <div class="overflow-x-auto">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th><?= gdrcd_filter('out', $PARAMETERS['names']['guild_name']['sing']) ?></th>
                                <th class="tabular-nums text-right"><?= gdrcd_filter('out', $PARAMETERS['names']['guild_name']['members']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gilde as $g): ?>
                                <tr>
                                    <td class="w-12">
                                        <?php if (!empty($g['immagine'])): ?>
                                            <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/guilds/<?= htmlspecialchars($g['immagine']) ?>"
                                                 alt="" class="w-10 h-10 object-contain">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="main.php?page=servizi_gilde&id_gilda=<?= (int)$g['id_gilda'] ?>" class="text-gdrcd-accent hover:underline font-display">
                                            <?= gdrcd_filter('out', $g['nome']) ?>
                                        </a>
                                    </td>
                                    <td class="tabular-nums text-right"><?= (int)$g['membri'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endforeach; ?>

    <?php else:
        $ruoli_res = gdrcd_query(
            "SELECT nome_ruolo, immagine, stipendio, capo FROM ruolo
             WHERE gilda = " . $id_gilda . "
             ORDER BY capo DESC, stipendio DESC",
            'result'
        );
        $membri_res = gdrcd_query(
            "SELECT clgpersonaggioruolo.personaggio, personaggio.cognome, ruolo.immagine, ruolo.capo, ruolo.nome_ruolo
             FROM ruolo
             JOIN clgpersonaggioruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
             JOIN personaggio ON personaggio.nome = clgpersonaggioruolo.personaggio
             WHERE ruolo.gilda = " . $id_gilda . "
             ORDER BY ruolo.capo DESC, ruolo.stipendio DESC",
            'result'
        );
        $statuto = gdrcd_query("SELECT statuto FROM gilda WHERE id_gilda = " . $id_gilda);
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['roles_title']['plur']) ?></h3>
            </header>
            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th></th>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['roles_title']['sing']) ?></th>
                            <th class="tabular-nums text-right"><?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['pay']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = gdrcd_query($ruoli_res, 'fetch')): ?>
                            <tr>
                                <td class="w-8">
                                    <?php if ((int)$row['capo'] === 1): ?>
                                        <svg class="w-4 h-4 text-gdrcd-accent" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/></svg>
                                    <?php endif; ?>
                                </td>
                                <td class="w-12">
                                    <?php if (!empty($row['immagine'])): ?>
                                        <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/guilds/<?= htmlspecialchars($row['immagine']) ?>"
                                             alt="" class="w-8 h-8 object-contain">
                                    <?php endif; ?>
                                </td>
                                <td><?= gdrcd_filter('out', $row['nome_ruolo']) ?></td>
                                <td class="tabular-nums text-right"><?= (int)$row['stipendio'] ?> <?= gdrcd_filter('out', $PARAMETERS['names']['currency']['plur']) ?></td>
                            </tr>
                        <?php endwhile; gdrcd_query($ruoli_res, 'free'); ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['members']) ?></h3>
            </header>
            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th></th>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['member']) ?></th>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['roles_title']['sing']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = gdrcd_query($membri_res, 'fetch')): ?>
                            <tr>
                                <td class="w-8">
                                    <?php if ((int)$row['capo'] === 1): ?>
                                        <svg class="w-4 h-4 text-gdrcd-accent" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm0 2h14v2H5v-2z"/></svg>
                                    <?php endif; ?>
                                </td>
                                <td class="w-12">
                                    <?php if (!empty($row['immagine'])): ?>
                                        <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/guilds/<?= htmlspecialchars($row['immagine']) ?>"
                                             alt="" class="w-8 h-8 object-contain">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="main.php?page=scheda&pg=<?= urlencode($row['personaggio']) ?>" class="text-gdrcd-accent hover:underline">
                                        <?= gdrcd_filter('out', $row['personaggio'] . ' ' . $row['cognome']) ?>
                                    </a>
                                </td>
                                <td><?= gdrcd_filter('out', $row['nome_ruolo']) ?></td>
                            </tr>
                        <?php endwhile; gdrcd_query($membri_res, 'free'); ?>
                    </tbody>
                </table>
            </div>
        </article>

        <?php if (!empty($statuto['statuto'])): ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Statuto</h3>
            </header>
            <div class="gdrcd-card-body prose-sm text-gdrcd-text leading-relaxed">
                <?= gdrcd_bbcoder(gdrcd_filter('out', $statuto['statuto'])) ?>
            </div>
        </article>
        <?php endif; ?>

        <div>
            <a href="main.php?page=servizi_gilde" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['guilds']['back']) ?>
            </a>
        </div>
    <?php endif; ?>
</div>
