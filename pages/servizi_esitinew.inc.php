<?php
/**
 * Servizi — Nuovo blocco/esito (dispatcher esiti_pg/*).
 */
switch (gdrcd_filter_get($_POST['op'] ?? '')) {
    case 'insert':
        include 'esiti_pg/insert.php';
        return;
    case 'add':
        include 'esiti_pg/add.php';
        return;
}
switch (gdrcd_filter_get($_GET['op'] ?? '')) {
    case 'new':
        include 'esiti_pg/new.php';
        return;
    case 'first':
        include 'esiti_pg/first.php';
        return;
}
