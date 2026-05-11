<?php
/**
 * Forum (main.php?page=forum)
 * Dispatcher delle sotto-azioni del forum/araldo.
 */

$post_op = gdrcd_filter_get($_POST['op'] ?? '');
$get_op  = gdrcd_filter_get($_GET['op']  ?? '');

$post_actions = [
    'delete'  => 'delete.inc.php',
    'edit'    => 'edit.inc.php',
    'insert'  => 'insert.inc.php',
    'readall' => 'readall.inc.php',
];
$get_actions = [
    'composer'    => 'composer.inc.php',
    'delete_conf' => 'delete_conf.inc.php',
    'modifica'    => 'modifica.inc.php',
    'read'        => 'read.inc.php',
    'visit'       => 'visit.inc.php',
];

if (isset($post_actions[$post_op])) {
    include __DIR__ . '/' . $post_actions[$post_op];
}

if (isset($get_actions[$get_op])) {
    include __DIR__ . '/' . $get_actions[$get_op];
} else {
    include __DIR__ . '/list.inc.php';
}
