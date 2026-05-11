<?php
/**
 * Dispatcher per le operazioni sui blocchi esiti.
 * Tutte le sotto-azioni vivono in new_esito/<op>.php
 */

if (!($_SESSION['permessi'] >= ESITI_PERM && ESITI)) {
    echo '<div class="gdrcd-alert-warning">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>Non hai i permessi per visualizzare questa sezione.</div>'
       . '</div>';
    return;
}

$post_op = gdrcd_filter_get($_POST['op'] ?? '');
$get_op  = gdrcd_filter_get($_GET['op']  ?? '');

$post_actions = [
    'modify' => 'modify.php',
    'insert' => 'insert.php',
    'add'    => 'add.php',
];
$get_actions = [
    'new'     => 'new.php',
    'edit'    => 'edit.php',
    'newchat' => 'new_chat.php',
    'first'   => 'first.php',
];

if (isset($post_actions[$post_op])) {
    include __DIR__ . '/new_esito/' . $post_actions[$post_op];
}

if (isset($get_actions[$get_op])) {
    include __DIR__ . '/new_esito/' . $get_actions[$get_op];
}
