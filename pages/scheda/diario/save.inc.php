<?php
/**
 * Diario PG — handler save_new / save_edit / delete.
 */

switch ($_POST['op'] ?? '') {
    case 'save_new':
        Db::preparedExecute(
            "INSERT INTO diario (titolo, data, data_inserimento, visibile, testo, personaggio)
             VALUES (?, ?, NOW(), ?, ?, ?)",
            'sssss',
            [
                $_POST['titolo'] ?? '',
                $_POST['data'] ?? '',
                $_POST['visibile'] ?? 'no',
                $_POST['testo'] ?? '',
                $_POST['pg'] ?? '',
            ]
        );
        $msg = 'Pagina creata.';
        gdrcd_toast('success', $msg);
        break;

    case 'save_edit':
        Db::preparedExecute(
            "UPDATE diario SET
                titolo = ?, data = ?, visibile = ?, testo = ?, data_modifica = NOW()
             WHERE id = ? LIMIT 1",
            'ssssi',
            [
                $_POST['titolo'] ?? '',
                $_POST['data'] ?? '',
                $_POST['visibile'] ?? 'no',
                $_POST['testo'] ?? '',
                (int)gdrcd_filter('num', $_POST['id'] ?? 0),
            ]
        );
        $msg = 'Modifiche salvate.';
        gdrcd_toast('success', $msg);
        break;

    case 'delete':
        Db::preparedExecute(
            "DELETE FROM diario WHERE id = ?",
            'i',
            [(int)gdrcd_filter('num', $_POST['id'] ?? 0)]
        );
        $msg = 'Pagina eliminata.';
        gdrcd_toast('success', $msg);
        break;

    default:
        gdrcd_toast('error', 'Operazione non riconosciuta.');
        echo '<div class="gdrcd-alert-error">Operazione non riconosciuta.</div>';
        return;
}
?>

<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div><?= htmlspecialchars($msg) ?></div>
</div>

<div>
    <a href="main.php?page=scheda_diario&pg=<?= urlencode($_REQUEST['pg']) ?>" class="gdrcd-btn-ghost">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Torna al diario
    </a>
</div>
