<?php
/**
 * Scheda personaggio (main.php?page=scheda&pg=<nome>).
 */

if (!isset($_REQUEST['pg']) || $_REQUEST['pg'] === '') {
    if (!empty($_SESSION['login'])) {
        $_REQUEST['pg'] = $_SESSION['login'];
    } else {
        echo '<div class="gdrcd-alert-error">'
           . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
           . '<div>' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>'
           . '</div>';
        return;
    }
}

$personaggi = gdrcd_query(
    "SELECT personaggio.*, razza.sing_m, razza.sing_f,
            razza.bonus_car0, razza.bonus_car1, razza.bonus_car2, razza.bonus_car3, razza.bonus_car4, razza.bonus_car5
     FROM personaggio LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
     WHERE personaggio.nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "'",
    'result'
);

if (gdrcd_query($personaggi, 'num_rows') === 0) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>'
       . '</div>';
    return;
}

$personaggio = gdrcd_query($personaggi, 'fetch');
$bonus_oggetti = gdrcd_query(
    "SELECT SUM(oggetto.bonus_car0) AS BO0, SUM(oggetto.bonus_car1) AS BO1,
            SUM(oggetto.bonus_car2) AS BO2, SUM(oggetto.bonus_car3) AS BO3,
            SUM(oggetto.bonus_car4) AS BO4, SUM(oggetto.bonus_car5) AS BO5
     FROM oggetto JOIN clgpersonaggiooggetto ON oggetto.id_oggetto = clgpersonaggiooggetto.id_oggetto
     WHERE clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "'
       AND clgpersonaggiooggetto.posizione > " . ZAINO
);

$lbl_s = $MESSAGE['interface']['sheet'];
?>

<div class="space-y-6">

    <?php if ($personaggio['esilio'] > date('Y-m-d')): ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            <div>
                <strong><?= gdrcd_filter('out', $personaggio['nome'] . ' ' . $personaggio['cognome']) ?></strong>
                <?= gdrcd_filter('out', $MESSAGE['warning']['character_exiled']) ?>
                <?= gdrcd_format_date($personaggio['esilio']) ?>
                <span class="text-gdrcd-muted">·</span>
                <em><?= gdrcd_filter('out', $personaggio['motivo_esilio']) ?></em>
                <span class="text-gdrcd-muted">·</span>
                <?= gdrcd_filter('out', $personaggio['autore_esilio']) ?>
            </div>
        </div>
        <?php if ($_SESSION['permessi'] >= GAMEMASTER): ?>
            <form action="main.php?page=scheda_modifica&pg=<?= urlencode($_REQUEST['pg']) ?>" method="post" class="flex justify-end">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="year" value="<?= date('Y') ?>"/>
                <input type="hidden" name="month" value="<?= date('m') ?>"/>
                <input type="hidden" name="day" value="<?= date('d') ?>"/>
                <input type="hidden" name="causale" value="<?= gdrcd_filter('out', $lbl_s['modify_form']['unexile']) ?>"/>
                <input type="hidden" name="op" value="exile"/>
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <?= gdrcd_filter('out', $lbl_s['modify_form']['unexile']) ?>
                </button>
            </form>
        <?php endif; ?>
    <?php
        return;
    endif;

    if (($PARAMETERS['mode']['alert_password_change'] ?? 'OFF') === 'ON'):
        $six_months  = 15552000;
        $ts_signup   = strtotime($personaggio['data_iscrizione']);
        $ts_lastpass = (int)strtotime($personaggio['ultimo_cambiopass'] ?? 0);
        if ($ts_lastpass + $six_months < time() && $personaggio['nome'] === $_SESSION['login']):
            $pass_msg = ($ts_signup + $six_months < time())
                ? $MESSAGE['warning']['changepass']
                : $MESSAGE['warning']['changepass_signup'];
            ?>
            <div class="gdrcd-alert-warning">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <div><?= $pass_msg ?></div>
            </div>
        <?php endif;
    endif; ?>

    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $personaggio['nome']) ?>
            <?php if (!empty($personaggio['cognome'])): ?>
                <span class="text-gdrcd-accent"><?= gdrcd_filter('out', $personaggio['cognome']) ?></span>
            <?php endif; ?>
        </h2>
        <p class="gdrcd-muted text-sm">
            <?= gdrcd_filter('out', $lbl_s['first_login']) ?> <?= gdrcd_format_date($personaggio['data_iscrizione']) ?>
            <?php if (gdrcd_format_date($personaggio['ora_entrata']) !== '00/00/0000'): ?>
                <span class="text-gdrcd-subtle">·</span>
                <?= gdrcd_filter('out', $lbl_s['last_login']) ?> <?= gdrcd_format_date($personaggio['ora_entrata']) ?>
            <?php endif; ?>
        </p>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-[18rem_minmax(0,1fr)] gap-6">

        <section class="gdrcd-card">
            <div class="gdrcd-card-body space-y-4">
                <?php if (!empty($personaggio['url_img'])): ?>
                    <img src="<?= htmlspecialchars(gdrcd_filter('fullurl', $personaggio['url_img'])) ?>"
                         alt="" class="w-full rounded-gdrcd object-cover border border-gdrcd-border bg-gdrcd-panel-alt"/>
                <?php else: ?>
                    <div class="aspect-square w-full rounded-gdrcd bg-gdrcd-accent-soft text-gdrcd-accent flex items-center justify-center font-display text-6xl font-bold">
                        <?= htmlspecialchars(strtoupper(mb_substr($personaggio['nome'] ?: '?', 0, 1))) ?>
                    </div>
                <?php endif; ?>

                <a href="main.php?page=messages_center&op=create&destinatario=<?= urlencode($personaggio['nome']) ?>"
                   class="gdrcd-btn-secondary w-full">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <?= gdrcd_filter('out', $lbl_s['send_message_to']['send'] . ' ' . strtolower($PARAMETERS['names']['private_message']['sing'])) ?>
                </a>
            </div>
        </section>

        <section class="space-y-4">
            <?php
            $rows = [];

            if ((int)$personaggio['permessi'] > 0) {
                $role_label = match ((int)$personaggio['permessi']) {
                    GUILDMODERATOR => $PARAMETERS['names']['guild_name']['lead'],
                    GAMEMASTER     => $PARAMETERS['names']['master']['sing'],
                    MODERATOR      => $PARAMETERS['names']['moderators']['sing'],
                    SUPERUSER      => $PARAMETERS['names']['administrator']['sing'],
                    default        => '',
                };
                if ($role_label !== '') {
                    $rows[$lbl_s['profile']['role']] = '<span class="gdrcd-badge-accent">' . gdrcd_filter('out', $role_label) . '</span>';
                }
            }

            $guilds = gdrcd_query(
                "SELECT ruolo.nome_ruolo, ruolo.gilda, ruolo.immagine, gilda.visibile, gilda.nome AS nome_gilda
                 FROM clgpersonaggioruolo
                 LEFT JOIN ruolo ON ruolo.id_ruolo = clgpersonaggioruolo.id_ruolo
                 LEFT JOIN gilda ON ruolo.gilda = gilda.id_gilda
                 WHERE clgpersonaggioruolo.personaggio = '" . gdrcd_filter('in', $personaggio['nome']) . "'",
                'result'
            );
            if (gdrcd_query($guilds, 'num_rows') === 0) {
                $rows[$lbl_s['profile']['occupation']] = '<span class="text-gdrcd-muted">' . gdrcd_filter('out', $lbl_s['profile']['uneployed']) . '</span>';
            } else {
                $g_html = '<div class="flex flex-wrap items-center gap-2">';
                while ($g = gdrcd_query($guilds, 'fetch')) {
                    $alt = gdrcd_filter('out', $g['nome_ruolo'] . (((int)$g['gilda'] !== -1 && !empty($g['nome_gilda'])) ? ' - ' . $g['nome_gilda'] : ''));
                    $img = '<img class="w-6 h-6 rounded border border-gdrcd-border bg-gdrcd-panel" '
                         . 'src="themes/' . htmlspecialchars($PARAMETERS['themes']['current_theme']) . '/imgs/guilds/' . gdrcd_filter('out', $g['immagine']) . '" '
                         . 'alt="' . $alt . '" title="' . $alt . '"/>';
                    if ((int)$g['gilda'] === -1) {
                        $g_html .= $img;
                    } elseif ((int)$g['visibile'] === 1 || (int)$_SESSION['permessi'] >= USER) {
                        $g_html .= '<a href="main.php?page=servizi_gilde&id_gilda=' . (int)$g['gilda'] . '">' . $img . '</a>';
                    }
                }
                $g_html .= '</div>';
                $rows[$lbl_s['profile']['occupation']] = $g_html;
            }

            if (!empty($personaggio['sing_f']) || !empty($personaggio['sing_m'])) {
                $razza_label = ($personaggio['sesso'] === 'f') ? $personaggio['sing_f'] : $personaggio['sing_m'];
            } else {
                $razza_label = $PARAMETERS['names']['race']['sing'] . ' ' . $lbl_s['profile']['no_race'];
            }
            $rows[$PARAMETERS['names']['race']['sing']] = gdrcd_filter('out', $razza_label);

            $rows[$lbl_s['profile']['experience']] = '<span class="font-semibold text-gdrcd-text tabular-nums">' . (int)floor($personaggio['esperienza']) . '</span>';

            $hp_pct = ((int)$personaggio['salute_max'] > 0)
                ? min(100, max(0, ($personaggio['salute'] / $personaggio['salute_max']) * 100))
                : 0;
            $hp_color = $hp_pct > 60 ? 'bg-gdrcd-success' : ($hp_pct > 30 ? 'bg-gdrcd-warning' : 'bg-gdrcd-error');
            $rows[$PARAMETERS['names']['stats']['hitpoints']] = ''
                . '<div class="flex items-center gap-2">'
                . '  <div class="flex-1 h-2 bg-gdrcd-panel-alt rounded-full overflow-hidden">'
                . '    <div class="h-full ' . $hp_color . '" style="width:' . $hp_pct . '%;"></div>'
                . '  </div>'
                . '  <span class="text-xs tabular-nums text-gdrcd-text-soft">' . (int)$personaggio['salute'] . '/' . (int)$personaggio['salute_max'] . '</span>'
                . '</div>';
            ?>

            <div class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl_s['box_title']['profile']) ?></h3>
                </div>
                <div class="gdrcd-card-body">
                    <dl class="grid grid-cols-1 sm:grid-cols-[10rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                        <?php foreach ($rows as $label => $value): ?>
                            <dt class="text-gdrcd-muted"><?= gdrcd_filter('out', $label) ?></dt>
                            <dd class="text-gdrcd-text-soft"><?= $value ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>

            <div class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Caratteristiche</h3>
                </div>
                <div class="gdrcd-card-body">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php for ($i = 0; $i < 6; $i++):
                            $val = (int)$personaggio['car'.$i] + (int)$personaggio['bonus_car'.$i] + (int)($bonus_oggetti['BO'.$i] ?? 0);
                            ?>
                            <div class="border border-gdrcd-border rounded-gdrcd p-3 bg-gdrcd-panel-alt/30 text-center">
                                <div class="text-xs text-gdrcd-muted uppercase tracking-wide font-medium">
                                    <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i]) ?>
                                </div>
                                <div class="text-2xl font-display font-bold text-gdrcd-text mt-1 tabular-nums">
                                    <?= $val ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($personaggio['stato'])): ?>
                <div class="gdrcd-card">
                    <div class="gdrcd-card-header">
                        <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl_s['profile']['status']) ?></h3>
                    </div>
                    <div class="gdrcd-card-body gdrcd-prose">
                        <?= nl2br(gdrcd_filter('out', $personaggio['stato'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if (($PARAMETERS['mode']['skillsystem'] ?? 'OFF') === 'ON'): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl_s['box_title']['skills']) ?></h3>
            </div>
            <div class="gdrcd-card-body">
                <?php include 'scheda/skillsystem.inc.php'; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $personaggio['url_media'] = gdrcd_filter('fullurl', $personaggio['url_media']);
    if (($PARAMETERS['mode']['allow_audio'] ?? 'OFF') === 'ON'
        && empty($_SESSION['blocca_media'])
        && !empty($personaggio['url_media'])):
        $ext = '.' . strtolower(pathinfo($personaggio['url_media'], PATHINFO_EXTENSION));
        ?>
        <audio autoplay class="hidden">
            <source src="<?= htmlspecialchars($personaggio['url_media']) ?>"
                    type="<?= htmlspecialchars($PARAMETERS['settings']['audiotype'][$ext] ?? 'audio/mpeg') ?>">
        </audio>
    <?php endif; ?>

</div>
