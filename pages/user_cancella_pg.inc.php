<?php
/**
 * Utente — cancella account proprio + force/restore (mod/superuser).
 */

$row = gdrcd_query("SELECT email, pass FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
$email = $row['email'] ?? '';
$storedPass = $row['pass'] ?? '';
$op = $_POST['op'] ?? null;
$alerts = [];
$logout = false;

if ($op === 'delete') {
    if (gdrcd_password_verify(gdrcd_filter_email($_POST['email'] ?? ''), $email)
        && gdrcd_password_verify($_POST['new_pass'] ?? '', $storedPass)
        && gdrcd_check_pass($_POST['new_pass'] ?? '') === true) {
        gdrcd_query("UPDATE personaggio SET permessi = -1
                     WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                             NOW(), " . DELETEPG . ", '" . gdrcd_filter('in', $MESSAGE['interface']['user']['delete']['undeleted']) . "')");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
        $logout = true;
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
    }
} elseif ($op === 'force' && $_SESSION['permessi'] === MODERATOR) {
    gdrcd_query("UPDATE personaggio SET permessi = -1
                 WHERE nome = '" . gdrcd_filter('in', $_POST['account']) . "' AND permessi < " . SUPERUSER);
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $_POST['account']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                         NOW(), " . DELETEPG . ", '" . gdrcd_filter('in', $MESSAGE['interface']['user']['delete']['deleted']) . "')");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
} elseif ($op === 'force' && $_SESSION['permessi'] === SUPERUSER) {
    gdrcd_query("UPDATE personaggio SET permessi = -1 WHERE nome = '" . gdrcd_filter('in', $_POST['account']) . "'");
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $_POST['account']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                         NOW(), " . DELETEPG . ", '->')");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
} elseif ($op === 'get_back' && $_SESSION['permessi'] >= MODERATOR) {
    gdrcd_query("UPDATE personaggio SET permessi = 0 WHERE nome = '" . gdrcd_filter('in', $_POST['account']) . "'");
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $_POST['account']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                         NOW(), " . DELETEPG . ", '<-')");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
}
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle text-gdrcd-error">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22m-9 0V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['user']['delete']['page_name']) ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($logout): ?>
        <div class="gdrcd-card p-4 text-center">
            <a href="index.php" class="gdrcd-btn-primary">
                <?= gdrcd_filter('out', $PARAMETERS['info']['homepage_name']) ?>
            </a>
        </div>
        <?php session_destroy(); return; ?>
    <?php endif; ?>

    <article class="gdrcd-card border-red-300">
        <header class="gdrcd-card-header bg-gdrcd-error-soft text-gdrcd-error">
            <h3 class="gdrcd-h3 text-gdrcd-error">Cancella il tuo account</h3>
        </header>
        <div class="gdrcd-card-body">
            <form action="main.php?page=user_cancella_pg" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?= gdrcd_csrf_field() ?>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['delete']['email']) ?></span>
                    <input type="email" name="email" class="gdrcd-input mt-1 w-full" required>
                </label>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['delete']['pass']) ?></span>
                    <input type="password" name="new_pass" class="gdrcd-input mt-1 w-full" required>
                </label>
                <div class="md:col-span-2 flex justify-end">
                    <input type="hidden" name="op" value="delete">
                    <button type="submit" class="gdrcd-btn-secondary text-red-600 border-red-300 hover:bg-red-50"
                            onclick="return confirm('Sei sicuro di voler cancellare il tuo account?');">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </article>

    <?php if ($_SESSION['permessi'] >= MODERATOR):
        $q = ($_SESSION['permessi'] == SUPERUSER)
            ? "SELECT nome FROM personaggio ORDER BY nome"
            : "SELECT nome FROM personaggio WHERE permessi < " . SUPERUSER . " ORDER BY nome";
        $active = gdrcd_query($q, 'result');
        $inactive = gdrcd_query("SELECT nome FROM personaggio WHERE permessi < 0 ORDER BY nome", 'result');
    ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <article class="gdrcd-card">
                <header class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['delete']['force']) ?></h3>
                </header>
                <div class="gdrcd-card-body">
                    <form action="main.php?page=user_cancella_pg" method="post" class="space-y-3">
                        <?= gdrcd_csrf_field() ?>
                        <select name="account" class="gdrcd-select w-full" required>
                            <option value="" disabled selected><?= gdrcd_filter('out', $MESSAGE['interface']['user']['delete']['who']) ?></option>
                            <?php while ($r = gdrcd_query($active, 'fetch')): ?>
                                <option value="<?= htmlspecialchars($r['nome']) ?>"><?= htmlspecialchars($r['nome']) ?></option>
                            <?php endwhile; gdrcd_query($active, 'free'); ?>
                        </select>
                        <input type="hidden" name="op" value="force">
                        <button type="submit" class="gdrcd-btn-secondary w-full">
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </form>
                </div>
            </article>

            <article class="gdrcd-card">
                <header class="gdrcd-card-header">
                    <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['get_back']['force']) ?></h3>
                </header>
                <div class="gdrcd-card-body">
                    <form action="main.php?page=user_cancella_pg" method="post" class="space-y-3">
                        <?= gdrcd_csrf_field() ?>
                        <select name="account" class="gdrcd-select w-full" required>
                            <option value="" disabled selected><?= gdrcd_filter('out', $MESSAGE['interface']['user']['get_back']['who']) ?></option>
                            <?php while ($r = gdrcd_query($inactive, 'fetch')): ?>
                                <option value="<?= htmlspecialchars($r['nome']) ?>"><?= htmlspecialchars($r['nome']) ?></option>
                            <?php endwhile; gdrcd_query($inactive, 'free'); ?>
                        </select>
                        <input type="hidden" name="op" value="get_back">
                        <button type="submit" class="gdrcd-btn-primary w-full">
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    </form>
                </div>
            </article>
        </div>
    <?php endif; ?>
</div>
