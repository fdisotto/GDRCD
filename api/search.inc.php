<?php
/**
 * Endpoint JSON per la ricerca globale dalla topbar.
 *
 * Cerca contemporaneamente nelle entità principali del gioco:
 *   - personaggi (non esiliati)
 *   - oggetti presenti sul mercato (solo quelli visibili agli utenti)
 *   - gilde visibili (visibile = 1)
 *   - capitoli di ambientazione (pubblici per utenti loggati)
 *   - articoli del regolamento (pubblici per utenti loggati)
 *
 * Risponde con:
 * {
 *   "query": "...",
 *   "results": {
 *     "pg":            [{"nome": "...", "cognome": "...", "url": "..."}],
 *     "oggetti":       [{"nome": "...", "url": "..."}],
 *     "gilde":         [{"nome": "...", "url": "..."}],
 *     "ambientazione": [{"titolo": "...", "capitolo": int, "url": "..."}],
 *     "regolamento":   [{"titolo": "...", "articolo": int, "url": "..."}]
 *   },
 *   "total": int
 * }
 *
 * Input: ?q=<stringa> (minimo 2 caratteri, altrimenti results vuoti).
 * Ogni sezione è limitata a 5 risultati.
 *
 * Richiede sessione valida. Risponde 401 se l'utente non è autenticato.
 *
 * @see includes/search.js
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// --- Auth check ---------------------------------------------------------
if (empty($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(
        ['error' => 'unauthenticated'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

$handleDBConnection = gdrcd_connect();

$qRaw = isset($_GET['q']) ? (string)$_GET['q'] : '';
$qTrim = trim($qRaw);

$emptyResponse = [
    'query'   => $qTrim,
    'results' => [
        'pg'            => [],
        'oggetti'       => [],
        'gilde'         => [],
        'ambientazione' => [],
        'regolamento'   => [],
    ],
    'total'   => 0,
];

// Minimo 2 caratteri (allineato col client per evitare query inutili al DB).
if (mb_strlen($qTrim) < 2) {
    echo json_encode($emptyResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
        unset($handleDBConnection);
    }
    exit;
}

// Filtra l'input per la concatenazione SQL (stessa convenzione delle altre
// pagine, vedi pages/servizi/anagrafe/index.inc.php). La conversione a
// prepared statement è un task separato.
$q   = gdrcd_filter('in', $qTrim);
$LIM = 5;

$results = [
    'pg'            => [],
    'oggetti'       => [],
    'gilde'         => [],
    'ambientazione' => [],
    'regolamento'   => [],
];

// --- Personaggi ---------------------------------------------------------
// Solo PG non esiliati (esilio <= oggi). Match su nome o cognome.
$resPg = gdrcd_query(
    "SELECT nome, cognome FROM personaggio
     WHERE (nome LIKE '%" . $q . "%' OR cognome LIKE '%" . $q . "%')
       AND esilio <= CURDATE()
     ORDER BY nome
     LIMIT " . $LIM,
    'result'
);
while ($row = gdrcd_query($resPg, 'fetch')) {
    $results['pg'][] = [
        'nome'    => (string)$row['nome'],
        'cognome' => (string)($row['cognome'] ?? ''),
        'url'     => 'main.php?page=scheda&pg=' . urlencode($row['nome']),
    ];
}
gdrcd_query($resPg, 'free');

// --- Oggetti sul mercato -----------------------------------------------
// JOIN su mercato per restituire solo oggetti effettivamente acquistabili
// (gli oggetti "nascosti" non presenti nel mercato non vengono mostrati).
$resOgg = gdrcd_query(
    "SELECT DISTINCT oggetto.id_oggetto, oggetto.nome, oggetto.tipo
     FROM oggetto
     JOIN mercato ON oggetto.id_oggetto = mercato.id_oggetto
     WHERE oggetto.nome LIKE '%" . $q . "%'
     ORDER BY oggetto.nome
     LIMIT " . $LIM,
    'result'
);
while ($row = gdrcd_query($resOgg, 'fetch')) {
    $results['oggetti'][] = [
        'nome' => (string)$row['nome'],
        'url'  => 'main.php?page=servizi_mercato&op=visit&what=' . (int)$row['tipo'],
    ];
}
gdrcd_query($resOgg, 'free');

// --- Gilde --------------------------------------------------------------
$resGil = gdrcd_query(
    "SELECT id_gilda, nome FROM gilda
     WHERE visibile = 1
       AND nome LIKE '%" . $q . "%'
     ORDER BY nome
     LIMIT " . $LIM,
    'result'
);
while ($row = gdrcd_query($resGil, 'fetch')) {
    $results['gilde'][] = [
        'nome' => (string)$row['nome'],
        'url'  => 'main.php?page=servizi_gilde&id_gilda=' . (int)$row['id_gilda'],
    ];
}
gdrcd_query($resGil, 'free');

// --- Ambientazione ------------------------------------------------------
// Match su titolo o testo. Restituiamo il titolo + capitolo, e linkiamo
// alla pagina con anchor #cap-N (il client può fare scroll lato js).
$resAmb = gdrcd_query(
    "SELECT capitolo, titolo FROM ambientazione
     WHERE titolo LIKE '%" . $q . "%' OR testo LIKE '%" . $q . "%'
     ORDER BY capitolo
     LIMIT " . $LIM,
    'result'
);
while ($row = gdrcd_query($resAmb, 'fetch')) {
    $results['ambientazione'][] = [
        'titolo'   => (string)$row['titolo'],
        'capitolo' => (int)$row['capitolo'],
        'url'      => 'main.php?page=user_ambientazione#cap-' . (int)$row['capitolo'],
    ];
}
gdrcd_query($resAmb, 'free');

// --- Regolamento --------------------------------------------------------
$resReg = gdrcd_query(
    "SELECT articolo, titolo FROM regolamento
     WHERE titolo LIKE '%" . $q . "%' OR testo LIKE '%" . $q . "%'
     ORDER BY articolo
     LIMIT " . $LIM,
    'result'
);
while ($row = gdrcd_query($resReg, 'fetch')) {
    $results['regolamento'][] = [
        'titolo'    => (string)$row['titolo'],
        'articolo'  => (int)$row['articolo'],
        'url'       => 'main.php?page=user_regolamento#art-' . (int)$row['articolo'],
    ];
}
gdrcd_query($resReg, 'free');

$total = count($results['pg'])
       + count($results['oggetti'])
       + count($results['gilde'])
       + count($results['ambientazione'])
       + count($results['regolamento']);

echo json_encode(
    [
        'query'   => $qTrim,
        'results' => $results,
        'total'   => $total,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
