<?php
/**
 * Utente — cambio nome PG proprio (entro 7gg) + force (mod/superuser).
 */

$row = Db::preparedFetch(
    "SELECT email, pass, DATE_ADD(data_iscrizione, INTERVAL 7 DAY) AS data
     FROM personaggio WHERE nome = ?",
    's',
    [$_SESSION['login']]
);
$email = $row['email'] ?? '';
$pass  = $row['pass'] ?? '';
$iscriz = explode(' ', $row['data'] ?? '')[0];
$today  = date('Y-m-d');

$op = $_POST['op'] ?? null;
$alerts = [];

$rename_self = function ($new_name) {
    $old = $_SESSION['login'];
    $new = $new_name;
    Db::preparedExecute(
        "UPDATE personaggio SET nome = ? WHERE nome = ?",
        'ss',
        [$new, $old]
    );
    Db::preparedExecute(
        "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
         VALUES (?, ?, NOW(), ?, ?)",
        'ssis',
        [$new, $old, (int)CHANGEDNAME, $old . ' -> ' . $new]
    );
    foreach (['log' => ['nome_interessato', 'autore'],
              'messaggi' => ['mittente', 'destinatario'],
              'backmessaggi' => ['mittente', 'destinatario'],
              'clgpersonaggioabilita' => ['nome'],
              'clgpersonaggiomostrine' => ['nome'],
              'clgpersonaggiooggetto' => ['nome'],
              'clgpersonaggioruolo' => ['personaggio']] as $table => $fields) {
        foreach ($fields as $f) {
            // Nome tabella/colonna NON parametrizzabile, ma deriva da
            // costanti hard-coded sopra; i valori (vecchio/nuovo nome)
            // passano da prepared statement.
            Db::preparedExecute(
                "UPDATE {$table} SET {$f} = ? WHERE {$f} = ?",
                'ss',
                [$new, $old]
            );
        }
    }
};

if ($op === 'new') {
    $new_name = $_POST['new_name'] ?? '';
    if (gdrcd_password_verify(gdrcd_filter_email($_POST['email'] ?? ''), $email)
        && gdrcd_password_verify($_POST['new_pass'] ?? '', $pass)
        && $iscriz >= $today
        && !empty($new_name)) {
        $check = Db::preparedFetch(
            "SELECT nome FROM personaggio WHERE nome = ?",
            's',
            [$new_name]
        );
        if ($check !== null) {
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
    $check = Db::preparedFetch(
        "SELECT nome FROM personaggio WHERE nome = ?",
        's',
        [$_POST['new_name']]
    );
    if ($check !== null) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['error']['existing_name'])];
    } else {
        $old = $_POST['account'];
        $new = $_POST['new_name'];
        $is_superuser = ($_SESSION['permessi'] == SUPERUSER);
        // SUPERUSER puo' rinominare anche superuser; gli altri solo permessi < SUPERUSER.
        $extra_where = $is_superuser ? '' : ' AND permessi < ' . (int)SUPERUSER;
        // Le tabelle senza colonna permessi (log/messaggi/backmessaggi/cl*)
        // non hanno il filtro; il vecchio codice faceva lo stesso check
        // appendendo $where_clause anche dove `permessi` non esisteva, ma
        // affidandosi alla mancata corrispondenza della colonna; manteniamo
        // il comportamento legacy: senza filtro su quelle tabelle.
        Db::preparedExecute("UPDATE log SET nome_interessato = ? WHERE nome_interessato = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE log SET autore = ? WHERE autore = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE messaggi SET mittente = ? WHERE mittente = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE messaggi SET destinatario = ? WHERE destinatario = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE backmessaggi SET mittente = ? WHERE mittente = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE backmessaggi SET destinatario = ? WHERE destinatario = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE clgpersonaggioabilita SET nome = ? WHERE nome = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE clgpersonaggiomostrine SET nome = ? WHERE nome = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE clgpersonaggiooggetto SET nome = ? WHERE nome = ?", 'ss', [$new, $old]);
        Db::preparedExecute("UPDATE clgpersonaggioruolo SET personaggio = ? WHERE personaggio = ?", 'ss', [$new, $old]);
        Db::preparedExecute(
            "UPDATE personaggio SET nome = ? WHERE nome = ?" . $extra_where,
            'ss',
            [$new, $old]
        );
        Db::preparedExecute(
            "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
             VALUES (?, ?, NOW(), ?, ?)",
            'ssis',
            [$old, $_SESSION['login'], (int)CHANGEDNAME, $old . ' -> ' . $new]
        );
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
                    <?= gdrcd_csrf_field() ?>
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
                    <?= gdrcd_csrf_field() ?>
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
