<?php
/**
 * Handler: marca come letti tutti i topic del forum per l'utente corrente.
 */

$me = (string)$_SESSION['login'];
$result = gdrcd_query(
    "SELECT id_messaggio, id_araldo FROM messaggioaraldo WHERE id_messaggio_padre = -1",
    'result'
);

while ($row = gdrcd_query($result, 'fetch')) {
    $esiste = Db::preparedFetch(
        "SELECT id FROM araldo_letto WHERE thread_id = ? AND nome = ?",
        'is',
        array((int)$row['id_messaggio'], $me)
    );
    if ((int)($esiste['id'] ?? 0) <= 0) {
        Db::preparedExecute(
            "INSERT INTO araldo_letto (nome, araldo_id, thread_id) VALUES (?, ?, ?)",
            'sii',
            array($me, (int)$row['id_araldo'], (int)$row['id_messaggio'])
        );
    }
}
gdrcd_query($result, 'free');
?>
<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div>Tutte le bacheche segnate come lette.</div>
</div>
