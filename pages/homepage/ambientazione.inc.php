<?php
/**
 * Homepage wrapper — pagine pubbliche di ambientazione/razze/regolamento.
 */
$strInnerPage = '';
if ($_REQUEST['page'] === 'user_ambientazione') {
    include 'pages/homepage/user_ambientazione.inc.php';
} elseif ($_REQUEST['page'] === 'user_razze') {
    include 'pages/homepage/user_razze.inc.php';
} else {
    include 'pages/homepage/user_regolamento.inc.php';
}
?>
