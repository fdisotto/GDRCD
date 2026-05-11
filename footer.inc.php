<script type="text/javascript" src="/includes/corefunctions.js"></script>
<!--<script type="text/javascript" src="includes/gdrcdskills.js"></script>-->
<script type="text/javascript" src="/includes/modal.js"></script>
<script type="text/javascript" src="/includes/toast.js"></script>
<script type="text/javascript" src="/includes/theme-toggle.js" defer></script>
<?php
/**
 * Notifiche desktop (PM + segnalazioni GM).
 * Caricate solo per utenti autenticati: l'endpoint /api/notifications.inc.php
 * comunque rifiuta sessioni non valide, ma evitiamo polling inutili dalla
 * login/installer.
 * @see includes/notifications.js
 */
if (!empty($_SESSION['login'])) { ?>
    <script type="text/javascript" src="/includes/notifications.js" defer></script>
    <script type="text/javascript" src="/includes/presenti.js" defer></script>
<?php } ?>
<?php
/** * Abilitazione tooltip
 * @author Blancks
 */
if($PARAMETERS['mode']['map_tooltip'] == 'ON' || $PARAMETERS['mode']['user_online_state'] == 'ON') { ?>
    <script type="text/javascript">
        var tooltip_offsetX = <?php echo $PARAMETERS['settings']['map_tooltip']['offset_x']; ?>;
        var tooltip_offsetY = <?php echo $PARAMETERS['settings']['map_tooltip']['offset_y']; ?>;
    </script>
    <script type="text/javascript" src="/includes/tooltip.js"></script>
    <?php
}
/** * Caricamento script per il titolo "lampeggiante" per i nuovi pm
 * @author Blancks
 */
if($PARAMETERS['mode']['alert_pm_via_pagetitle'] == 'ON') {
    echo '<script type="text/javascript" src="/includes/changetitle.js"></script>';

}
?>

<!--<script type="text/javascript">
    setTimeout("self.location.href.reload();",<?php //echo (int) $_GET['ref'] * 1000; ?>);
</script-->
<?= gdrcd_flash_toasts() ?>
</body>
</html>
<?php

/*Chiudo la connessione al database, se presente*/
if(isset($handleDBConnection)) {
    // Chiudo la connessione al database
    gdrcd_close_connection($handleDBConnection);
    // Libero la memoria occupata dalla variabile
    unset($handleDBConnection);
}

/**    * Per ottimizzare le risorse impiegate le liberiamo dopo che non ne abbiamo pi� bisogno
 * @author Blancks
 */
unset($MESSAGE);
unset($PARAMETERS);
?>
