<?php
/**
 * Utente — cambio nome PG proprio (entro 7gg) + force (mod/superuser).
 */

$row = gdrcd_query("SELECT email, pass, DATE_ADD(data_iscrizione, INTERVAL 7 DAY) AS data
                    FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
$email = $row['email'] ?? '';
$pass  = $row['pass'] ?? '';
$iscriz = explode(' ', $row['data'] ?? '')[0];
$today  = date('Y-m-d');

$op = $_POST['op'] ?? null;
$alerts = [];

$rename_self = function ($new_name) {
    $old = gdrcd_filter('in', $_SESSION['login']);
    $new = gdrcd_filter('in', $new_name);
    gdrcd_query("UPDATE personaggio SET nome = '{$new}' WHERE nome = '{$old}'");
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('{$new}', '{$old}', NOW(), " . CHANGEDNAME . ", '{$old} -> {$new}')");
    foreach (['log' => ['nome_interessato', 'autore'],
              'messaggi' => ['mittente', 'destinatario'],
              'backmessaggi' => ['mittente', 'destinatario'],
              'clgpersonaggioabilita' => ['nome'],
              'clgpersonaggiomostrine' => ['nome'],
              'clgpersonaggiooggetto' => ['nome'],
              'clgpersonaggioruolo' => ['personaggio']] as $table => $fields) {
        foreach ($fields as $f) {
            gdrcd_query("UPDATE {$table} SET {$f} = '{$new}' WHERE {$f} = '{$old}'");
        }
    }
};

if ($op === 'new') {
    $new_name = $_POST['new_name'] ?? '';
    if ($email === gdrcd_filter_email($_POST['email'] ?? '')
        && $pass === gdrcd_encript($_POST['new_pass'] ?? '')
        && $iscriz >= $today
        && !empty($new_name)) {
        $check = gdrcd_query("SELECT nome FROM personaggio WHERE nome = '" . gdrcd_filter('in', $new_name) . "'", 'result');
        if (gdrcd_query($check, 'num_rows') > 0) {
            $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['error']['existing_name'])];
        } else {
            $rename_self($new_name);
            $_SESSION['login'] = gdrcd_filter('get', $new_name);
            $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
        }
    } else {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
    }
} elseif ($op === 'force' && $_SESSION['permessi'] >= MODERATOR && !empty($_POST['new_name'])) {
    $check = gdrcd_query("SELECT nome FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_POST['new_name']) . "'", 'result');
    if (gdrcd_query($check, 'num_rows') > 0) {
        gdrcd_query($check, 'free');
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['error']['existing_name'])];
    } else {
        $old = gdrcd_filter('in', $_POST['account']);
        $new = gdrcd_filter('in', $_POST['new_name']);
        $where_clause = ($_SESSION['permessi'] == SUPERUSER) ? '' : " AND permessi < " . SUPERUSER;
        gdrcd_query("UPDATE log SET nome_interessato = '{$new}' WHERE nome_interessato = '{$old}'");
        gdrcd_query("UPDATE log SET autore = '{$new}' WHERE autore = '{$old}'");
        gdrcd_query("UPDATE messaggi SET mittente = '{$new}' WHERE mittente = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE messaggi SET destinatario = '{$new}' WHERE destinatario = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE backmessaggi SET mittente = '{$new}' WHERE mittente = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE backmessaggi SET destinatario = '{$new}' WHERE destinatario = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE clgpersonaggioabilita SET nome = '{$new}' WHERE nome = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE clgpersonaggiomostrine SET nome = '{$new}' WHERE nome = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE clgpersonaggiooggetto SET nome = '{$new}' WHERE nome = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE clgpersonaggioruolo SET personaggio = '{$new}' WHERE personaggio = '{$old}'{$where_clause}");
        gdrcd_query("UPDATE personaggio SET nome = '{$new}' WHERE nome = '{$old}'{$where_clause}");
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('{$old}', '" . gdrcd_filter('in', $_SESSION['login']) . "', NOW(), " . CHANGEDNAME . ", '{$old} -> {$new}')");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
    }
} elseif ($op === 'force') {
    $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
}
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['user']['name']['page_name']) ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <?php if ($iscriz >= $today): ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Cambio nome personale</h3>
                <p class="text-xs text-gdrcd-text-soft mt-1">Disponibile fino al <?= htmlspecialchars($iscriz) ?> (7gg dalla creazione).</p>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=user_cambio_nome" method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['name']['email']) ?></span>
                        <input type="email" name="email" class="gdrcd-input mt-1 w-full" required>
                    </label>
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['name']['pass']) ?></span>
                        <input type="password" name="new_pass" class="gdrcd-input mt-1 w-full" required>
                    </label>
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['name']['new']) ?></span>
                        <input type="text" name="new_name" class="gdrcd-input mt-1 w-full" required>
                    </label>
                    <div class="md:col-span-3 flex justify-end">
                        <input type="hidden" name="op" value="new">
                        <button type="submit" class="gdrcd-btn-primary">
                            <?= gdrcd_filter('out', $MESSAGE['interface']['user']['pass']['submit']['user']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </article>
    <?php else: ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>
            <div>Il periodo per il cambio nome volontario è scaduto.</div>
        </div>
    <?php endif; ?>

    <?php if ($_SESSION['permessi'] >= MODERATOR):
        $q = ($_SESSION['permessi'] == SUPERUSER)
            ? "SELECT nome FROM personaggio ORDER BY nome"
            : "SELECT nome FROM personaggio WHERE permessi < " . SUPERUSER . " ORDER BY nome";
        $result = gdrcd_query($q, 'result');
    ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['name']['force']) ?></h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=user_cambio_nome" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['name']['change_to']) ?></span>
                        <select name="account" class="gdrcd-select mt-1 w-full" required>
                            <option value="" disabled selected></option>
                            <?php while ($r = gdrcd_query($result, 'fetch')): ?>
                                <option value="<?= htmlspecialchars($r['nome']) ?>"><?= htmlspecialchars($r['nome']) ?></option>
                            <?php endwhile; gdrcd_query($result, 'free'); ?>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm text-gdrcd-text-soft">Nuovo nome</span>
                        <input type="text" name="new_name" class="gdrcd-input mt-1 w-full" required>
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
