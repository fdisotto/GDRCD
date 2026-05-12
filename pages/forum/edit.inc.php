<?php
/**
 * Handler: aggiorna un messaggio del forum (titolo + corpo).
 * Solo autore o moderatori. Redirect al topic dopo update.
 */

$id_msg = (int)gdrcd_filter('num', $_POST['id_messaggio'] ?? 0);
$row = Db::preparedFetch(
    "SELECT autore, titolo, messaggio, id_messaggio_padre
     FROM messaggioaraldo WHERE id_messaggio = ?",
    'i',
    array($id_msg)
);

$can_edit = $row && ($row['autore'] === $_SESSION['login'] || $_SESSION['permessi'] >= MODERATOR);

if ($can_edit) {
    $time = date('d/m/Y H:i');
    $newMsg   = (string)($_POST['messaggio'] ?? '') . "\n\n\n\nEdit (" . (string)$_SESSION['login'] . '): ' . $time;
    $newTitle = (string)($_POST['titolo'] ?? $row['titolo']);
    Db::preparedExecute(
        "UPDATE messaggioaraldo SET messaggio = ?, titolo = ? WHERE id_messaggio = ? LIMIT 1",
        'ssi',
        array($newMsg, $newTitle, $id_msg)
    );

    $padre = ((int)$row['id_messaggio_padre'] === -1) ? $id_msg : (int)$row['id_messaggio_padre'];
    gdrcd_redirect('main.php?page=forum&op=read&what=' . $padre . '&where=' . (int)gdrcd_filter('num', $_POST['araldo'] ?? 0));
} else {
    ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div>Permesso negato.</div>
    </div>
    <?php
}
