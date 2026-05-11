<?php
/**
 * Handler: marca come letti tutti i topic del forum per l'utente corrente.
 */

$me_in = gdrcd_filter('in', $_SESSION['login']);
$result = gdrcd_query(
    "SELECT id_messaggio, id_araldo FROM messaggioaraldo WHERE id_messaggio_padre = -1",
    'result'
);

while ($row = gdrcd_query($result, 'fetch')) {
    $esiste = gdrcd_query(
        "SELECT id FROM araldo_letto WHERE thread_id = " . (int)$row['id_messaggio'] .
        " AND nome = '" . $me_in . "'"
    );
    if ((int)($esiste['id'] ?? 0) <= 0) {
        gdrcd_query(
            "INSERT INTO araldo_letto (nome, araldo_id, thread_id) VALUES ("
            . "'" . $me_in . "',"
            . (int)$row['id_araldo'] . ","
            . (int)$row['id_messaggio'] . ")"
        );
    }
}
gdrcd_query($result, 'free');
?>
<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div>Tutte le bacheche segnate come lette.</div>
</div>
