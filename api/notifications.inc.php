<?php
/**
 * Endpoint JSON per le notifiche desktop (PM e segnalazioni GM).
 *
 * Risponde con:
 * {
 *   "unread_pm": int,
 *   "unread_segnalazioni": int,
 *   "latest_pm": { "id": int, "from": "...", "subject": "..." } | null
 * }
 *
 * Richiede sessione valida. Risponde 401 se l'utente non è autenticato.
 *
 * Pensato per polling dal client (vedi includes/notifications.js).
 *
 * @see includes/notifications.js
 * @see pages/frame_messages.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

// Disabilita cache: ogni polling deve riflettere lo stato reale.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// --- Auth check ---------------------------------------------------------
if (empty($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

// Connetti al DB (lo fanno tutte le pagine via header.inc.php, ma qui siamo
// fuori dal flusso normale: gdrcd_query() richiede una connessione attiva).
$handleDBConnection = gdrcd_connect();

$me = gdrcd_filter('in', $_SESSION['login']);

// --- PM non letti -------------------------------------------------------
$unreadPm = 0;
$latestPm = null;

if (($PARAMETERS['mode']['check_messages'] ?? 'OFF') === 'ON') {
    $res = gdrcd_query(
        "SELECT COUNT(*) AS c FROM messaggi
         WHERE destinatario = '" . $me . "'
           AND destinatario_del = 0
           AND letto = 0"
    );
    $unreadPm = (int)($res['c'] ?? 0);

    if ($unreadPm > 0) {
        $row = gdrcd_query(
            "SELECT id, mittente, oggetto FROM messaggi
             WHERE destinatario = '" . $me . "'
               AND destinatario_del = 0
               AND letto = 0
             ORDER BY spedito DESC
             LIMIT 1"
        );
        if (!empty($row)) {
            $subject = (string)($row['oggetto'] ?? '');
            if (mb_strlen($subject) > 80) {
                $subject = mb_substr($subject, 0, 77) . '...';
            }
            $latestPm = [
                'id'      => (int)$row['id'],
                'from'    => (string)$row['mittente'],
                'subject' => $subject,
            ];
        }
    }
}

// --- Segnalazioni GM non lette -----------------------------------------
// Conta gli esiti non letti dal master quando l'utente ha i permessi GM
// (stessa logica di pages/gestione/segnalazioni/esiti_master.php).
$unreadSegnalazioni = 0;
$permessi = (int)($_SESSION['permessi'] ?? 0);

if (ESITI && $permessi >= ESITI_PERM) {
    if ($permessi >= FULL_PERM) {
        // Moderator: vede tutti i blocchi.
        $res = gdrcd_query(
            "SELECT COUNT(*) AS c FROM esiti
             WHERE letto_master = 0
               AND autore <> '" . $me . "'"
        );
    } else {
        // GM: vede solo i blocchi liberi (master = 0) o di cui è master.
        $res = gdrcd_query(
            "SELECT COUNT(e.id) AS c FROM esiti e
             INNER JOIN blocco_esiti b ON b.id = e.id_blocco
             WHERE e.letto_master = 0
               AND e.autore <> '" . $me . "'
               AND (b.master = '0' OR b.master = '" . $me . "')"
        );
    }
    $unreadSegnalazioni = (int)($res['c'] ?? 0);
}

echo json_encode([
    'unread_pm'           => $unreadPm,
    'unread_segnalazioni' => $unreadSegnalazioni,
    'latest_pm'           => $latestPm,
]);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
