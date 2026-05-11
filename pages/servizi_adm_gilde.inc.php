<?php
/**
 * Servizi — Amministrazione gilde: assunzioni, licenziamenti, dimissioni.
 */

$op = $_POST['op'] ?? null;
$alerts = [];

if ($op === 'hire' && $_SESSION['permessi'] >= GUILDMODERATOR) {
    $nome_in = $_POST['nome'] ?? '';
    $jobs = Db::preparedFetch(
        "SELECT COUNT(*) AS n FROM clgpersonaggioruolo WHERE personaggio = ?",
        's',
        [$nome_in]
    ) ?? ['n' => 0];
    if ((int)$jobs['n'] >= $PARAMETERS['settings']['guilds_limit']) {
        $alerts[] = ['error', gdrcd_filter('out', $nome_in . ' ' . $MESSAGE['interface']['adm_guilds']['cannot_hire'])];
    } else {
        $subject = explode('-', $_POST['ruolo'] ?? '');
        $ruolo = (int)($subject[0] ?? 0);
        $data = Db::preparedFetch(
            "SELECT gilda FROM ruolo WHERE id_ruolo = ? LIMIT 1",
            'i',
            [$ruolo]
        ) ?? ['gilda' => 0];
        $ruoli_capi = Db::preparedFetchAll(
            "SELECT id_ruolo FROM ruolo WHERE gilda = ? AND capo = 1",
            'i',
            [(int)$data['gilda']]
        );
        $contr = false;
        foreach ($ruoli_capi as $rc) {
            $check = Db::preparedFetch(
                "SELECT COUNT(*) AS tot FROM clgpersonaggioruolo
                 WHERE personaggio = ? AND id_ruolo = ?",
                'si',
                [$nome_in, (int)$rc['id_ruolo']]
            ) ?? ['tot' => 0];
            if ((int)$check['tot'] > 0) { $contr = true; break; }
        }
        if ($contr || $_SESSION['permessi'] >= MODERATOR) {
            Db::preparedExecute(
                "INSERT INTO clgpersonaggioruolo (personaggio, id_ruolo, scadenza)
                 VALUES (?, ?, NOW())",
                'si',
                [$nome_in, $ruolo]
            );
            $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['adm_guilds']['ok_hire'])];
            Db::preparedExecute(
                "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES (?, ?, NOW(), ?, ?)",
                'ssis',
                [$nome_in, $_SESSION['login'], (int)NUOVOLAVORO, $subject[1] ?? '']
            );
            if ($_SESSION['login'] != $nome_in) {
                Db::preparedExecute(
                    "INSERT INTO messaggi (mittente, destinatario, spedito, testo)
                     VALUES (?, ?, NOW(), ?)",
                    'sss',
                    [
                        $_SESSION['login'],
                        $nome_in,
                        $MESSAGE['interface']['adm-guilds']['message_body']['hire'] . ' ' . ($subject[1] ?? ''),
                    ]
                );
            }
        }
    }
}

if ($op === 'fire' && $_SESSION['permessi'] >= GUILDMODERATOR) {
    $subject = explode('-', $_POST['ruolo'] ?? '');
    if (count($subject) >= 3) {
        $ruolo = (int)$subject[1];
        $data = Db::preparedFetch(
            "SELECT gilda FROM ruolo WHERE id_ruolo = ? LIMIT 1",
            'i',
            [$ruolo]
        ) ?? ['gilda' => 0];
        $ruoli_capi = Db::preparedFetchAll(
            "SELECT id_ruolo FROM ruolo WHERE gilda = ? AND capo = 1",
            'i',
            [(int)$data['gilda']]
        );
        $nome_in = $_POST['nome'] ?? $subject[0];
        $contr = false;
        foreach ($ruoli_capi as $rc) {
            $check = Db::preparedFetch(
                "SELECT COUNT(*) AS tot FROM clgpersonaggioruolo
                 WHERE personaggio = ? AND id_ruolo = ?",
                'si',
                [$nome_in, (int)$rc['id_ruolo']]
            ) ?? ['tot' => 0];
            if ((int)$check['tot'] > 0) { $contr = true; break; }
        }
        if ($contr || $_SESSION['permessi'] >= MODERATOR) {
            Db::preparedExecute(
                "DELETE FROM clgpersonaggioruolo
                 WHERE personaggio = ? AND id_ruolo = ? LIMIT 1",
                'si',
                [$subject[0], $ruolo]
            );
            $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['adm_guilds']['ok_fire'])];
            Db::preparedExecute(
                "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES (?, ?, NOW(), ?, ?)",
                'ssis',
                [$subject[0], $_SESSION['login'], (int)DIMISSIONE, $subject[2]]
            );
            if ($_SESSION['login'] != $subject[0]) {
                Db::preparedExecute(
                    "INSERT INTO messaggi (mittente, destinatario, spedito, testo)
                     VALUES (?, ?, NOW(), ?)",
                    'sss',
                    [
                        $_SESSION['login'],
                        $subject[0],
                        $MESSAGE['interface']['adm-guilds']['message_body']['fire'] . ' ' . $subject[2],
                    ]
                );
            }
        }
    }
}

if ($op === 'fire-yourself') {
    $ruolo = (int)gdrcd_filter('num', $_POST['ruolo'] ?? 0);
    Db::preparedExecute(
        "DELETE FROM clgpersonaggioruolo WHERE personaggio = ? AND id_ruolo = ? LIMIT 1",
        'si',
        [$_SESSION['login'], $ruolo]
    );
    $alerts[] = ['success', 'Licenziamento avvenuto con successo.'];
}

// Carico dati per form
$is_mod = $_SESSION['permessi'] >= MODERATOR;
$is_guildmod = $_SESSION['permessi'] >= GUILDMODERATOR;

if ($is_guildmod) {
    if ($is_mod) {
        $q_ruoli = "SELECT ruolo.id_ruolo, ruolo.nome_ruolo, gilda.nome FROM ruolo
                    LEFT JOIN gilda ON ruolo.gilda = gilda.id_gilda
                    ORDER BY gilda.nome, ruolo.capo DESC, ruolo.stipendio DESC, ruolo.nome_ruolo";
        $q_membri = "SELECT clgpersonaggioruolo.personaggio, clgpersonaggioruolo.id_ruolo, ruolo.nome_ruolo, ruolo.gilda
                     FROM clgpersonaggioruolo JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
                     ORDER BY ruolo.gilda DESC, ruolo.stipendio DESC";
    } else {
        $login_in = gdrcd_filter('in', $_SESSION['login']);
        $q_ruoli = "SELECT ruolo.id_ruolo, ruolo.nome_ruolo, gilda.nome FROM ruolo
                    JOIN gilda ON ruolo.gilda = gilda.id_gilda
                    WHERE ruolo.gilda IN (SELECT ruolo.gilda FROM clgpersonaggioruolo
                                          JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
                                          WHERE clgpersonaggioruolo.personaggio='{$login_in}'
                                          AND ruolo.gilda>-1 AND ruolo.capo = 1)
                    ORDER BY gilda.nome, ruolo.capo DESC, ruolo.stipendio DESC, ruolo.nome_ruolo";
        $q_membri = "SELECT clgpersonaggioruolo.personaggio, clgpersonaggioruolo.id_ruolo, ruolo.nome_ruolo, ruolo.gilda
                     FROM clgpersonaggioruolo JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
                     WHERE ruolo.gilda IN (SELECT ruolo.gilda FROM clgpersonaggioruolo
                                           JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
                                           WHERE clgpersonaggioruolo.personaggio='{$login_in}'
                                           AND ruolo.gilda>-1 AND ruolo.capo = 1) OR ruolo.gilda=-1
                     ORDER BY ruolo.gilda DESC, ruolo.stipendio DESC";
    }
    $ruoli_res = gdrcd_query($q_ruoli, 'result');
    $people_res = gdrcd_query("SELECT nome, cognome FROM personaggio WHERE permessi > -1 ORDER BY nome", 'result');
    $membri_res = gdrcd_query($q_membri, 'result');
}

$me = gdrcd_filter('in', $_SESSION['login']);
$miei_ruoli = gdrcd_query("SELECT ruolo.id_ruolo, ruolo.nome_ruolo FROM clgpersonaggioruolo
                            LEFT JOIN ruolo ON ruolo.id_ruolo = clgpersonaggioruolo.id_ruolo
                            WHERE clgpersonaggioruolo.personaggio='{$me}'", 'result');
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['adm_guilds']['page_name'] . ' ' . strtolower($PARAMETERS['names']['guild_name']['plur'])) ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($is_guildmod):
        $has_ruoli = gdrcd_query($ruoli_res, 'num_rows') > 0;
    ?>
        <?php if (!$has_ruoli): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/></svg>
                <div><?= $MESSAGE['interface']['adm_guilds']['no_adm'] . ' ' . strtolower($PARAMETERS['names']['guild_name']['sing']) ?></div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Assumi -->
                <article class="gdrcd-card">
                    <header class="gdrcd-card-header">
                        <h3 class="gdrcd-h3"><?= $MESSAGE['interface']['adm_guilds']['new_member'] . ' ' . strtolower($PARAMETERS['names']['guild_name']['members']) ?></h3>
                    </header>
                    <div class="gdrcd-card-body">
                        <form action="main.php?page=servizi_adm_gilde" method="post" class="space-y-3">
                            <?= gdrcd_csrf_field() ?>
                            <label class="block">
                                <span class="text-sm text-gdrcd-text-soft">Ruolo</span>
                                <select name="ruolo" class="gdrcd-select mt-1 w-full">
                                    <?php while ($row = gdrcd_query($ruoli_res, 'fetch')): ?>
                                        <option value="<?= (int)$row['id_ruolo'] . '-' . htmlspecialchars($row['nome_ruolo']) ?>">
                                            <?= htmlspecialchars($row['nome_ruolo']) ?>
                                            (<?= !empty($row['nome']) ? htmlspecialchars($row['nome']) : $MESSAGE['interface']['adm_guilds']['freelance'] ?>)
                                        </option>
                                    <?php endwhile; gdrcd_query($ruoli_res, 'free'); ?>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm text-gdrcd-text-soft">Personaggio</span>
                                <select name="nome" class="gdrcd-select mt-1 w-full">
                                    <?php while ($row = gdrcd_query($people_res, 'fetch')): ?>
                                        <option value="<?= htmlspecialchars($row['nome']) ?>">
                                            <?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?>
                                        </option>
                                    <?php endwhile; gdrcd_query($people_res, 'free'); ?>
                                </select>
                            </label>
                            <div class="flex justify-end">
                                <input type="hidden" name="op" value="hire">
                                <button type="submit" class="gdrcd-btn-primary">
                                    <?= $MESSAGE['interface']['adm_guilds']['hire'] ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </article>

                <!-- Licenzia -->
                <article class="gdrcd-card">
                    <header class="gdrcd-card-header">
                        <h3 class="gdrcd-h3"><?= $MESSAGE['interface']['adm_guilds']['fire_member'] . ' ' . strtolower($PARAMETERS['names']['guild_name']['members']) ?></h3>
                    </header>
                    <div class="gdrcd-card-body">
                        <form action="main.php?page=servizi_adm_gilde" method="post" class="space-y-3">
                            <?= gdrcd_csrf_field() ?>
                            <label class="block">
                                <span class="text-sm text-gdrcd-text-soft">Membro</span>
                                <select name="ruolo" class="gdrcd-select mt-1 w-full">
                                    <?php
                                    $echoed_null_row = false;
                                    while ($row = gdrcd_query($membri_res, 'fetch')):
                                        if (!$echoed_null_row && $row['gilda'] == -1):
                                            echo '<option value="" disabled>──────────</option>';
                                            $echoed_null_row = true;
                                        endif;
                                    ?>
                                        <option value="<?= htmlspecialchars($row['personaggio'] . '-' . $row['id_ruolo'] . '-' . $row['nome_ruolo']) ?>">
                                            <?= htmlspecialchars($row['personaggio'] . ' (' . $row['nome_ruolo'] . ')') ?>
                                        </option>
                                    <?php endwhile; gdrcd_query($membri_res, 'free'); ?>
                                </select>
                            </label>
                            <div class="flex justify-end">
                                <input type="hidden" name="op" value="fire">
                                <button type="submit" class="gdrcd-btn-secondary">
                                    <?= $MESSAGE['interface']['adm_guilds']['fire'] ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </article>
            </div>
        <?php endif;
    endif; ?>

    <!-- Dimissioni proprie -->
    <?php if (gdrcd_query($miei_ruoli, 'num_rows') > 0): ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= $MESSAGE['interface']['adm_guilds']['quit'] ?? 'Dimissioni' ?></h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=servizi_adm_gilde" method="post" class="flex flex-col md:flex-row gap-3">
                    <?= gdrcd_csrf_field() ?>
                    <select name="ruolo" class="gdrcd-select flex-1">
                        <option value=""></option>
                        <?php foreach ($miei_ruoli as $r): ?>
                            <option value="<?= (int)$r['id_ruolo'] ?>"><?= gdrcd_filter('out', $r['nome_ruolo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="op" value="fire-yourself">
                    <button type="submit" class="gdrcd-btn-secondary">Licenziati</button>
                </form>
            </div>
        </article>
    <?php endif; ?>
</div>
