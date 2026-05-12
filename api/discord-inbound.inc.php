<?php
/**
 * Endpoint inbound del Discord bridge.
 *
 *  URL: /api/discord-inbound.inc.php
 *  Metodo: POST
 *  Auth: header `X-Discord-Token: <token>` (vedi gestione/discord)
 *  Body JSON:
 *    {
 *      "author":  "<discord username>",   // required, max 64 chars
 *      "content": "<text>",               // required, max 1900 chars
 *      "type":    "O" | "M"               // optional, default "O"
 *    }
 *
 *  Risposte:
 *    200 { "ok": true,  "id": <inserted chat row id> }
 *    400 { "ok": false, "error": "bad_request", "detail": "..." }
 *    401 { "ok": false, "error": "unauthorized" }
 *    405 { "ok": false, "error": "method_not_allowed" }
 *    503 { "ok": false, "error": "disabled" }   // bridge OFF
 *
 *  Sicurezza:
 *   - L'autenticazione e' token-based (timing-safe compare).
 *   - L'endpoint NON usa la sessione PHP, ne' la richiede.
 *   - Il CSRF guard NON e' applicabile: chiamante esterno (bot Discord).
 *   - Tutti i campi vengono sanitizzati prima dell'INSERT.
 *
 * @see includes/discord.inc.php
 * @see pages/gestione/discord.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/**
 * Emette una risposta JSON con un dato HTTP code ed esce.
 */
$emit = function ($code, array $body) {
    http_response_code((int)$code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

// --- Method check ---------------------------------------------------------
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';
if ($method !== 'POST') {
    $emit(405, array('ok' => false, 'error' => 'method_not_allowed'));
}

// --- Config & auth --------------------------------------------------------
$cfg = gdrcd_discord_config();

if (empty($cfg['enabled'])) {
    $emit(503, array('ok' => false, 'error' => 'disabled'));
}
if ($cfg['incoming_token'] === '') {
    // Bridge enabled ma token mai generato: rifiutiamo tutto fail-closed.
    $emit(401, array('ok' => false, 'error' => 'unauthorized'));
}
if ((int)$cfg['bridge_room_id'] <= 0) {
    $emit(503, array('ok' => false, 'error' => 'bridge_room_not_configured'));
}

$presented_token = isset($_SERVER['HTTP_X_DISCORD_TOKEN']) ? (string)$_SERVER['HTTP_X_DISCORD_TOKEN'] : '';
if (!gdrcd_discord_token_equals($presented_token, $cfg['incoming_token'])) {
    if (function_exists('gdrcd_log_warning')) {
        gdrcd_log_warning('Discord inbound: invalid token', array(
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '',
        ));
    }
    $emit(401, array('ok' => false, 'error' => 'unauthorized'));
}

// --- Body parsing ---------------------------------------------------------
$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '') {
    $emit(400, array('ok' => false, 'error' => 'bad_request', 'detail' => 'empty_body'));
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    $emit(400, array('ok' => false, 'error' => 'bad_request', 'detail' => 'invalid_json'));
}

$author  = isset($data['author'])  ? trim((string)$data['author'])  : '';
$content = isset($data['content']) ? (string)$data['content']       : '';
$type_in = isset($data['type'])    ? strtoupper(trim((string)$data['type'])) : 'O';

if ($author === '') {
    $emit(400, array('ok' => false, 'error' => 'bad_request', 'detail' => 'missing_author'));
}
if ($content === '' || trim($content) === '') {
    $emit(400, array('ok' => false, 'error' => 'bad_request', 'detail' => 'missing_content'));
}

// Solo 'O' (other/sistema) o 'M' (master) ammessi inbound: i tipi P/A
// implicano una scheda PG, qui non c'e'.
if (!in_array($type_in, array('O', 'M'), true)) {
    $type_in = 'O';
}

// Limit lunghezze (DB lato: chat.testo TEXT, ma rendiamo il flusso "tracciabile").
if (mb_strlen($author) > 64) {
    $author = mb_substr($author, 0, 64);
}
$contentMax = 1900; // Discord limit reale e' 2000; teniamo margine per il prefisso.
if (mb_strlen($content) > $contentMax) {
    $content = mb_substr($content, 0, $contentMax) . "\u{2026}";
}

// --- Costruzione mittente -------------------------------------------------
// Mittente in chat = "[Discord] <author>", taggato per essere immediatamente
// distinguibile dai PG reali. Il bot_name di config rimane utilizzabile come
// alternativa in futuro (per ora non lo usiamo per i singoli messaggi).
$mittente = '[Discord] ' . $author;
if (mb_strlen($mittente) > 250) {
    $mittente = mb_substr($mittente, 0, 250);
}

// --- INSERT chat ----------------------------------------------------------
$handleDBConnection = gdrcd_connect();

$stanza   = (int)$cfg['bridge_room_id'];
$mittenteEsc = gdrcd_filter('in', $mittente);
$testoEsc    = gdrcd_filter('in', $content);
$tipoEsc     = gdrcd_filter('in', $type_in);

try {
    gdrcd_query(
        "INSERT INTO chat (stanza, imgs, mittente, destinatario, ora, tipo, testo) VALUES ("
        . $stanza . ", '', '" . $mittenteEsc . "', '', NOW(), '" . $tipoEsc . "', '" . $testoEsc . "')"
    );

    // Recupera l'ID inserito per restituirlo (utile a deduplicare lato bot).
    $idRow = gdrcd_query("SELECT LAST_INSERT_ID() AS id");
    $insertedId = is_array($idRow) && isset($idRow['id']) ? (int)$idRow['id'] : 0;
} catch (\Throwable $e) {
    if (function_exists('gdrcd_log_error')) {
        gdrcd_log_error('Discord inbound: insert failed', array('exception' => $e->getMessage()));
    }
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    $emit(500, array('ok' => false, 'error' => 'insert_failed'));
}

if (function_exists('gdrcd_log_info')) {
    gdrcd_log_info('Discord inbound: message accepted', array(
        'author'  => $author,
        'type'    => $type_in,
        'stanza'  => $stanza,
        'chat_id' => isset($insertedId) ? $insertedId : 0,
    ));
}

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}

$emit(200, array('ok' => true, 'id' => isset($insertedId) ? $insertedId : 0));
