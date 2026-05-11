<?php
/**
 * Servizi — Banca: saldo, deposito, prelievo, bonifico, stipendio.
 */

$row = gdrcd_query("SELECT soldi, banca, ultimo_stipendio FROM personaggio
                    WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
$soldi  = (int)($row['soldi'] ?? 0);
$banca  = (int)($row['banca'] ?? 0);
$ultimo = $row['ultimo_stipendio'] ?? '';

$result = gdrcd_query(
    "SELECT ruolo.stipendio FROM clgpersonaggioruolo
     LEFT JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
     WHERE clgpersonaggioruolo.personaggio = '" . gdrcd_filter('in', $_SESSION['login']) . "'",
    'result'
);
$stipendio = 0;
while ($r = gdrcd_query($result, 'fetch')) {
    $stipendio += (int)$r['stipendio'];
}
gdrcd_query($result, 'free');

$alerts = [];
$op = $_POST['op'] ?? null;
$today = date('Y-m-d');
$amount_in = gdrcd_filter('num', $_POST['ammontare'] ?? 0);
$amount    = is_numeric($_POST['ammontare'] ?? null) ? (int)$_POST['ammontare'] : 0;

if ($op === 'preleva') {
    if ($amount <= 0) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['interface']['bank']['error'])];
    } elseif ($amount > $banca) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['interface']['bank']['withdraw_no'])];
    } else {
        gdrcd_query("UPDATE personaggio SET soldi = soldi + " . $amount_in . ", banca = banca - " . $amount_in . "
                     WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['bank']['done'])];
        $banca -= $amount;
        $soldi += $amount;
    }
} elseif ($op === 'deposita') {
    if ($amount <= 0) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['interface']['bank']['error'])];
    } elseif ($amount > $soldi) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['interface']['bank']['deposit_no'])];
    } else {
        gdrcd_query("UPDATE personaggio SET soldi = soldi - " . $amount_in . ", banca = banca + " . $amount_in . "
                     WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['bank']['done'])];
        $banca += $amount;
        $soldi -= $amount;
    }
} elseif ($op === 'bonifico') {
    $beneficiario = gdrcd_filter('in', $_POST['beneficiario'] ?? '');
    if (empty($beneficiario)) {
        $alerts[] = ['error', "Il beneficiario inserito non esiste o non è valido."];
    } elseif ($amount <= 0) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['interface']['bank']['error'])];
    } elseif ($amount > $banca) {
        $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['interface']['bank']['withdraw_no'])];
    } else {
        gdrcd_query("UPDATE personaggio SET banca = banca - " . $amount_in . "
                     WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        gdrcd_query("UPDATE personaggio SET banca = banca + " . $amount_in . "
                     WHERE nome = '" . $beneficiario . "' LIMIT 1");
        $causale_in = gdrcd_filter('in', $_POST['causale'] ?? '');
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('" . $beneficiario . "', '" . gdrcd_filter('in', $_SESSION['login']) . "',
                             NOW(), " . BONIFICO . ",
                             '(" . $amount_in . " " . $PARAMETERS['names']['currency']['plur'] . ") " . $causale_in . "')");
        gdrcd_query("INSERT INTO messaggi (mittente, destinatario, spedito, testo)
                     VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "',
                             '" . gdrcd_capital_letter($beneficiario) . "', NOW(),
                             '" . gdrcd_filter('in', $_SESSION['login'] . ' ' . $MESSAGE['interface']['bank']['notice'] . ' ' . $amount_in . ' ' . $PARAMETERS['names']['currency']['plur']) . ".\n\n" . $causale_in . "')");
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['bank']['done'])];
        $banca -= $amount;
    }
} elseif ($op === 'incassa' && $ultimo != $today) {
    gdrcd_query("UPDATE personaggio SET banca = banca + " . (int)$stipendio . ", ultimo_stipendio = NOW()
                 WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' AND ultimo_stipendio < NOW() LIMIT 1");
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['interface']['bank']['done'])];
    $banca += $stipendio;
    $ultimo = $today;
}

$currency = gdrcd_filter('out', $PARAMETERS['names']['currency']['plur']);
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18a2 2 0 012 2v8a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2zm4 8h2"/>
                </svg>
            </span>
            <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['page_name']) ?>
        </h2>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <!-- Saldi -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="gdrcd-card p-4">
            <div class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">
                <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['amount']) ?>
            </div>
            <div class="mt-1 text-2xl font-display text-gdrcd-accent tabular-nums">
                <?= $banca ?> <span class="text-sm text-gdrcd-text-soft"><?= $currency ?></span>
            </div>
        </div>
        <div class="gdrcd-card p-4">
            <div class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">
                <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['pocket']) ?>
            </div>
            <div class="mt-1 text-2xl font-display text-gdrcd-text tabular-nums">
                <?= $soldi ?> <span class="text-sm text-gdrcd-text-soft"><?= $currency ?></span>
            </div>
        </div>
        <div class="gdrcd-card p-4">
            <div class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">
                <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['per_day']) ?>
            </div>
            <div class="mt-1 text-2xl font-display text-gdrcd-text tabular-nums">
                <?= $stipendio ?> <span class="text-sm text-gdrcd-text-soft"><?= $currency ?></span>
            </div>
        </div>
    </div>

    <!-- Stipendio -->
    <?php if ($stipendio > 0): ?>
    <article class="gdrcd-card">
        <header class="gdrcd-card-header flex items-center justify-between">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['bank']['credit']) ?></h3>
            <span class="gdrcd-badge-accent tabular-nums"><?= $stipendio ?> <?= $currency ?></span>
        </header>
        <div class="gdrcd-card-body">
            <?php if ($ultimo >= $today): ?>
                <div class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['bank']['credit_no']) ?></div>
            <?php else: ?>
                <form action="main.php?page=servizi_banca" method="post" class="flex justify-end">
                    <?= gdrcd_csrf_field() ?>
                    <input type="hidden" name="ammontare" value="<?= $stipendio ?>">
                    <input type="hidden" name="op" value="incassa">
                    <button type="submit" class="gdrcd-btn-primary">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['pay']) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php endif; ?>

    <!-- Operazioni -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Deposito -->
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gdrcd-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['deposit']) ?>
                </h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=servizi_banca" method="post" class="space-y-3">
                    <?= gdrcd_csrf_field() ?>
                    <input type="number" name="ammontare" value="0" min="0" class="gdrcd-input w-full">
                    <input type="hidden" name="op" value="deposita">
                    <button type="submit" class="gdrcd-btn-primary w-full">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['execute']) ?>
                    </button>
                </form>
            </div>
        </article>

        <!-- Prelievo -->
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gdrcd-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['withdraw']) ?>
                </h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=servizi_banca" method="post" class="space-y-3">
                    <?= gdrcd_csrf_field() ?>
                    <input type="number" name="ammontare" value="0" min="0" class="gdrcd-input w-full">
                    <input type="hidden" name="op" value="preleva">
                    <button type="submit" class="gdrcd-btn-secondary w-full">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['execute']) ?>
                    </button>
                </form>
            </div>
        </article>
    </div>

    <!-- Bonifico -->
    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3 flex items-center gap-2">
                <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['payment']) ?>
            </h3>
        </header>
        <div class="gdrcd-card-body">
            <form action="main.php?page=servizi_banca" method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?= gdrcd_csrf_field() ?>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['bank']['payee']) ?></span>
                    <select name="beneficiario" class="gdrcd-select mt-1 w-full">
                        <option value=""></option>
                        <?php
                        $nomi = gdrcd_query("SELECT nome, cognome FROM personaggio WHERE permessi > -1 ORDER BY nome", 'result');
                        while ($o = gdrcd_query($nomi, 'fetch')): ?>
                            <option value="<?= htmlspecialchars($o['nome']) ?>">
                                <?= gdrcd_filter('out', $o['nome'] . ' ' . $o['cognome']) ?>
                            </option>
                        <?php endwhile; gdrcd_query($nomi, 'free'); ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft">Ammontare</span>
                    <input type="number" name="ammontare" min="0" value="0" class="gdrcd-input mt-1 w-full">
                </label>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= gdrcd_filter('out', $MESSAGE['interface']['bank']['cause']) ?></span>
                    <input type="text" name="causale" value="" class="gdrcd-input mt-1 w-full">
                </label>
                <div class="md:col-span-3 flex justify-end">
                    <input type="hidden" name="op" value="bonifico">
                    <button type="submit" class="gdrcd-btn-primary">
                        <?= gdrcd_filter('out', $MESSAGE['interface']['bank']['execute']) ?>
                    </button>
                </div>
            </form>
        </div>
    </article>
</div>
