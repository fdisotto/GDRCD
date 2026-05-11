<?php
/**
 * Utente — statistiche del sito (tot PG, nuovi, esiliati, master, ecc).
 */

$op = gdrcd_filter_get($_REQUEST['op'] ?? '');
$show_links = (gdrcd_filter_get($_REQUEST['links'] ?? '') === 'yes');

$st_minreg   = gdrcd_query("SELECT MIN(data_iscrizione) AS stat FROM personaggio");
$st_tot      = gdrcd_query("SELECT COUNT(*) AS stat FROM personaggio");
$st_exiled   = gdrcd_query("SELECT COUNT(*) AS stat FROM personaggio WHERE esilio > NOW()");
$st_master   = gdrcd_query("SELECT COUNT(*) AS stat FROM personaggio WHERE permessi = " . GAMEMASTER);
$st_mod      = gdrcd_query("SELECT COUNT(*) AS stat FROM personaggio WHERE permessi >= " . MODERATOR);
$st_topics   = gdrcd_query("SELECT COUNT(*) AS stat FROM messaggioaraldo WHERE data_messaggio > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$st_chat     = gdrcd_query("SELECT COUNT(*) AS stat FROM chat WHERE ora > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$st_newpg    = gdrcd_query("SELECT COUNT(*) AS stat FROM personaggio WHERE data_iscrizione > DATE_SUB(NOW(), INTERVAL 7 DAY)");
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['page_name']) ?>
        </h2>
    </header>

    <?php if ($op === ''):
        $stats = [
            ['icon' => '<path d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
             'label' => gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['creation_date']),
             'value' => gdrcd_format_date($st_minreg['stat'])],
            ['icon' => '<path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M13 12a4 4 0 11-8 0 4 4 0 018 0z"/>',
             'label' => gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['characters']),
             'value' => (int)$st_tot['stat']],
            ['icon' => '<path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 18.364M5.636 5.636l12.728 12.728"/>',
             'label' => gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['exiled']),
             'value' => (int)$st_exiled['stat'],
             'link'  => $show_links ? 'main.php?page=user_stats&op=esiliati' : null],
            ['icon' => '<path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>',
             'label' => gdrcd_filter('out', $PARAMETERS['names']['master']['plur']),
             'value' => (int)$st_master['stat']],
            ['icon' => '<path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
             'label' => gdrcd_filter('out', $PARAMETERS['names']['moderators']['plur']),
             'value' => (int)$st_mod['stat']],
            ['icon' => '<path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
             'label' => gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['topics']),
             'value' => (int)$st_topics['stat']],
            ['icon' => '<path d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-4 4z"/>',
             'label' => gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['last_chat']),
             'value' => (int)$st_chat['stat']],
            ['icon' => '<path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>',
             'label' => gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['last_characters']),
             'value' => (int)$st_newpg['stat'],
             'link'  => $show_links ? 'main.php?page=user_stats&op=nuovi' : null],
        ];
    ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <?php foreach ($stats as $s):
                $is_link = !empty($s['link']);
                $tag = $is_link ? 'a' : 'div';
                $href = $is_link ? ' href="' . htmlspecialchars($s['link']) . '"' : '';
                $cls = 'gdrcd-card p-4' . ($is_link ? ' hover:border-gdrcd-accent transition cursor-pointer' : '');
            ?>
                <<?= $tag ?><?= $href ?> class="<?= $cls ?>">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><?= $s['icon'] ?></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">
                                <?= htmlspecialchars($s['label']) ?>
                            </div>
                            <div class="mt-1 text-xl font-display text-gdrcd-text tabular-nums">
                                <?= htmlspecialchars((string)$s['value']) ?>
                            </div>
                        </div>
                    </div>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php elseif ($op === 'nuovi'):
        $result = gdrcd_query("SELECT nome, cognome, data_iscrizione FROM personaggio
                               WHERE data_iscrizione > DATE_SUB(NOW(), INTERVAL 7 DAY)
                               ORDER BY data_iscrizione DESC", 'result');
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['last_characters']) ?></h3>
            </header>
            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['character']) ?></th>
                            <th class="tabular-nums"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['date']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                        <tr>
                            <td>
                                <a href="main.php?page=scheda&pg=<?= urlencode($row['nome']) ?>" class="text-gdrcd-accent hover:underline">
                                    <?= gdrcd_filter('out', $row['nome'] . ' ' . $row['cognome']) ?>
                                </a>
                            </td>
                            <td class="tabular-nums text-sm">
                                <?= gdrcd_format_date($row['data_iscrizione']) ?>
                                <?= gdrcd_format_time($row['data_iscrizione']) ?>
                            </td>
                        </tr>
                    <?php endwhile; gdrcd_query($result, 'free'); ?>
                    </tbody>
                </table>
            </div>
        </article>
        <div>
            <a href="main.php?page=user_stats&links=yes" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['link']['back']) ?>
            </a>
        </div>
    <?php elseif ($op === 'esiliati'):
        $result = gdrcd_query("SELECT nome, cognome, esilio, data_esilio, autore_esilio, motivo_esilio
                               FROM personaggio WHERE esilio > NOW() ORDER BY data_esilio DESC", 'result');
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['exiled']) ?></h3>
            </header>
            <div class="overflow-x-auto">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['character']) ?></th>
                            <th class="tabular-nums"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['date_end']) ?></th>
                            <th><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['why']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                        <tr>
                            <td>
                                <a href="main.php?page=scheda&pg=<?= urlencode($row['nome']) ?>" class="text-gdrcd-accent hover:underline">
                                    <?= gdrcd_filter('out', $row['nome'] . ' ' . $row['cognome']) ?>
                                </a>
                            </td>
                            <td class="tabular-nums text-sm"><?= gdrcd_format_date($row['esilio']) ?></td>
                            <td class="text-sm">
                                <?= gdrcd_filter('out', $row['motivo_esilio']) ?>
                                <div class="text-xs text-gdrcd-text-soft mt-1">
                                    <?= gdrcd_filter('out', $row['autore_esilio'] . ' · ' . gdrcd_format_date($row['data_esilio'])) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; gdrcd_query($result, 'free'); ?>
                    </tbody>
                </table>
            </div>
        </article>
        <div>
            <a href="main.php?page=user_stats&links=yes" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['link']['back']) ?>
            </a>
        </div>
    <?php endif; ?>
</div>
