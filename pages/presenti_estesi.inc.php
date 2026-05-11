<?php
/**
 * Presenti estesi — elenco PG online raggruppato per mappa e luogo.
 */

$theme = gdrcd_filter('out', $PARAMETERS['themes']['current_theme']);
$show_state = ($PARAMETERS['mode']['user_online_state'] ?? 'OFF') === 'ON';
$mapwise = ($PARAMETERS['mode']['mapwise_links'] ?? 'OFF') !== 'OFF';

$result = gdrcd_query(
    "SELECT personaggio.nome, personaggio.cognome, personaggio.permessi, personaggio.sesso,
            personaggio.id_razza, razza.sing_m, razza.sing_f, razza.icon,
            personaggio.disponibile, personaggio.online_status, personaggio.is_invisible,
            personaggio.ultima_mappa, personaggio.ultimo_luogo, personaggio.posizione,
            personaggio.ora_entrata, personaggio.ora_uscita, personaggio.ultimo_refresh,
            mappa.stanza_apparente, mappa.nome AS luogo, mappa_click.nome AS mappa
     FROM personaggio
     LEFT JOIN mappa ON personaggio.ultimo_luogo = mappa.id
     LEFT JOIN mappa_click ON personaggio.ultima_mappa = mappa_click.id_click
     LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
     WHERE personaggio.ora_entrata > personaggio.ora_uscita
       AND DATE_ADD(personaggio.ultimo_refresh, INTERVAL 4 MINUTE) > NOW()
     ORDER BY personaggio.is_invisible, personaggio.ultima_mappa, personaggio.ultimo_luogo, personaggio.nome",
    'result'
);

$grouped = [];
$invisible_label = $MESSAGE['status_pg']['invisible'][1] ?? 'Invisibili';
while ($row = gdrcd_query($result, 'fetch')) {
    if ($row['is_invisible'] == 1) {
        $mappa = $invisible_label;
        $luogo = $invisible_label;
    } else {
        $mappa = $row['mappa'] ?: '—';
        $luogo = !empty($row['stanza_apparente']) ? $row['stanza_apparente'] : ($row['luogo'] ?: '—');
    }
    $grouped[$mappa][$luogo][] = $row;
}
gdrcd_query($result, 'free');

// Icone centralizzate: vedi includes/icons.inc.php

$total = array_sum(array_map(function ($m) {
    return array_sum(array_map('count', $m));
}, $grouped));
?>

<div class="space-y-6">
    <header class="space-y-1 flex flex-wrap items-center justify-between gap-3">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87a4 4 0 100-8 4 4 0 000 8zm0-8a4 4 0 100-8 4 4 0 000 8z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['logged_users']['page_title']) ?>
        </h2>
        <span class="gdrcd-badge-accent tabular-nums">
            <?= (int)$total ?> presenti
        </span>
    </header>

    <?php if ($show_state): ?>
        <div id="descriptionLoc"></div>
    <?php endif; ?>

    <?php if ($total === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
            <div>Nessun personaggio attualmente presente.</div>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($grouped as $mappa => $luoghi):
                $mappa_count = array_sum(array_map('count', $luoghi));
            ?>
                <article class="gdrcd-card">
                    <header class="gdrcd-card-header flex items-center justify-between gap-2">
                        <h3 class="gdrcd-h3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.553 2.776A1 1 0 0021 18.882V8.118a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <?= gdrcd_filter('out', $mappa) ?>
                        </h3>
                        <span class="text-xs text-gdrcd-text-soft tabular-nums"><?= $mappa_count ?> PG</span>
                    </header>
                    <div class="gdrcd-card-body space-y-3">
                        <?php foreach ($luoghi as $luogo => $pgs):
                            $first = $pgs[0];
                            $link_luogo = (!$mapwise && $first['is_invisible'] == 0)
                                ? 'main.php?dir=' . (int)$first['ultimo_luogo'] . '&map_id=' . (int)$first['ultima_mappa']
                                : null;
                        ?>
                            <section>
                                <div class="flex items-center gap-2 pb-1 mb-2 border-b border-gdrcd-border">
                                    <svg class="w-3.5 h-3.5 text-gdrcd-text-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <?php if ($link_luogo !== null): ?>
                                        <a href="<?= htmlspecialchars($link_luogo) ?>" class="text-sm text-gdrcd-accent hover:underline font-display">
                                            <?= gdrcd_filter('out', $luogo) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-sm text-gdrcd-text font-display"><?= gdrcd_filter('out', $luogo) ?></span>
                                    <?php endif; ?>
                                    <span class="ml-auto text-[10px] text-gdrcd-text-soft tabular-nums"><?= count($pgs) ?></span>
                                </div>

                                <ul class="space-y-1.5">
                                    <?php foreach ($pgs as $r):
                                        $activity = gdrcd_check_time($r['ora_entrata']);
                                        $just_in = $activity <= 2;
                                        $icon = $r['icon'] ?: 'standard_razza.png';
                                        $razza_lbl  = $r['sing_' . $r['sesso']] ?? '';
                                        $gender_lbl = gdrcd_filter('out', $MESSAGE['status_pg']['gender'][$r['sesso']] ?? '');
                                        $disp_lbl   = gdrcd_filter('out', $MESSAGE['status_pg']['availability'][$r['disponibile']] ?? '');
                                        $perm_lbl   = gdrcd_perm_label((int)$r['permessi']);

                                        $tooltip_attr = '';
                                        if ($show_state && !empty($r['online_status'])) {
                                            $s = trim(nl2br(gdrcd_filter('in', $r['online_status'])));
                                            $s = strtr($s, ["\n\r" => '', "\n" => '', "\r" => '', '"' => '&quot;']);
                                            $tooltip_attr = ' onmouseover="show_desc(event, \'' . $s . '\');" onmouseout="hide_desc();"';
                                        }
                                    ?>
                                        <li class="flex flex-wrap items-center gap-2 text-sm px-2 py-1.5 rounded hover:bg-gdrcd-accent-soft/40 transition"<?= $tooltip_attr ?>>
                                            <span class="inline-flex items-center gap-1.5 shrink-0">
                                                <?= gdrcd_icon_disp((int)$r['disponibile'], $disp_lbl) ?>
                                                <?php if ($just_in): ?>
                                                    <?= gdrcd_icon_enter(gdrcd_filter('out', $MESSAGE['status_pg']['enter'])) ?>
                                                <?php endif; ?>
                                                <?= gdrcd_icon_perm((int)$r['permessi'], $perm_lbl) ?>
                                                <?= gdrcd_icon_race($icon, $theme, $razza_lbl) ?>
                                                <?= gdrcd_icon_gender($r['sesso'], $gender_lbl) ?>
                                            </span>

                                            <a href="main.php?page=scheda&pg=<?= urlencode($r['nome']) ?>"
                                               class="font-display text-gdrcd-text hover:text-gdrcd-accent transition gender_<?= htmlspecialchars($r['sesso']) ?>">
                                                <?= gdrcd_filter('out', $r['nome']) ?>
                                                <?= !empty($r['cognome']) ? ' ' . gdrcd_filter('out', $r['cognome']) : '' ?>
                                            </a>

                                            <a href="main.php?page=messages_center&op=create&destinatario=<?= urlencode($r['nome']) ?>"
                                               class="ml-auto gdrcd-btn-ghost text-[11px] py-0.5 px-1.5"
                                               title="Invia messaggio privato">
                                                MP
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
