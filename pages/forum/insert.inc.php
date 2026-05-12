<?php
/**
 * Handler: inserisce un nuovo topic o un reply in un thread esistente.
 */

$padre  = gdrcd_filter('num', $_POST['padre']  ?? -1);
$araldo = gdrcd_filter('num', $_POST['araldo'] ?? 0);

if ((int)$padre === -1) {
    $cond   = ' araldo.id_araldo = ' . $araldo;
    $join   = '';
    $fields = '';
} else {
    $fields = ', MA.chiuso';
    $join   = ' INNER JOIN messaggioaraldo AS MA ON MA.id_araldo = araldo.id_araldo ';
    $cond   = ' MA.id_messaggio = ' . $padre . ' AND id_messaggio_padre = -1';
}

$thread = gdrcd_query(
    "SELECT araldo.id_araldo, araldo.tipo, araldo.proprietari" . $fields .
    " FROM araldo " . $join . " WHERE " . $cond,
    'result'
);

if (!gdrcd_query($thread, 'num_rows')) {
    ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['forums']['not_exists']) ?></div>
    </div>
    <?php
    return;
}

$araldoData = gdrcd_query($thread, 'fetch');

if (!gdrcd_controllo_permessi_forum($araldoData['tipo'], $araldoData['proprietari'])) {
    ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['not_allowed']) ?></div>
    </div>
    <?php
    return;
}

Db::preparedExecute(
    "INSERT INTO messaggioaraldo
        (id_messaggio_padre, id_araldo, titolo, messaggio, autore, data_messaggio, data_ultimo_messaggio)
     VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
    'iisss',
    array((int)$padre, (int)$araldoData['id_araldo'], (string)($_POST['titolo'] ?? ''), (string)($_POST['messaggio'] ?? ''), (string)$_SESSION['login'])
);

if ((int)$padre === -1) {
    $padre = Db::lastInsertId();
} else {
    Db::preparedExecute(
        "UPDATE messaggioaraldo SET data_ultimo_messaggio = NOW() WHERE id_messaggio = ?",
        'i',
        array((int)$padre)
    );
}

Db::preparedExecute(
    "DELETE FROM araldo_letto WHERE thread_id = ? AND nome != ?",
    'is',
    array((int)$padre, (string)$_SESSION['login'])
);

gdrcd_redirect('main.php?page=forum&op=read&what=' . (int)$padre . '&where=' . (int)$araldoData['id_araldo']);
