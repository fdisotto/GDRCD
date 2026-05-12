<?php
/**
 * Endpoint JSON: metriche per la dashboard amministratore.
 *
 *   /api/admin-metrics.inc.php
 *
 * Restituisce il payload usato da pages/gestione/dashboard.inc.php per
 * popolare i grafici Chart.js. Solo i moderatori (>= MODERATOR) possono
 * accedere a questo endpoint, gli altri ricevono 403.
 *
 * Struttura della risposta:
 * {
 *   "counts": {
 *     "total_pg":        int,
 *     "active_24h":      int,
 *     "new_week":        int,
 *     "msgs_7d":         int,
 *     "pending_reports": int
 *   },
 *   "presence_30d":   [{"day":"YYYY-MM-DD","count":int}, ...],
 *   "msgs_30d":       [{"day":"YYYY-MM-DD","count":int}, ...],
 *   "top_chats_30d":  [{"nome":"...","count":int}, ...],
 *   "top_pgs_30d":    [{"nome":"...","count":int}, ...]
 * }
 *
 * Le query aggregano lato DB: la pagina riceve dataset gia' pronti.
 *
 * @see pages/gestione/dashboard.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$handleDBConnection = gdrcd_connect();

// --- Auth check ---------------------------------------------------------
// Richiediamo identità valida (sessione PHP o JWT) e permessi >= MODERATOR.
// Stesso livello di accesso della pagina dashboard.inc.php.
$auth = gdrcd_api_authenticate();
if ($auth === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$permessi = (int)$auth['permessi'];
if ($permessi < MODERATOR) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// --- Card metriche ------------------------------------------------------

$row = gdrcd_query("SELECT COUNT(*) AS c FROM personaggio");
$total_pg = (int)($row['c'] ?? 0);

// "Attivi nelle ultime 24h": PG con ultimo_refresh negli ultimi 86400s.
$row = gdrcd_query(
    "SELECT COUNT(*) AS c FROM personaggio
     WHERE ultimo_refresh > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
$active_24h = (int)($row['c'] ?? 0);

$row = gdrcd_query(
    "SELECT COUNT(*) AS c FROM personaggio
     WHERE data_iscrizione > DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$new_week = (int)($row['c'] ?? 0);

$row = gdrcd_query(
    "SELECT COUNT(*) AS c FROM chat
     WHERE ora > DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$msgs_7d = (int)($row['c'] ?? 0);

// Segnalazioni pending: esiti non letti dai master, su blocchi non chiusi.
$pending_reports = 0;
$row = gdrcd_query(
    "SELECT COUNT(e.id) AS c
     FROM esiti e
     INNER JOIN blocco_esiti b ON b.id = e.id_blocco
     WHERE e.letto_master = 0
       AND b.closed = 0"
);
$pending_reports = (int)($row['c'] ?? 0);

// --- Serie temporali ----------------------------------------------------

/**
 * Esegue una query SELECT e ritorna tutte le righe come array assoc.
 * Lavora con gdrcd_query() che usa MYSQLI_BOTH: ci accediamo per chiave
 * stringa, ignorando i duplicati numerici.
 */
$fetch_all = function (string $sql): array {
    $res = gdrcd_query($sql, 'result');
    $out = [];
    while ($r = gdrcd_query($res, 'assoc')) {
        $out[] = $r;
    }
    gdrcd_query($res, 'free');
    return $out;
};

// Presenze giornaliere ultimi 30 giorni: distinct PG che hanno fatto
// almeno un refresh in quel giorno. La colonna ultimo_refresh viene
// aggiornata ad ogni hit autenticato del giocatore, quindi e' un buon
// proxy della presenza effettiva.
$presence_30d_raw = $fetch_all(
    "SELECT DATE(ultimo_refresh) AS d, COUNT(DISTINCT nome) AS c
     FROM personaggio
     WHERE ultimo_refresh > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY d
     ORDER BY d ASC"
);

$msgs_30d_raw = $fetch_all(
    "SELECT DATE(ora) AS d, COUNT(*) AS c
     FROM chat
     WHERE ora > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY d
     ORDER BY d ASC"
);

// Riempie i buchi: costruisce la serie continua degli ultimi 30 giorni
// inserendo zero dove la query non ha righe. Cosi' i grafici hanno tutte
// le date sull'asse X anche se la chat e' stata vuota.
$build_series = function (array $rows): array {
    $by_day = [];
    foreach ($rows as $r) {
        $by_day[(string)$r['d']] = (int)$r['c'];
    }
    $out = [];
    for ($i = 29; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $out[] = [
            'day'   => $day,
            'count' => $by_day[$day] ?? 0,
        ];
    }
    return $out;
};

$presence_30d = $build_series($presence_30d_raw);
$msgs_30d     = $build_series($msgs_30d_raw);

// Top 10 chat per attivita' ultimi 30 giorni.
$top_chats_30d_raw = $fetch_all(
    "SELECT mappa.nome AS nome, COUNT(chat.id) AS c
     FROM chat
     INNER JOIN mappa ON mappa.id = chat.stanza
     WHERE chat.ora > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY mappa.id
     ORDER BY c DESC
     LIMIT 10"
);
$top_chats_30d = [];
foreach ($top_chats_30d_raw as $r) {
    $top_chats_30d[] = [
        'nome'  => (string)($r['nome'] ?? ''),
        'count' => (int)$r['c'],
    ];
}

// Top 10 PG piu' attivi (messaggi inviati) ultimi 30 giorni.
// Escludiamo l'utente di sistema che spamma notifiche di ingresso/uscita.
$top_pgs_30d_raw = $fetch_all(
    "SELECT mittente AS nome, COUNT(*) AS c
     FROM chat
     WHERE ora > DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND mittente <> 'System message'
       AND mittente <> ''
     GROUP BY mittente
     ORDER BY c DESC
     LIMIT 10"
);
$top_pgs_30d = [];
foreach ($top_pgs_30d_raw as $r) {
    $top_pgs_30d[] = [
        'nome'  => (string)($r['nome'] ?? ''),
        'count' => (int)$r['c'],
    ];
}

// --- Output -------------------------------------------------------------

echo json_encode([
    'counts' => [
        'total_pg'        => $total_pg,
        'active_24h'      => $active_24h,
        'new_week'        => $new_week,
        'msgs_7d'         => $msgs_7d,
        'pending_reports' => $pending_reports,
    ],
    'presence_30d'  => $presence_30d,
    'msgs_30d'      => $msgs_30d,
    'top_chats_30d' => $top_chats_30d,
    'top_pgs_30d'   => $top_pgs_30d,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
