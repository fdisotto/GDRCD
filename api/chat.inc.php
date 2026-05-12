<?php
declare(strict_types=1);

/**
 * Endpoint JSON: messaggi chat della stanza corrente.
 *
 * GET ?after=<lastId> (default 0) — restituisce i messaggi chat successivi
 * a lastId nella stanza ($_SESSION['luogo']), rispettando ora_prenotazione
 * della mappa e la finestra di 30 minuti come la chat iframe.
 *
 * Mirror lato dati del SELECT in ref_header.inc.php. I campi sono raw
 * (nessun escape HTML): il client è responsabile dell'escape quando rende
 * il messaggio.
 *
 * Richiede sessione valida e $_SESSION['luogo'] impostato.
 *
 * @see ref_header.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$handleDBConnection = gdrcd_connect();

$auth = gdrcd_api_authenticate();
if ($auth === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Luogo: per sessioni web arriva da $_SESSION; per i client JWT può essere
// passato esplicitamente come query string ?luogo=<id>. Senza luogo non
// abbiamo una "stanza" da pollare e restituiamo lista vuota.
$luogo_src = $_SESSION['luogo'] ?? ($_GET['luogo'] ?? '');
if ($luogo_src === '' || !is_numeric($luogo_src)) {
    echo json_encode([
        'messages' => [],
        'last_id'  => (int)($_REQUEST['after'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    exit;
}

$after  = isset($_GET['after']) ? (int)$_GET['after'] : 0;
if ($after < 0) {
    $after = 0;
}
$luogo  = (int)$luogo_src;

$query = gdrcd_query(
    "SELECT chat.id, chat.imgs, chat.mittente, chat.destinatario, chat.tipo,
            chat.ora, chat.testo, personaggio.url_img_chat
     FROM chat
     INNER JOIN mappa ON mappa.id = chat.stanza
     LEFT JOIN personaggio ON personaggio.nome = chat.mittente
     WHERE chat.id > " . $after . "
       AND stanza = " . $luogo . "
       AND chat.ora > IFNULL(mappa.ora_prenotazione, '0000-00-00 00:00:00')
       AND DATE_SUB(NOW(), INTERVAL 30 MINUTE) < chat.ora
     ORDER BY chat.id ASC",
    'result'
);

$messages = [];
$last_id  = $after;
while ($row = gdrcd_query($query, 'fetch')) {
    $id = (int)$row['id'];
    $messages[] = [
        'id'           => $id,
        'tipo'         => (string)$row['tipo'],
        'mittente'     => (string)$row['mittente'],
        'destinatario' => empty($row['destinatario']) ? null : (string)$row['destinatario'],
        'ora'          => (string)$row['ora'],
        'testo'        => (string)$row['testo'],
        'imgs'         => (string)($row['imgs'] ?? ''),
        // url_img_chat lo passiamo via filtro fullurl come fa la chat iframe,
        // così evitiamo URL malformati lato client.
        'url_img_chat' => !empty($row['url_img_chat'])
            ? gdrcd_filter('fullurl', $row['url_img_chat'])
            : null,
    ];
    if ($id > $last_id) {
        $last_id = $id;
    }
}
gdrcd_query($query, 'free');

echo json_encode([
    'messages' => $messages,
    'last_id'  => $last_id,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
