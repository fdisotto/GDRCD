<?php
/**
 * Utente — cambio password proprio + force (mod/superuser).
 */

$row = gdrcd_query("SELECT email FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
$email = $row['email'] ?? '';
$op = $_POST['op'] ?? null;
$alerts = [];

if ($op === 'new') {
    if (gdrcd_password_check(gdrcd_filter_email($_POST['email'] ?? ''), $email) && gdrcd_check_pass($_POST['new_pass'] ?? '') === true) {
        gdrcd_query("UPDATE personaggio SET pass = '" . gdrcd_filter('in', gdrcd_password_hash($_POST['new_pass'])) . "', ultimo_cambiopass = NOW()
                     WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                             NOW(), " . CHANGEDPASS . ", '" . gdrcd_filter('in', $_SERVER['REMOTE_ADDR']) . "')");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
    }
} elseif ($op === 'force' && $_SESSION['permessi'] >= MODERATOR && gdrcd_check_pass($_POST['new_pass'] ?? '') === true) {
    $where = ($_SESSION['permessi'] == SUPERUSER)
        ? "nome = '" . gdrcd_filter_in($_POST['account']) . "'"
        : "nome = '" . gdrcd_filter_in($_POST['account']) . "' AND permessi < " . SUPERUSER;
    gdrcd_query("UPDATE personaggio SET pass = '" . gdrcd_filter('in', gdrcd_password_hash($_POST['new_pass'])) . "', ultimo_cambiopass = NOW() WHERE " . $where);
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter_in($_POST['account']) . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                         NOW(), " . CHANGEDPASS . ", '" . gdrcd_filter('in', $_SERVER['REMOTE_ADDR']) . "')");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
} elseif ($op === 'force') {
    $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
}
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['page_name']) ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3">La tua password</h3>
        </header>
        <div class="gdrcd-card-body">
            <form action="main.php?page=user_cambio_pass" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?= gdrcd_csrf_field() ?>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['email']) ?></span>
                    <input type="email" name="email" class="gdrcd-input mt-1 w-full" required>
                </label>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['new']) ?></span>
                    <input type="password" name="new_pass" class="gdrcd-input mt-1 w-full" required>
                </label>
                <div class="md:col-span-2 flex justify-end">
                    <input type="hidden" name="op" value="new">
                    <button type="submit" class="gdrcd-btn-primary">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['submit']['user']) ?>
                    </button>
                </div>
            </form>
        </div>
    </article>

    <?php if ($_SESSION['permessi'] >= MODERATOR):
        $q = ($_SESSION['permessi'] == SUPERUSER)
            ? "SELECT nome FROM personaggio ORDER BY nome"
            : "SELECT nome FROM personaggio WHERE permessi < " . SUPERUSER . " ORDER BY nome";
        $result = gdrcd_query($q, 'result');
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['force']) ?></h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=user_cambio_pass" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?= gdrcd_csrf_field() ?>
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['change_to']) ?></span>
                        <select name="account" class="gdrcd-select mt-1 w-full" required>
                            <option value="" disabled selected></option>
                            <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                                <option value="<?= htmlspecialchars($row['nome']) ?>"><?= htmlspecialchars($row['nome']) ?></option>
                            <?php endwhile; gdrcd_query($result, 'free'); ?>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['new']) ?></span>
                        <input type="text" name="new_pass" class="gdrcd-input mt-1 w-full" required>
                    </label>
                    <div class="md:col-span-2 flex justify-end">
                        <input type="hidden" name="op" value="force">
                        <button type="submit" class="gdrcd-btn-secondary">
                            <?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['submit']['user']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </article>
    <?php endif; ?>
</div>
