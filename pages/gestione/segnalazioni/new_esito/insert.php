<?php
/**
 * Handler POST: inserimento di un nuovo blocco esiti + primo esito.
 * Innescato dal form first.php con op=insert.
 */

if (($_POST['op'] ?? '') !== 'insert') {
    return;
}

$note = (!isset($_POST['note']) || $_POST['note'] === '')
    ? 'Nessuna'
    : gdrcd_filter('in', $_POST['note']);

$same_pg = ($_POST['pg'] === $_SESSION['login']);

if (!$same_pg) {
    gdrcd_query(
        "INSERT INTO blocco_esiti (titolo, pg, autore, master) VALUES ("
        . "'" . gdrcd_filter('in', $_POST['titolo']) . "',"
        . "'" . gdrcd_filter('in', $_POST['pg']) . "',"
        . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
        . "'" . gdrcd_filter('in', $_SESSION['login']) . "')"
    );
} else {
    gdrcd_query(
        "INSERT INTO blocco_esiti (titolo, pg, autore) VALUES ("
        . "'" . gdrcd_filter('in', $_POST['titolo']) . "',"
        . "'" . gdrcd_filter('in', $_POST['pg']) . "',"
        . "'" . gdrcd_filter('in', $_SESSION['login']) . "')"
    );
}

$load_blocco = gdrcd_query(
    "SELECT id, master FROM blocco_esiti
     WHERE titolo = '" . gdrcd_filter('in', $_POST['titolo']) . "'
     ORDER BY id DESC LIMIT 1"
);

gdrcd_query(
    "INSERT INTO esiti (titolo, pg, autore, contenuto, noteoff, master, id_blocco, letto_master) VALUES ("
    . "'" . gdrcd_filter('in', $_POST['titolo']) . "',"
    . "'" . gdrcd_filter('in', $_POST['pg']) . "',"
    . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
    . "'" . gdrcd_filter('in', $_POST['contenuto']) . "',"
    . "'" . $note . "',"
    . "'" . gdrcd_filter('in', $load_blocco['master'] ?? '') . "',"
    . (int)$load_blocco['id'] . ", 1)"
);

if (!$same_pg) {
    // Avviso al giocatore disabilitato nell'originale.
    // $text = 'Hai ricevuto un nuovo esito dal Master ' . gdrcd_filter('out', $_SESSION['login']) .
    //         ' per la serie di esiti intitolata: "' . gdrcd_filter('out', $_POST['titolo']) . '"';
    // gdrcd_query("INSERT INTO messaggi (mittente, destinatario, spedito, testo) VALUES (...)");
}

$open_url = 'main.php?' . http_build_query([
    'page' => 'gestione_segnalazioni',
    'segn' => 'esiti_master',
]);
?>

<div class="gdrcd-shell">
    <div class="gdrcd-container-sm">
        <section class="gdrcd-card">
            <div class="gdrcd-card-body text-center space-y-4 py-8">
                <span class="gdrcd-icon-circle bg-gdrcd-success-soft text-gdrcd-success border-green-200">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $MESSAGE['warning']['inserted']) ?></h2>
                <p class="gdrcd-muted">
                    Serie <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['titolo']) ?></strong>
                    creata per <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['pg']) ?></strong>.
                </p>
                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-center pt-2">
                    <a href="<?= htmlspecialchars($open_url) ?>" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        Torna alla lista
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>
