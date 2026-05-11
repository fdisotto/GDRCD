<?php
/**
 * Widget presenti — auto-refresh ogni 60s (iframe).
 * Visualizza: entrati di recente, usciti, presenti in luogo corrente.
 */
include('../ref_header.inc.php');

// Refresh stato.
if (isset($_REQUEST['disponibile'])) {
    gdrcd_query("UPDATE personaggio SET ultimo_refresh = NOW(),
                 disponibile = " . gdrcd_filter('num', $_REQUEST['disponibile']) . "
                 WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
} elseif (isset($_REQUEST['invisibile']) && $_SESSION['permessi'] >= GAMEMASTER) {
    gdrcd_query("UPDATE personaggio SET ultimo_refresh = NOW(),
                 is_invisible = " . gdrcd_filter('num', $_REQUEST['invisibile']) . "
                 WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
} else {
    gdrcd_query("UPDATE personaggio SET ultimo_refresh = NOW()
                 WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
}

$theme = gdrcd_filter('out', $PARAMETERS['themes']['current_theme']);
// Icone: vedi includes/icons.inc.php

/** Render riga PG. */
$render_pg = function ($r) use ($theme, $MESSAGE) {
    $is_me      = ($r['nome'] === $_SESSION['login']);
    $perm_lbl   = gdrcd_perm_label((int)$r['permessi']);
    $disp_lbl   = gdrcd_filter('out', $MESSAGE['status_pg']['availability'][$r['disponibile']] ?? '');
    $gender_lbl = gdrcd_filter('out', $MESSAGE['status_pg']['gender'][$r['sesso']] ?? '');
    $razza_lbl  = $r['sing_' . $r['sesso']] ?? '';
    $icon       = $r['icon'] ?: 'standard_razza.png';
    $change_disp = ($r['disponibile'] + 1) % 3;

    if ($is_me && $r['permessi'] != $_SESSION['permessi']) {
        $_SESSION['permessi'] = $r['permessi'];
    }
    ?>
    <div class="flex items-center gap-1.5 px-2 py-1 text-xs hover:bg-gdrcd-accent-soft/40 rounded transition">
        <?= gdrcd_icon_perm((int)$r['permessi'], $perm_lbl) ?>
        <?php if ($is_me): ?>
            <a href="presenti.inc.php?disponibile=<?= $change_disp ?>" class="shrink-0">
                <?= gdrcd_icon_disp((int)$r['disponibile'], $disp_lbl) ?>
            </a>
        <?php else: ?>
            <?= gdrcd_icon_disp((int)$r['disponibile'], $disp_lbl) ?>
        <?php endif; ?>
        <?= gdrcd_icon_race($icon, $theme, $razza_lbl, '../themes') ?>
        <?= gdrcd_icon_gender($r['sesso'], $gender_lbl) ?>
        <a href="../main.php?page=scheda&pg=<?= urlencode($r['nome']) ?>"
           class="flex-1 min-w-0 truncate text-gdrcd-text hover:text-gdrcd-accent transition gender_<?= htmlspecialchars($r['sesso']) ?>"
           target="_top">
            <?= gdrcd_filter('out', $r['nome']) ?>
        </a>
        <?php if ($is_me && $_SESSION['permessi'] >= GAMEMASTER):
            $next = ((int)$r['is_invisible'] === 1) ? 0 : 1;
            $vis_lbl = gdrcd_filter('out', $MESSAGE['status_pg']['invisible'][$r['is_invisible']] ?? '');
        ?>
            <a href="presenti.inc.php?invisibile=<?= $next ?>" class="shrink-0">
                <?= gdrcd_icon_visible((int)$r['is_invisible'] === 1, $vis_lbl) ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
};

/** Sezione titolo. */
$section_title = function ($txt) { ?>
    <div class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display px-2 pt-2 pb-1 border-b border-gdrcd-border">
        <?= htmlspecialchars($txt) ?>
    </div>
<?php };

// Conteggio totale presenti.
$tot_record = gdrcd_query("SELECT COUNT(*) AS numero FROM personaggio
                           WHERE personaggio.ora_entrata > personaggio.ora_uscita
                           AND DATE_ADD(personaggio.ultimo_refresh, INTERVAL 4 MINUTE) > NOW()
                           AND personaggio.is_invisible = 0");
$tot_presenti = (int)($tot_record['numero'] ?? 0);
?>

<div class="space-y-2 text-sm font-sans text-gdrcd-text">

    <!-- Entrati -->
    <?php
    $entrati = gdrcd_query("SELECT personaggio.nome, personaggio.cognome, personaggio.permessi, personaggio.sesso,
                            razza.sing_m, razza.sing_f, razza.icon, personaggio.disponibile, personaggio.is_invisible
                            FROM personaggio LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
                            WHERE DATE_ADD(personaggio.ora_entrata, INTERVAL 2 MINUTE) > NOW()
                            ORDER BY personaggio.ora_entrata, personaggio.nome", 'result');
    if (gdrcd_query($entrati, 'num_rows') > 0):
        $section_title($MESSAGE['interface']['logged_users']['logged_in']);
        while ($r = gdrcd_query($entrati, 'fetch')) $render_pg($r);
        gdrcd_query($entrati, 'free');
    endif;
    ?>

    <!-- Usciti -->
    <?php
    $usciti = gdrcd_query("SELECT personaggio.nome, personaggio.cognome, personaggio.permessi, personaggio.sesso,
                           razza.sing_m, razza.sing_f, razza.icon, personaggio.disponibile, personaggio.is_invisible
                           FROM personaggio LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
                           WHERE (personaggio.ora_uscita > personaggio.ora_entrata AND DATE_ADD(personaggio.ora_uscita, INTERVAL 1 MINUTE) > NOW())
                              OR (personaggio.ora_uscita < personaggio.ora_entrata
                                  AND DATE_ADD(personaggio.ultimo_refresh, INTERVAL 4 MINUTE) > NOW()
                                  AND DATE_ADD(personaggio.ultimo_refresh, INTERVAL 3 MINUTE) < NOW())
                           ORDER BY personaggio.ultimo_refresh, personaggio.nome", 'result');
    if (gdrcd_query($usciti, 'num_rows') > 0):
        $section_title($MESSAGE['interface']['logged_users']['logged_out']);
        while ($r = gdrcd_query($usciti, 'fetch')) $render_pg($r);
        gdrcd_query($usciti, 'free');
    endif;
    ?>

    <!-- In luogo -->
    <?php
    $here = gdrcd_query("SELECT personaggio.nome, personaggio.cognome, personaggio.permessi, personaggio.sesso,
                         razza.sing_m, razza.sing_f, razza.icon, personaggio.disponibile, personaggio.is_invisible,
                         mappa.stanza_apparente, mappa.nome AS luogo
                         FROM personaggio
                         LEFT JOIN mappa ON personaggio.ultimo_luogo = mappa.id
                         LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
                         WHERE personaggio.ora_entrata > personaggio.ora_uscita
                         AND DATE_ADD(personaggio.ultimo_refresh, INTERVAL 4 MINUTE) > NOW()
                         AND personaggio.ultimo_luogo = " . (int)$_SESSION['luogo'] . "
                         AND personaggio.ultima_mappa = " . (int)$_SESSION['mappa'] . "
                         ORDER BY personaggio.is_invisible, personaggio.ultimo_luogo, personaggio.nome", 'result');
    $ultimo = '';
    while ($r = gdrcd_query($here, 'fetch')):
        if ($r['is_invisible'] == 1 && $r['nome'] !== $_SESSION['login']) continue;
        $luogo = !empty($r['stanza_apparente']) ? $r['stanza_apparente'] : ($r['luogo'] ?: '');
        if (empty($luogo)) {
            $luogo = ($r['mappa'] ?? 0) >= 0 ? $PARAMETERS['names']['maps_location'] : $PARAMETERS['names']['base_location'];
        }
        if ($luogo !== $ultimo) {
            $ultimo = $luogo;
            $section_title($luogo);
        }
        $render_pg($r);
    endwhile;
    gdrcd_query($here, 'free');
    ?>

    <!-- Conteggio + link -->
    <div class="mt-3 pt-2 border-t border-gdrcd-border">
        <a href="../main.php?page=presenti_estesi" target="_top"
           class="block px-2 py-1.5 rounded text-center text-sm font-display text-gdrcd-accent hover:bg-gdrcd-accent-soft transition">
            <strong class="tabular-nums"><?= $tot_presenti ?></strong>
            <?= gdrcd_filter('out', $tot_presenti === 1
                ? $PARAMETERS['names']['users_name']['sing'] . ' ' . $MESSAGE['interface']['logged_users']['sing']
                : $PARAMETERS['names']['users_name']['plur'] . ' ' . $MESSAGE['interface']['logged_users']['plur']) ?>
        </a>
    </div>
</div>

<?php include('../footer.inc.php'); ?>
