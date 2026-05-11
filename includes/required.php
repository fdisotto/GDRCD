<?php
session_start();

require_once(dirname(__FILE__) . '/constant_values.inc.php');
require_once(dirname(__FILE__) . '/../config.inc.php');
if(file_exists(dirname(__FILE__).'/config-overrides.php')){
    include_once dirname(__FILE__).'/config-overrides.php';
}

/*
 * Composer autoloader (additive).
 * Se presente, abilita il caricamento PSR-4 delle classi sotto il namespace
 * GDRCD\ (vedi composer.json: "GDRCD\\": "src/"). I require_once legacy
 * qui sotto restano in piedi e continuano a definire le classi nel namespace
 * globale per retrocompatibilita' con i call site esistenti.
 */
$gdrcdAutoloader = dirname(__FILE__) . '/../vendor/autoload.php';
if (file_exists($gdrcdAutoloader)) {
    require_once $gdrcdAutoloader;
}
unset($gdrcdAutoloader);

require_once dirname(__FILE__) . '/DbMigration/DbMigrationEngine.class.php';
require_once dirname(__FILE__) . '/DbMigration/DbMigration.class.php';

require_once(dirname(__FILE__) . '/../vocabulary/' . $PARAMETERS['languages']['set'] . '.vocabulary.php');
require_once(dirname(__FILE__) . '/functions.inc.php');
require_once(dirname(__FILE__) . '/Db.class.php');
require_once(dirname(__FILE__) . '/logger.inc.php');
require_once(dirname(__FILE__) . '/csrf.inc.php');
require_once(dirname(__FILE__) . '/icons.inc.php');
require_once(dirname(__FILE__) . '/uploads.inc.php');

/** Gestione dei Suoni */
require_once(dirname(__FILE__) . '/AudioController.class.php');

if(!empty($_SESSION['theme']) and array_key_exists($_SESSION['theme'], $PARAMETERS['themes']['available'])){
    $PARAMETERS['themes']['current_theme'] = $_SESSION['theme'];
}
