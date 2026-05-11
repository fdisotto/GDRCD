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

/* Registro per inlining immagini: ogni risorsa locale viene letta una sola volta
   e referenziata nel markup tramite classe CSS, evitando di duplicare i base64. */
$__img_registry  = new GdrcdChatlogImageRegistry();
$__current_theme = isset($PARAMETERS['themes']['current_theme']) ? $PARAMETERS['themes']['current_theme'] : '';

/* Token segnaposto: il CSS finale delle immagini è generato a fine loop,
   quando tutte le risorse sono state registrate. */
$__IMG_CSS_PLACEHOLDER = '/*__GDRCD_IMG_CSS__*/';

/*Inizio a preparare il testo da inserire poi nel file da salvare.*/
$add_chat = '<!DOCTYPE html>
<html xml:lang="it" lang="it">
<head>
<meta charset="utf-8" />
<title>Log chat &mdash; ' . htmlspecialchars($PARAMETERS['info']['site_name']) . '</title>
<style>
' . gdrcd_chatlog_inline_css() . '
' . $__IMG_CSS_PLACEHOLDER . '
</style>
</head>
<body>
<div class="wrap">
' . gdrcd_chatlog_header_html(
        $PARAMETERS['info']['site_name'],
        isset($_SESSION['login']) ? $_SESSION['login'] : '',
        date('d/m/Y H:i', strtotime('-240 minutes')),
        date('d/m/Y H:i')
    ) . '
<div class="chatlog-body">
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
        $icona_razza  = isset($icone_chat[1]) ? $icone_chat[1] : '';
        $icona_sesso  = isset($icone_chat[0]) ? $icone_chat[0] : '';

        /*Aggiunta per rendere utilizzabile la chat anche in mancanza dell'installazione della patch Icone Chat
        * Save Chat HTML 1.3
        *@author eLDiabolo
        */
        if (isset($PARAMETERS['settings']['chat']['guilds'])) {

            if ($PARAMETERS['settings']['chat']['race'] == 'ON' && $icona_razza !== '') {
                $add_icon .= $__img_registry->iconTag(
                    'themes/' . $__current_theme . '/imgs/races/' . $icona_razza
                );
            }
            if ($PARAMETERS['settings']['chat']['gender'] == 'ON' && $icona_sesso !== '') {
                $add_icon .= $__img_registry->iconTag(
                    'imgs/icons/testamini' . $icona_sesso . '.png'
                );
            }
            if ($PARAMETERS['settings']['chat']['guilds'] == 'ON') {

                $query_ruoli = "SELECT 	clgpersonaggioruolo.id_ruolo,	ruolo.nome_ruolo,	ruolo.immagine FROM clgpersonaggioruolo INNER JOIN ruolo ON ruolo.id_ruolo = clgpersonaggioruolo.id_ruolo WHERE clgpersonaggioruolo.personaggio='" . $row['mittente'] . "'";
                $result_ruoli = gdrcd_query($query_ruoli, 'result');
                $gilde = 0;

                if (gdrcd_query($result_ruoli, 'num_rows') > 0) {
                    while ($ruoli = gdrcd_query($result_ruoli, 'fetch')) {
                        $gilde++;
                        $add_icon .= $__img_registry->iconTag(
                            'themes/' . $__current_theme . '/imgs/guilds/' . $ruoli['immagine'],
                            'presenti_ico',
                            gdrcd_filter('out', $ruoli['nome_ruolo'])
                        );
                    }
                }

                for ($k = $PARAMETERS['settings']['guilds_limit']; $k > $gilde; $k--) {
                    $add_icon .= $__img_registry->iconTag('imgs/icons/guilds/null.png');
                }
            }
        } else {
            /*Aggiunta per rendere utilizzabile la chat anche in mancanza dell'installazione della patch Icone Chat
            * Save Chat HTML 1.3
            *@author eLDiabolo
            */
            if ($icona_razza !== '') {
                $add_icon .= $__img_registry->iconTag(
                    'themes/' . $__current_theme . '/imgs/races/' . $icona_razza
                );
            }
            if ($icona_sesso !== '') {
                $add_icon .= $__img_registry->iconTag(
                    'imgs/icons/testamini' . $icona_sesso . '.png'
                );
            }
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
                $add_chat .= $__img_registry->avatarTag($row['url_img_chat']);
            }


            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';

            if ($PARAMETERS['mode']['chaticons'] == 'ON') {
                $add_chat .= $add_icon;
            }

            $mittente_html = gdrcd_filter('out', $row['mittente']);
            $add_chat .= '<span class="chat_name"><a href="#">' . $mittente_html . '</a>';

            if (empty ($row['destinatario']) === false) {
                $add_chat .= '<span class="chat_tag"> [' . gdrcd_filter('out', $row['destinatario']) . ']</span>';
            }

            $add_chat .= ': </span> ';
            $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span>';

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
                $add_chat .= $__img_registry->avatarTag($row['url_img_chat']);
            }


            $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';

            if ($PARAMETERS['mode']['chaticons'] == 'ON') {
                $add_chat .= $add_icon;
            }

            $mittente_html = gdrcd_filter('out', $row['mittente']);
            $add_chat .= '<span class="chat_name"><a href="#">' . $mittente_html . '</a>';

            if (empty ($row['destinatario']) === false) {
                $add_chat .= '<span class="chat_tag"> [' . gdrcd_filter('out', $row['destinatario']) . ']</span>';
            }
            $add_chat .= '</span> ';
            $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span>';

            $add_chat .= '</div>';

            break;


        case 'S':
            if ($_SESSION['login'] == $row['destinatario']) {
                /**    * Fix problema visualizzazione spazi vuoti con i sussurri
                 * @author eLDiabolo
                 */
                $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';

                $add_chat .= '<span class="chat_name">' . gdrcd_filter('out', $row['mittente']) . ' ' . $MESSAGE['chat']['whisper']['by'] . ': </span> ';
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

                        $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['mittente']) . ' ' . $MESSAGE['chat']['whisper']['from_to'] . ' ' . gdrcd_filter('out',
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
            $add_chat .= '<span class="chat_name">' . gdrcd_filter('out', $row['destinatario']) . '</span> ';
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

            /* Le immagini inviate in chat sono URL utente-forniti (spesso esterni):
               non vengono inlinate per non aumentare a dismisura il file generato
               e perché non sempre raggiungibili lato server. Resta il link. */
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
<div class="chatlog-footer">' . htmlspecialchars($PARAMETERS['info']['site_name']) . ' &middot; log autonomo (offline-ready)</div>
</div>
</body>
</html>
';

/* Sostituisce il segnaposto con le regole CSS background-image generate per
   le immagini incorporate (ogni risorsa appare UNA volta nel file). */
$add_chat = str_replace($__IMG_CSS_PLACEHOLDER, $__img_registry->renderStyle(), $add_chat);

/* Nome file leggibile: chat-<personaggio>-<YYYYMMDD-HHMMSS>.html */
$file = gdrcd_chatlog_filename(isset($_SESSION['login']) ? $_SESSION['login'] : '');

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
    fwrite($fp, $message, strlen($message));
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
