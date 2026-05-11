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

$perm_labels = [
    USER             => '',
    GUILDMODERATOR   => $PARAMETERS['names']['guild_name']['lead'] ?? '',
    GAMEMASTER       => $PARAMETERS['names']['master']['sing'] ?? '',
    MODERATOR        => $PARAMETERS['names']['moderators']['sing'] ?? '',
    SUPERUSER        => $PARAMETERS['names']['administrator']['sing'] ?? '',
];

// Helper icone — Heroicons mini (20x20 solid), consistente w-4 h-4.
// Ogni SVG include <title> per tooltip nativo accessibile.
$svg_title = function ($t) { return '<title>' . htmlspecialchars($t) . '</title>'; };

$icon_perm = function ($level, $label) use ($svg_title) {
    $t = $svg_title($label);
    switch ($level) {
        case SUPERUSER:
            return '<svg class="w-4 h-4 text-purple-600" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $t . '<path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036a2.63 2.63 0 001.91 1.91l1.036.258a.75.75 0 010 1.456l-1.036.258a2.63 2.63 0 00-1.91 1.91l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a2.625 2.625 0 00-1.91-1.91l-1.036-.258a.75.75 0 010-1.456l1.036-.258a2.625 2.625 0 001.91-1.91l.258-1.036A.75.75 0 0118 1.5zM16.5 15a.75.75 0 01.712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 010 1.422l-1.183.395a1.5 1.5 0 00-.948.948l-.395 1.183a.75.75 0 01-1.422 0l-.395-1.183a1.5 1.5 0 00-.948-.948l-1.183-.395a.75.75 0 010-1.422l1.183-.395a1.5 1.5 0 00.948-.948l.395-1.183A.75.75 0 0116.5 15z" clip-rule="evenodd"/></svg>';
        case MODERATOR:
            return '<svg class="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $t . '<path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zm3.78 7.625a.75.75 0 00-1.06-1.06L9.22 12.07 7.28 10.13a.75.75 0 00-1.06 1.06l2.47 2.47a.75.75 0 001.06 0l4.03-4.03z" clip-rule="evenodd"/></svg>';
        case GAMEMASTER:
            return '<svg class="w-4 h-4 text-gdrcd-accent" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $t . '<path d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L4 7.723V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.733.99A1.002 1.002 0 0118 6v2a1 1 0 11-2 0v-.277l-.254.145a1 1 0 11-.992-1.736l.23-.132-.23-.132a1 1 0 01-.372-1.364zm-7 4a1 1 0 011.364-.372L10 8.848l1.254-.716a1 1 0 11.992 1.736L11 10.58V12a1 1 0 11-2 0v-1.42l-1.246-.712a1 1 0 01-.372-1.364zM3 11a1 1 0 011 1v1.42l1.246.712a1 1 0 11-.992 1.736l-1.75-1A1 1 0 012 14v-2a1 1 0 011-1zm14 0a1 1 0 011 1v2a1 1 0 01-.504.868l-1.75 1a1 1 0 11-.992-1.736L16 13.42V12a1 1 0 011-1zm-9.618 5.504a1 1 0 011.364-.372l.254.145V16a1 1 0 112 0v.277l.254-.145a1 1 0 11.992 1.736l-1.735.992a.995.995 0 01-1.022 0l-1.735-.992a1 1 0 01-.372-1.364z"/></svg>';
        case GUILDMODERATOR:
            return '<svg class="w-4 h-4 text-amber-500" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $t . '<path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd"/></svg>';
        default:
            return '';
    }
};

$icon_disp = function ($state, $label) {
    $colors = [
        0 => ['ring' => 'ring-green-500/30', 'bg' => 'bg-green-500'],
        1 => ['ring' => 'ring-yellow-500/30', 'bg' => 'bg-yellow-500'],
        2 => ['ring' => 'ring-red-500/30',    'bg' => 'bg-red-500'],
    ];
    $c = $colors[$state] ?? ['ring' => 'ring-gray-400/30', 'bg' => 'bg-gray-400'];
    return '<span class="relative inline-flex w-2.5 h-2.5" title="' . htmlspecialchars($label) . '" aria-label="' . htmlspecialchars($label) . '">'
         . '<span class="absolute inset-0 rounded-full ' . $c['bg'] . '"></span>'
         . '<span class="absolute -inset-1 rounded-full ' . $c['ring'] . ' ring-2"></span>'
         . '</span>';
};

$icon_gender = function ($sex, $label) use ($svg_title) {
    $t = $svg_title($label);
    if ($sex === 'm') {
        return '<svg class="w-4 h-4 text-sky-600" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $t . '<path fill-rule="evenodd" d="M12 2a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 11-2 0V4.414l-2.86 2.86A6 6 0 1110.726 6.14l2.86-2.86H12a1 1 0 01-1-1zM8 9a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>';
    }
    if ($sex === 'f') {
        return '<svg class="w-4 h-4 text-pink-600" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $t . '<path fill-rule="evenodd" d="M10 2a5 5 0 100 10 5 5 0 000-10zm-1 11.93A7 7 0 1110 0a7 7 0 011 13.93V16h2a1 1 0 110 2h-2v1a1 1 0 11-2 0v-1H7a1 1 0 110-2h2v-2.07z" clip-rule="evenodd"/></svg>';
    }
    return '';
};

$icon_enter_fn = function ($label) use ($svg_title) {
    return '<svg class="w-4 h-4 text-green-600" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="' . htmlspecialchars($label) . '">' . $svg_title($label)
         . '<path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/>'
         . '<path fill-rule="evenodd" d="M6 10a.75.75 0 01.75-.75h9.546l-1.048-.943a.75.75 0 111.004-1.114l2.5 2.25a.75.75 0 010 1.114l-2.5 2.25a.75.75 0 11-1.004-1.114l1.048-.943H6.75A.75.75 0 016 10z" clip-rule="evenodd"/></svg>';
};

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
                                        $razza_lbl = htmlspecialchars($r['sing_' . $r['sesso']] ?? '');
                                        $gender_lbl = gdrcd_filter('out', $MESSAGE['status_pg']['gender'][$r['sesso']] ?? '');
                                        $disp_lbl = gdrcd_filter('out', $MESSAGE['status_pg']['availability'][$r['disponibile']] ?? '');
                                        $perm_lbl = gdrcd_filter('out', $perm_labels[$r['permessi']] ?? '');

                                        $tooltip_attr = '';
                                        if ($show_state && !empty($r['online_status'])) {
                                            $s = trim(nl2br(gdrcd_filter('in', $r['online_status'])));
                                            $s = strtr($s, ["\n\r" => '', "\n" => '', "\r" => '', '"' => '&quot;']);
                                            $tooltip_attr = ' onmouseover="show_desc(event, \'' . $s . '\');" onmouseout="hide_desc();"';
                                        }
                                    ?>
                                        <li class="flex flex-wrap items-center gap-2 text-sm px-2 py-1.5 rounded hover:bg-gdrcd-accent-soft/40 transition"<?= $tooltip_attr ?>>
                                            <span class="inline-flex items-center gap-1.5 shrink-0">
                                                <?= $icon_disp((int)$r['disponibile'], $disp_lbl) ?>
                                                <?php if ($just_in): ?>
                                                    <?= $icon_enter_fn(gdrcd_filter('out', $MESSAGE['status_pg']['enter'])) ?>
                                                <?php endif; ?>
                                                <?php $perm_svg = $icon_perm((int)$r['permessi'], $perm_lbl); if ($perm_svg !== ''): ?>
                                                    <?= $perm_svg ?>
                                                <?php endif; ?>
                                                <?php if (!empty($icon) && $icon !== 'standard_razza.png'): ?>
                                                    <img src="themes/<?= $theme ?>/imgs/races/<?= htmlspecialchars($icon) ?>" alt="<?= $razza_lbl ?>" title="<?= $razza_lbl ?>" class="w-4 h-4 object-contain">
                                                <?php endif; ?>
                                                <?= $icon_gender($r['sesso'], $gender_lbl) ?>
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
