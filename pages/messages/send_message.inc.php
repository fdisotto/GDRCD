<?php
/**
 * Handler POST: invia un messaggio (privato, ai presenti o broadcast).
 */

$lbl = $MESSAGE['interface']['messages'];
$me  = gdrcd_filter('in', $_SESSION['login']);

$opRequest    = gdrcd_filter('get', $_POST['multipli'] ?? '');
$tipo_val     = gdrcd_filter('in',  $_POST['tipo']     ?? '');
$oggetto_val  = gdrcd_filter('in',  $_POST['oggetto']  ?? '');
$testo_val    = gdrcd_filter('in',  $_POST['testo']    ?? '');

$success_alert = function (string $msg) {
    return '<div class="gdrcd-alert-success">'
         . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
         . '<div>' . $msg . '</div></div>';
};
$warn_alert = function (string $msg) {
    return '<div class="gdrcd-alert-warning">'
         . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
         . '<div>' . $msg . '</div></div>';
};

$out = '<div class="space-y-3">';

switch ($opRequest) {
    case 'presenti':
        $result = gdrcd_query(
            "SELECT nome FROM personaggio
             WHERE ora_entrata > ora_uscita
               AND DATE_ADD(ultimo_refresh, INTERVAL 4 MINUTE) > NOW()",
            'result'
        );
        while ($row = gdrcd_query($result, 'fetch')) {
            gdrcd_query(
                "INSERT INTO messaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES ("
                . "'" . $me . "',"
                . "'" . gdrcd_filter('in', $row['nome']) . "',"
                . "NOW(), '" . $tipo_val . "', '" . $oggetto_val . "', '" . $testo_val . "')"
            );
        }
        $out .= $success_alert(gdrcd_filter('out', $PARAMETERS['names']['private_message']['sing'] . $lbl['sent']));
        break;

    case 'broadcast':
        if ($_SESSION['permessi'] >= MODERATOR) {
            $query = gdrcd_query("SELECT nome FROM personaggio", 'result');
            while ($row = gdrcd_query($query, 'fetch')) {
                gdrcd_query(
                    "INSERT INTO messaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES ("
                    . "'" . $me . "',"
                    . "'" . gdrcd_filter('in', $row['nome']) . "',"
                    . "NOW(), '" . $tipo_val . "', '" . $oggetto_val . "', '" . $testo_val . "')"
                );
            }
            $out .= $success_alert(gdrcd_filter('out', $PARAMETERS['names']['private_message']['sing'] . $lbl['sent']));
        }
        break;

    default:
        $destinatari = array_map('trim', explode(',', $_POST['destinatario'] ?? ''));
        $destinatari = array_filter($destinatari, fn($v) => $v !== '');
        $num_dest    = count($destinatari);

        $destinatariCheck = "'" . implode("','", array_map(fn($v) => gdrcd_filter('in', $v), $destinatari)) . "'";

        $result = gdrcd_query(
            "SELECT nome FROM personaggio
             WHERE nome IN (" . $destinatariCheck . ")
               AND nome IS NOT NULL
             GROUP BY nome",
            'result'
        );
        $sended = (int)gdrcd_query($result, 'num_rows');
        $not_all_sended = ($num_dest > $sended);

        if ($sended > 0) {
            if (!empty($_POST['url'])) {
                $testo_val = gdrcd_filter('in', $me . ' ti ha segnalato questo [url=' . $_POST['url'] . ']link[/url].');
            }

            $queryInsert = [];
            while ($record = gdrcd_query($result, 'fetch')) {
                $queryInsert[] = "('" . $me . "',"
                              . "'" . gdrcd_filter('in', $record['nome']) . "',"
                              . "NOW(), '" . $tipo_val . "', '" . $oggetto_val . "', '" . $testo_val . "')";
            }
            if ($queryInsert) {
                $values = implode(',', $queryInsert);
                gdrcd_query("INSERT INTO messaggi    (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES " . $values);
                gdrcd_query("INSERT INTO backmessaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES " . $values);
            }

            $out .= $success_alert(gdrcd_filter('out', $PARAMETERS['names']['private_message']['sing'] . $lbl['sent']));
        } else {
            $out .= $warn_alert('Non hai selezionato nessun destinatario valido.');
        }

        if ($not_all_sended && $num_dest > 0) {
            $out .= $warn_alert('Alcuni dei destinatari selezionati sono inesistenti.');
        }
        break;
}

$out .= '<div><a href="main.php?page=messages_center&offset=0" class="gdrcd-btn-ghost">'
     .  '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
     .  gdrcd_filter('out', $lbl['go_back']) . '</a></div>';

$out .= '</div>';
echo $out;
