<?php
#################################################################################
#                                                                               ##
#                Save Chat HTML 1.3 - Author eLDiabolo                          ##
#                                                                               ##
#      e-mail: http://www.gdr-online.com/email.asp?email=eldiabolo              ##
#                                                                               ##
##################################################################################
#################################################################################

session_start();

/* Includo i file necessari */
include('includes/constant_values.inc.php');
include('config.inc.php');
if (file_exists(__DIR__ . '/includes/config-overrides.php')) {
    include __DIR__ . '/includes/config-overrides.php';
}
include('vocabulary/' . $PARAMETERS['languages']['set'] . '.vocabulary.php');
include('includes/functions.inc.php');

/* Eseguo la connessione al database */
$handleDBConnection = gdrcd_connect();

$typeOrder = ($PARAMETERS['mode']['chat_from_bottom'] == 'ON') ? 'DESC' : 'ASC';

/*Query per caricamento dati dalla chat corrente, carica le azioni degli ultimi 240 min - 4 ore !! NON SALVA LE CHAT PRIVATE !!*/

if ($PARAMETERS['mode']['chatsavepvt'] == 'ON') {
    $query = gdrcd_query("	SELECT chat.id, chat.imgs, chat.mittente, chat.destinatario, chat.tipo, chat.ora, chat.testo, personaggio.url_img_chat, mappa.ora_prenotazione, mappa.privata
        FROM chat
        INNER JOIN mappa ON mappa.id = chat.stanza
        LEFT JOIN personaggio ON personaggio.nome = chat.mittente
        WHERE stanza = " . $_SESSION['luogo'] . " AND DATE_SUB(NOW(), INTERVAL 240 MINUTE) < ora ORDER BY id " . $typeOrder,
        'result');
} else {
    $query = gdrcd_query("	SELECT chat.id, chat.imgs, chat.mittente, chat.destinatario, chat.tipo, chat.ora, chat.testo, personaggio.url_img_chat, mappa.ora_prenotazione, mappa.privata
            FROM chat
            INNER JOIN mappa ON mappa.id = chat.stanza
            LEFT JOIN personaggio ON personaggio.nome = chat.mittente
            WHERE stanza = " . $_SESSION['luogo'] . " AND mappa.privata = 0 AND DATE_SUB(NOW(), INTERVAL 240 MINUTE) < ora AND chat.ora > IFNULL(mappa.ora_prenotazione, '0000-00-00 00:00:00') ORDER BY id " . $typeOrder,
        'result');
}
/*Inizio a preparare il testo da inserire poi nel file da salvare.*/
$add_chat = '<!DOCTYPE html>
<html xml:lang="it" lang="it">
<head>
<meta charset="utf-8" />
<title>Log chat — ' . htmlspecialchars($PARAMETERS['info']['site_name']) . '</title>
<style>
    :root {
        --bg:#f8f7f4; --panel:#fff; --border:#e5e0d4;
        --text:#1f2937; --soft:#374151; --muted:#6b7280; --subtle:#9ca3af;
        --accent:#a47e3b; --accent-soft:#f3ead4;
    }
    *{box-sizing:border-box}
    body{margin:0;padding:24px;background:var(--bg);color:var(--text);
         font-family:Inter,system-ui,sans-serif;line-height:1.5;font-size:14px}
    .wrap{max-width:880px;margin:0 auto;background:var(--panel);border:1px solid var(--border);
          border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    h1{font-family:"Cinzel",serif;margin:0 0 16px;font-size:22px;font-weight:700}
    [class^="chat_row_"]{padding:8px 0;border-bottom:1px solid var(--border);
                        display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px}
    [class^="chat_row_"]:last-child{border-bottom:0}
    .chat_avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;
                 border:1px solid var(--border);flex-shrink:0;margin-right:4px}
    .chat_time{font-size:12px;color:var(--muted);font-variant-numeric:tabular-nums;flex-shrink:0;margin-top:2px}
    .chat_name{font-weight:600;color:var(--accent);flex-shrink:0}
    .chat_name a{color:var(--accent);text-decoration:none}
    .chat_tag{font-size:12px;color:var(--muted)}
    .chat_msg{color:var(--soft);flex:1;min-width:0;word-wrap:break-word}
    .chat_master{color:var(--accent);font-weight:600;font-style:italic}
    .chat_icons{display:inline-flex;align-items:center;gap:4px;flex-shrink:0}
    .presenti_ico{width:16px;height:16px;border-radius:2px}
    .chat_img{max-width:320px;border-radius:6px;border:1px solid var(--border)}
    .chat_row_A{font-style:italic}
    .chat_row_S{color:var(--muted);font-size:12px;font-style:italic}
    .chat_row_M{background:var(--accent-soft);padding:4px 12px;border-radius:6px}
    .chat_row_I{justify-content:center}
</style>
</head>
<body>
<div class="wrap">
<h1>Log chat</h1>
';


$i = 0;
/* Eseguo la query e le formattazioni */
while ($row = gdrcd_query($query, 'fetch')) {

    /** BEGIN "Icone di Chat by eLDiabolo"
     *
     * Modifica immagini di chat. Icone razza, genere e gilda.
     * Per farle apparire impostare i parametri relativi nel file config.inc.php
     * se impostato su On compaiono le icone di gilda, in automatico riempie gli spazi vuoti
     *    per chi non ha raggiunto il limite dei simboli possibili così da avere la chat più ordinata
     *
     * v 1.3
     * @author eLDiabolo
     */

    $add_icon = '';

    if ($PARAMETERS['mode']['chaticons'] == 'ON') {
        $add_icon .= '<span class="chat_icons">';

        $icone_chat = explode(";", gdrcd_filter('out', $row['imgs']));

        /*Aggiunta per rendere utilizzabile la chat anche in mancanza dell'installazione della patch Icone Chat
        * Save Chat HTML 1.3
        *@author eLDiabolo
        */
        if (isset($PARAMETERS['settings']['chat']['guilds'])) {

            if ($PARAMETERS['settings']['chat']['race'] == 'ON') {
                $add_icon .= '<img class="presenti_ico"
                 src="' . $PARAMETERS['info']['site_url'] . '/themes/' . $PARAMETERS['themes']['current_theme'] . '/imgs/icons/races/' . $icone_chat[1] . '">';
            }
            if ($PARAMETERS['settings']['chat']['gender'] == 'ON') {
                $add_icon .= '<img class="presenti_ico" src="' . $PARAMETERS['info']['site_url'] . '/imgs/icons/testamini' . $icone_chat[0] . '.png">';
            }
            if ($PARAMETERS['settings']['chat']['guilds'] == 'ON') {

                $query_ruoli = "SELECT 	clgpersonaggioruolo.id_ruolo,	ruolo.nome_ruolo,	ruolo.immagine FROM clgpersonaggioruolo INNER JOIN ruolo ON ruolo.id_ruolo = clgpersonaggioruolo.id_ruolo WHERE clgpersonaggioruolo.personaggio='" . $row['mittente'] . "'";
                $result_ruoli = gdrcd_query($query_ruoli, 'result');
                $gilde = 0;

                if (gdrcd_query($result_ruoli, 'num_rows') > 0) {
                    while ($ruoli = gdrcd_query($result_ruoli, 'fetch')) {
                        $gilde++;
                        $add_icon .= '<img class="presenti_ico" src="' . $PARAMETERS['info']['site_url'] . '/themes/' .
                            $PARAMETERS['themes']['current_theme'] . '/imgs/guilds/' . $ruoli['immagine'] . '" alt="' .
                            gdrcd_filter('out',
                                $record3['nome_ruolo']) . '" title="' . gdrcd_filter('out',
                                $ruoli['nome_ruolo']) . '" />';
                    }
                }

                for ($i = $PARAMETERS['settings']['guilds_limit']; $i > $gilde; $i--) {
                    $add_icon .= '<img class="presenti_ico" src="' . $PARAMETERS['info']['site_url'] . '/imgs/icons/guilds/null.png" alt="" title="" />';
                }
            }
        } else {
            /*Aggiunta per rendere utilizzabile la chat anche in mancanza dell'installazione della patch Icone Chat
            * Save Chat HTML 1.3
            *@author eLDiabolo
            */
            $add_icon .= '<img class="presenti_ico" src="' . $PARAMETERS['info']['site_url'] . '/themes/' . $PARAMETERS['themes']['current_theme'] . '/imgs/icons/races/' . $icone_chat[1] . '">';
            $add_icon .= '<img class="presenti_ico" src="' . $PARAMETERS['info']['site_url'] . '/imgs/icons/testamini' . $icone_chat[0] . '.png">';
        }

        /*Corretta la svista riportata nel pacchetto "Icone Chat v 1.1"
        *@author eLDiabolo
        */
        $add_icon .= '</span>';

    }

    /** END "Icone di Chat by eLDiabolo"
     *
     * @author eLDiabolo
     */

    switch ($row['tipo']) {
        case 'P':

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            /** * Avatar di chat
             * @author Blancks
             */
            if ($PARAMETERS['mode']['chat_avatar'] == 'ON' && !empty($row['url_img_chat'])) {
                $add_chat .= '<img src="' . $row['url_img_chat'] . '" class="chat_avatar" alt="" />';
            }


            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';

            if ($PARAMETERS['mode']['chaticons'] == 'ON') {
                $add_chat .= $add_icon;
            }

            $add_chat .= '<span class="chat_name"><a href="#" onclick="Javascript: document.getElementById(\'tag\').value=\'' . $row['mittente'] . '\'; document.getElementById(\'type\')[2].selected = \'1\'; document.getElementById(\'message\').focus();">' . $row['mittente'] . '</a>';

            if (empty ($row['destinatario']) === false) {
                $add_chat .= '<span class="chat_tag"> [' . gdrcd_filter('out', $row['destinatario']) . ']</span>';
            }

            $add_chat .= ': </span> ';
            $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            if ($PARAMETERS['mode']['chat_avatar'] == 'ON') {
                $add_chat .= '<br style="clear:both;" />';
            }

            $add_chat .= '</div>';

            break;


        case 'A':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            /** * Avatar di chat
             * @author Blancks
             */
            if ($PARAMETERS['mode']['chat_avatar'] == 'ON' && !empty($row['url_img_chat'])) {
                $add_chat .= '<img src="' . $row['url_img_chat'] . '" class="chat_avatar" alt="" />';
            }


            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';

            if ($PARAMETERS['mode']['chaticons'] == 'ON') {
                $add_chat .= $add_icon;
            }

            $add_chat .= '<span class="chat_name"><a href="#" onclick="Javascript: document.getElementById(\'tag\').value=\'' . $row['mittente'] . '\';  document.getElementById(\'type\')[2].selected = \'1\'; document.getElementById(\'message\').focus();">' . $row['mittente'] . '</a>';

            if (empty ($row['destinatario']) === false) {
                $add_chat .= '<span class="chat_tag"> [' . gdrcd_filter('out', $row['destinatario']) . ']</span>';
            }
            $add_chat .= '</span> ';
            $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            if ($PARAMETERS['mode']['chat_avatar'] == 'ON') {
                $add_chat .= '<br style="clear:both;" />';
            }

            $add_chat .= '</div>';

            break;


        case 'S':
            if ($_SESSION['login'] == $row['destinatario']) {
                /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                 * @author eLDiabolo
                 */
                $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

                $add_chat .= '<span class="chat_name">' . $row['mittente'] . ' ' . $MESSAGE['chat']['whisper']['by'] . ': </span> ';
                $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span>';

                /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                 * @author eLDiabolo
                 */
                $add_chat .= '</div>';

            } else {
                if ($_SESSION['login'] == $row['mittente']) {
                    /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                     * @author eLDiabolo
                     */
                    $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

                    $add_chat .= '<span class="chat_msg">' . $MESSAGE['chat']['whisper']['to'] . ' ' . gdrcd_filter('out',
                            $row['destinatario']) . ': </span>';
                    $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span>';

                    /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                     * @author eLDiabolo
                     */
                    $add_chat .= '</div>';

                } else {
                    if (($_SESSION['permessi'] >= MODERATOR) && ($PARAMETERS['mode']['spyprivaterooms'] == 'ON')) {
                        /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                         * @author eLDiabolo
                         */
                        $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

                        $add_chat .= '<span class="chat_msg">' . $row['mittente'] . ' ' . $MESSAGE['chat']['whisper']['from_to'] . ' ' . gdrcd_filter('out',
                                $row['destinatario']) . ' </span>';
                        $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span>';

                        /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                         * @author eLDiabolo
                         */
                        $add_chat .= '</div>';

                    }
                }
            }
            break;


        case 'N':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
            $add_chat .= '<span class="chat_name">' . $row['destinatario'] . '</span> ';
            $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '</div>';
            break;


        case 'M':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            $add_chat .= '<span class="chat_master">' . gdrcd_filter('out', $row['testo']) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '</div>';
            break;


        case 'I':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            $add_chat .= '<img class="chat_img" src="' . gdrcd_filter('out', $row['testo']) . '" />';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '</div>';
            break;


        case 'C':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
            $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '</div>';
            break;


        case 'D':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
            $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '</div>';
            break;


        case 'O':
            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
            $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span>';

            /**    * Fix problema visualizzazione spazi vuoti con i sussurri
             * @author eLDiabolo
             */
            $add_chat .= '</div>';
            break;
    }
    $i++;
    $add_chat .= '#stop#';
}
$add_chat .= '
</div>
</body>
</html>
';
/* Scrivo tutto in un file di testo */
$start = gdrcd_format_datetime_cat($start_time);
$end = gdrcd_format_datetime_cat($end_time);
/* Scrivo tutto in un file di testo */
$file = $start . "-" . $end . "-" . $_SESSION['login'];
$rand = rand(1, 10000);
$file = md5($file . $rand);
$file = $file . ".html";

if ($PARAMETERS['mode']['chatsave_link'] == 'ON') {
    $file = "giocate/" . $file;
    $fp = fopen($file, 'wb');
    $message = str_replace("#stop#", "\r\n", $add_chat);
    fwrite($fp, $message, strlen($message));
    fclose($fp);
    echo '<a href="' . $file . '">Link giocata</a>';
}
if ($PARAMETERS['mode']['chatsave_download'] == 'ON') {
    $fp = fopen($file, "wb");
    $message = str_replace("#stop#", "\r\n", $add_chat);
    fwrite($fp, $message, 65536);
    fclose($fp);

    /* Do le informazioni di download */
    header("Content-Disposition: attachment; filename=" . urlencode($file));
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    header("Content-Description: File Transfer");
    header("Content-Length: " . filesize($file));

    /* Passo le info del file al browser */
    $fp = fopen($file, "r");
    while (!feof($fp)) {
        print fread($fp, 65536);
        flush();
    }
    fclose($fp);

    /* Elimino il file temporaneo */
    unlink($file);

    /* Chiudo la finestra aperta */
}
if ($PARAMETERS['mode']['chatsave_download'] == 'ON' && $PARAMETERS['mode']['chatsave_link'] == 'OFF') {
    ?>
    <script language="JavaScript1.2">
        self.close();
    </script>
<?php } ?>
