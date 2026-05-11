<?php
/**
 * Centro messaggi (main.php?page=messages_center)
 * Dispatcher delle sotto-azioni: lista, lettura, composizione, eliminazione, risposta.
 */

$post_op = gdrcd_filter_get($_POST['op'] ?? '');
$get_op  = gdrcd_filter_get($_GET['op']  ?? '');

$post_actions = [
    'erase'         => 'erase.inc.php',
    'erase_checked' => 'erase_checked.inc.php',
    'eraseall'      => 'eraseall.inc.php',
    'send_message'  => 'send_message.inc.php',
    'attach'        => 'reply.inc.php',
    'send'          => 'reply.inc.php',
    'reply'         => 'reply.inc.php',
];

if (isset($post_actions[$post_op])) {
    include __DIR__ . '/messages/' . $post_actions[$post_op];
}

if ($get_op === 'read') {
    include __DIR__ . '/messages/read.inc.php';
} elseif ($get_op === 'create') {
    include __DIR__ . '/messages/create.inc.php';
} else {
    include __DIR__ . '/messages/index.inc.php';
}
