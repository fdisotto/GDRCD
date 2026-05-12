<?php
/**
 * Handler POST: invia un messaggio (privato, ai presenti o broadcast).
 */

$lbl = $MESSAGE['interface']['messages'];
$me  = (string)$_SESSION['login'];

$opRequest    = gdrcd_filter('get', $_POST['multipli'] ?? '');
$tipo_val     = (string)($_POST['tipo']     ?? '');
$oggetto_val  = (string)($_POST['oggetto']  ?? '');
$testo_val    = (string)($_POST['testo']    ?? '');

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
            Db::preparedExecute(
                "INSERT INTO messaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES (?, ?, NOW(), ?, ?, ?)",
                'sssss',
                array($me, (string)$row['nome'], $tipo_val, $oggetto_val, $testo_val)
            );
        }
        $out .= $success_alert(gdrcd_filter('out', $PARAMETERS['names']['private_message']['sing'] . $lbl['sent']));
        break;

    case 'broadcast':
        if ($_SESSION['permessi'] >= MODERATOR) {
            $query = gdrcd_query("SELECT nome FROM personaggio", 'result');
            while ($row = gdrcd_query($query, 'fetch')) {
                Db::preparedExecute(
                    "INSERT INTO messaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES (?, ?, NOW(), ?, ?, ?)",
                    'sssss',
                    array($me, (string)$row['nome'], $tipo_val, $oggetto_val, $testo_val)
                );
            }
            $out .= $success_alert(gdrcd_filter('out', $PARAMETERS['names']['private_message']['sing'] . $lbl['sent']));
        }
        break;

    default:
        $destinatari = array_map('trim', explode(',', $_POST['destinatario'] ?? ''));
        $destinatari = array_filter($destinatari, fn($v) => $v !== '');
        $destinatari = array_values($destinatari);
        $num_dest    = count($destinatari);

        $sended = 0;
        $validRecipients = array();
        if ($num_dest > 0) {
            $placeholders = implode(',', array_fill(0, $num_dest, '?'));
            $types        = str_repeat('s', $num_dest);
            $rows         = Db::preparedFetchAll(
                "SELECT nome FROM personaggio
                 WHERE nome IN (" . $placeholders . ")
                   AND nome IS NOT NULL
                 GROUP BY nome",
                $types,
                $destinatari
            );
            foreach ($rows as $r) {
                $validRecipients[] = (string)$r['nome'];
            }
            $sended = count($validRecipients);
        }
        $not_all_sended = ($num_dest > $sended);

        if ($sended > 0) {
            if (!empty($_POST['url'])) {
                $testo_val = $me . ' ti ha segnalato questo [url=' . (string)$_POST['url'] . ']link[/url].';
            }

            foreach ($validRecipients as $destNome) {
                Db::preparedExecute(
                    "INSERT INTO messaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES (?, ?, NOW(), ?, ?, ?)",
                    'sssss',
                    array($me, $destNome, $tipo_val, $oggetto_val, $testo_val)
                );
                Db::preparedExecute(
                    "INSERT INTO backmessaggi (mittente, destinatario, spedito, tipo, oggetto, testo) VALUES (?, ?, NOW(), ?, ?, ?)",
                    'sssss',
                    array($me, $destNome, $tipo_val, $oggetto_val, $testo_val)
                );
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
