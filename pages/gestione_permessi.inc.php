<?php
/**
 * Gestione permessi (main.php?page=gestione_permessi)
 * Modifica il livello permessi di un personaggio (staff/admin).
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl    = $MESSAGE['interface']['administration']['roles'];
$op     = $_POST['op']    ?? '';
$is_super = ($_SESSION['permessi'] > MODERATOR);

/* Mappa ruoli → label (label dipende dai parametri config). */
$role_options = [
    USER           => $PARAMETERS['names']['users_name']['sing'],
    GUILDMODERATOR => $PARAMETERS['names']['guild_name']['lead'],
    GAMEMASTER     => $PARAMETERS['names']['master']['sing'],
];
if ($is_super) {
    $role_options[MODERATOR] = $PARAMETERS['names']['moderators']['sing'];
    $role_options[SUPERUSER] = $PARAMETERS['names']['administrator']['sing'];
}

$render_role_select = function (string $name, int $current = -1) use ($role_options) {
    $html = '<select class="gdrcd-select" name="' . htmlspecialchars($name) . '">';
    foreach ($role_options as $val => $label) {
        $sel = ($val === $current) ? ' selected' : '';
        $html .= '<option value="' . (int)$val . '"' . $sel . '>'
              .  gdrcd_filter('out', $label)
              .  '</option>';
    }
    $html .= '</select>';
    return $html;
};

/* Gestione submit edit/new */
$is_submit = ($op === $lbl['submit']['edit']) || ($op === $lbl['submit']['new']);
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Assegna o modifica il ruolo dei personaggi del gioco.</p>
    </header>

    <?php if ($is_submit):
        gdrcd_query(
            "UPDATE personaggio SET permessi = " . gdrcd_filter('num', $_POST['permessi']) . "
             WHERE nome = '" . gdrcd_filter('in', $_POST['nome']) . "' LIMIT 1"
        );

        $newrole_label = $role_options[(int)$_POST['permessi']] ?? '';
        $newrole       = gdrcd_filter('out', $newrole_label);

        gdrcd_query(
            "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES ("
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
            . "NOW(), '" . CHANGEDROLE . "', '->" . $newrole . "')"
        );

        $body = $MESSAGE['interface']['administration']['roles']['message_body'][0]
              . $newrole
              . $MESSAGE['interface']['administration']['roles']['message_body'][1];

        gdrcd_query(
            "INSERT INTO messaggi (mittente, destinatario, spedito, testo) VALUES ("
            . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
            . "'" . gdrcd_filter('in', $_POST['nome']) . "',"
            . "NOW(), '" . gdrcd_filter('in', $body) . "')"
        );
        ?>
        <div class="gdrcd-alert-success">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div>
                <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
                <span class="text-gdrcd-muted">·</span>
                <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['nome']) ?></strong>
                → <span class="gdrcd-badge-accent ml-1"><?= $newrole ?></span>
            </div>
        </div>

        <div>
            <a href="main.php?page=gestione_permessi" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $lbl['link']['back']) ?>
            </a>
        </div>

    <?php else: ?>

        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Staff attuale</h3>
                <p class="gdrcd-muted text-xs">Personaggi con ruolo superiore a utente. Modifica e applica per riga.</p>
            </div>

            <?php $result = gdrcd_query("SELECT nome, permessi FROM personaggio WHERE permessi > " . USER . " ORDER BY permessi DESC", 'result');
                  $staff_count = (int)gdrcd_query($result, 'num_rows'); ?>

            <?php if ($staff_count === 0): ?>
                <div class="p-6">
                    <div class="gdrcd-alert-info">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>Nessun membro dello staff al momento.</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="gdrcd-table-wrap !rounded-none !border-0 !shadow-none">
                    <table class="gdrcd-table">
                        <thead>
                            <tr>
                                <th>Personaggio</th>
                                <th>Ruolo attuale</th>
                                <th class="w-[260px]">Nuovo ruolo</th>
                                <th class="text-right w-[140px]"><span class="sr-only">Azioni</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = gdrcd_query($result, 'fetch')):
                                $current_label = $role_options[(int)$row['permessi']] ?? ('#' . (int)$row['permessi']);
                                ?>
                                <tr>
                                    <td class="font-medium text-gdrcd-text whitespace-nowrap">
                                        <?= gdrcd_filter('out', $row['nome']) ?>
                                    </td>
                                    <td>
                                        <span class="gdrcd-badge-accent"><?= gdrcd_filter('out', $current_label) ?></span>
                                    </td>
                                    <td>
                                        <form action="main.php?page=gestione_permessi" method="post" id="form_<?= gdrcd_filter('out', $row['nome']) ?>
                                            <?= gdrcd_csrf_field() ?>" class="flex items-center gap-2">
                                            <input type="hidden" name="nome" value="<?= gdrcd_filter('out', $row['nome']) ?>"/>
                                            <?= $render_role_select('permessi', (int)$row['permessi']) ?>
                                        </form>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button type="submit"
                                                form="form_<?= gdrcd_filter('out', $row['nome']) ?>"
                                                name="op"
                                                value="<?= gdrcd_filter('out', $lbl['submit']['edit']) ?>"
                                                class="gdrcd-btn-secondary">
                                            <?= gdrcd_filter('out', $lbl['submit']['edit']) ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile;
                            gdrcd_query($result, 'free');
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Assegna nuovo ruolo</h3>
                <p class="gdrcd-muted text-xs">Promuovi un personaggio scegliendo nome e ruolo da assegnare.</p>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=gestione_permessi" method="post" class="space-y-5">
                    <?= gdrcd_csrf_field() ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label" for="gp_nome">Personaggio</label>
                            <select class="gdrcd-select" id="gp_nome" name="nome">
                                <?php $result = gdrcd_query("SELECT nome FROM personaggio WHERE permessi > " . DELETED . " ORDER BY nome", 'result');
                                while ($row = gdrcd_query($result, 'fetch')): ?>
                                    <option value="<?= gdrcd_filter('out', $row['nome']) ?>">
                                        <?= gdrcd_filter('out', $row['nome']) ?>
                                    </option>
                                <?php endwhile;
                                gdrcd_query($result, 'free');
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="gdrcd-label" for="gp_permessi">Ruolo</label>
                            <?= $render_role_select('permessi', GAMEMASTER) ?>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <button type="submit"
                                name="op"
                                value="<?= gdrcd_filter('out', $lbl['submit']['new']) ?>"
                                class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $lbl['submit']['new']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

    <?php endif; ?>

</div>
