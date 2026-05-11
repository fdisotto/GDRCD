<?php
/**
 * Servizi — Lavoro indipendente: scelta/dimissione.
 */

$disoccupato = 0;
$lavoro = -1;
$jobsn = 0;
$ultimolavoro = date('Y-m-d');
$result = gdrcd_query(
    "SELECT clgpersonaggioruolo.id_ruolo, clgpersonaggioruolo.scadenza, ruolo.gilda
     FROM clgpersonaggioruolo
     LEFT JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
     WHERE clgpersonaggioruolo.personaggio = '" . gdrcd_filter('in', $_SESSION['login']) . "'
     ORDER BY ruolo.gilda",
    'result'
);
while ($jobs = gdrcd_query($result, 'fetch')) {
    $jobsn++;
    if ($jobs['gilda'] == -1) {
        $disoccupato = -1;
        $lavoro = $jobs['id_ruolo'];
        $ultimolavoro = $jobs['scadenza'];
    }
}
gdrcd_query($result, 'free');

$op = $_POST['op'] ?? null;
$alerts = [];

if ($op === 'pick') {
    if ($disoccupato == -1) {
        gdrcd_query("UPDATE clgpersonaggioruolo SET id_ruolo = " . gdrcd_filter('num', $_POST['id_record']) . ",
                     scadenza = DATE_ADD(NOW(), INTERVAL " . gdrcd_filter('num', $PARAMETERS['settings']['minimum_employment']) . " DAY)
                     WHERE personaggio = '" . gdrcd_filter('in', $_SESSION['login']) . "' AND id_ruolo = " . gdrcd_filter('num', $lavoro) . " LIMIT 1");
    } else {
        gdrcd_query("INSERT INTO clgpersonaggioruolo (id_ruolo, personaggio, scadenza)
                     VALUES (" . gdrcd_filter('num', $_POST['id_record']) . ",
                             '" . gdrcd_filter('in', $_SESSION['login']) . "',
                             DATE_ADD(NOW(), INTERVAL " . gdrcd_filter('num', $PARAMETERS['settings']['minimum_employment']) . " DAY))");
    }
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['job']['ok_job'])];
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                         NOW(), " . NUOVOLAVORO . ", '" . gdrcd_filter('in', $_POST['nome_lavoro'] ?? '') . "')");
} elseif ($op === 'resign') {
    gdrcd_query("DELETE FROM clgpersonaggioruolo WHERE personaggio = '" . gdrcd_filter('in', $_SESSION['login']) . "'
                 AND id_ruolo = " . gdrcd_filter('num', $_POST['id_record']) . " LIMIT 1");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['job']['ok_quit'])];
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                         NOW(), " . DIMISSIONE . ", '" . gdrcd_filter('in', $_POST['nome_lavoro'] ?? '') . "')");
}

$today = date('Y-m-d');
$theme = $PARAMETERS['themes']['current_theme'];
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.93 23.93 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['job']['page_name']) ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($op === null):
        $result = gdrcd_query("SELECT nome_ruolo, immagine, stipendio, id_ruolo FROM ruolo WHERE gilda = -1 ORDER BY nome_ruolo", 'result');
    ?>
        <article class="gdrcd-card">
            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th colspan="2"><?= gdrcd_filter('out', $MESSAGE['interface']['job']['job']) ?></th>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['job']['pay']) ?></th>
                            <th class="text-right"><?= gdrcd_filter('out', $MESSAGE['interface']['job']['controls']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                        <tr>
                            <td class="w-12">
                                <?php if (!empty($row['immagine'])): ?>
                                    <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/guilds/<?= htmlspecialchars($row['immagine']) ?>"
                                         alt="" class="w-10 h-10 object-contain">
                                <?php endif; ?>
                            </td>
                            <td class="font-display"><?= gdrcd_filter('out', $row['nome_ruolo']) ?></td>
                            <td class="tabular-nums"><?= (int)$row['stipendio'] ?> <?= gdrcd_filter('out', $PARAMETERS['names']['currency']['plur']) ?></td>
                            <td class="text-right">
                                <?php if ($ultimolavoro <= $today): ?>
                                    <?php if ($lavoro == $row['id_ruolo']): ?>
                                        <form method="post" action="main.php?page=servizi_lavoro" class="inline">
                                            <input type="hidden" name="op" value="resign">
                                            <input type="hidden" name="nome_lavoro" value="<?= gdrcd_filter('out', $row['nome_ruolo']) ?>">
                                            <input type="hidden" name="id_record" value="<?= (int)$row['id_ruolo'] ?>">
                                            <button type="submit" class="gdrcd-btn-ghost text-xs">
                                                <?= gdrcd_filter('out', $MESSAGE['interface']['job']['submit']['quit']) ?>
                                            </button>
                                        </form>
                                    <?php elseif ($jobsn < $PARAMETERS['settings']['guilds_limit']): ?>
                                        <form method="post" action="main.php?page=servizi_lavoro" class="inline">
                                            <input type="hidden" name="op" value="pick">
                                            <input type="hidden" name="nome_lavoro" value="<?= gdrcd_filter('out', $row['nome_ruolo']) ?>">
                                            <input type="hidden" name="id_record" value="<?= (int)$row['id_ruolo'] ?>">
                                            <button type="submit" class="gdrcd-btn-primary text-xs">
                                                <?= gdrcd_filter('out', $MESSAGE['interface']['job']['submit']['pick']) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($lavoro == $row['id_ruolo']):
                                    $ex = explode('-', $ultimolavoro);
                                    echo '<span class="text-xs text-gdrcd-text-soft">' . gdrcd_filter('out', $MESSAGE['interface']['job']['extent']) . ' ' . $ex[2] . '-' . $ex[1] . '-' . $ex[0] . '</span>';
                                endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; gdrcd_query($result, 'free'); ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gdrcd-border text-xs text-gdrcd-text-soft">
                <?= gdrcd_filter('out', $MESSAGE['interface']['job']['disclaimer']) ?> <?= (int)$PARAMETERS['settings']['minimum_employment'] ?>
            </div>
        </article>
    <?php else: ?>
        <div>
            <a href="main.php?page=servizi_lavoro" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['job']['back']) ?>
            </a>
        </div>
    <?php endif; ?>
</div>
