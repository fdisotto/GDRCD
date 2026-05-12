<?php
/**
 * Endpoint JSON: elenco PG online raggruppati per mappa/luogo.
 *
 * Stesse informazioni esposte dall'iframe pages/presenti_estesi.inc.php e
 * pages/presenti.inc.php (versione machine-readable per polling JS / client
 * mobili).
 *
 * Richiede sessione valida. Risponde 401 se non autenticato.
 *
 * @see pages/presenti_estesi.inc.php
 * @see pages/presenti.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$handleDBConnection = gdrcd_connect();

// Auth: sessione PHP o JWT Bearer.
$auth = gdrcd_api_authenticate();
if ($auth === null) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$show_state = ($PARAMETERS['mode']['user_online_state'] ?? 'OFF') === 'ON';
$mapwise    = ($PARAMETERS['mode']['mapwise_links'] ?? 'OFF') !== 'OFF';
$me_login   = (string)$auth['login'];

$result = gdrcd_query(
    "SELECT personaggio.nome, personaggio.cognome, personaggio.permessi, personaggio.sesso,
            personaggio.id_razza, razza.sing_m, razza.sing_f, razza.icon,
            personaggio.disponibile, personaggio.online_status, personaggio.is_invisible,
            personaggio.ultima_mappa, personaggio.ultimo_luogo, personaggio.posizione,
            personaggio.ora_entrata, personaggio.ora_uscita, personaggio.ultimo_refresh,
            mappa.stanza_apparente, mappa.nome AS luogo, mappa_click.nome AS mappa
     FROM personaggio
     LEFT JOIN mappa ON personaggio.ultimo_luogo = mappa.id
     LEFT JOIN mappa_click ON personaggio.ultima_mappa = mappa_click.id_click
     LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
     WHERE personaggio.ora_entrata > personaggio.ora_uscita
       AND DATE_ADD(personaggio.ultimo_refresh, INTERVAL 4 MINUTE) > NOW()
     ORDER BY personaggio.is_invisible, personaggio.ultima_mappa, personaggio.ultimo_luogo, personaggio.nome",
    'result'
);

$invisible_label = $MESSAGE['status_pg']['invisible'][1] ?? 'Invisibili';

// Raggruppamento mappa => luogo => [pgs]
$grouped = [];
// Manteniamo i metadati per il link luogo (id_mappa / id_luogo) usando la
// prima riga di ogni gruppo, come fa il template iframe.
$group_meta = [];

while ($row = gdrcd_query($result, 'fetch')) {
    $is_invisible = ((int)$row['is_invisible'] === 1);

    if ($is_invisible) {
        $mappa_key = $invisible_label;
        $luogo_key = $invisible_label;
    } else {
        $mappa_key = $row['mappa'] ?: '—';
        $luogo_key = !empty($row['stanza_apparente'])
            ? $row['stanza_apparente']
            : ($row['luogo'] ?: '—');
    }

    $activity   = gdrcd_check_time($row['ora_entrata']);
    $just_in    = ($activity <= 2);
    $razza_lbl  = $row['sing_' . $row['sesso']] ?? '';

    $pg = [
        'nome'          => (string)$row['nome'],
        'cognome'       => (string)($row['cognome'] ?? ''),
        'permessi'      => (int)$row['permessi'],
        'sesso'         => (string)$row['sesso'],
        'razza_label'   => (string)$razza_lbl,
        'razza_icon'    => (string)($row['icon'] ?: 'standard_razza.png'),
        'disponibile'   => (int)$row['disponibile'],
        'is_invisible'  => $is_invisible,
        'is_me'         => ($row['nome'] === $me_login),
        'just_entered'  => $just_in,
        // online_status: lo restituiamo solo se l'opzione è attiva, altrimenti null.
        'online_status' => ($show_state && !empty($row['online_status']))
            ? (string)$row['online_status']
            : null,
    ];

    if (!isset($grouped[$mappa_key])) {
        $grouped[$mappa_key] = [];
    }
    if (!isset($grouped[$mappa_key][$luogo_key])) {
        $grouped[$mappa_key][$luogo_key] = [];
        $group_meta[$mappa_key][$luogo_key] = [
            'is_invisible' => $is_invisible,
            'ultima_mappa' => (int)$row['ultima_mappa'],
            'ultimo_luogo' => (int)$row['ultimo_luogo'],
        ];
    }
    $grouped[$mappa_key][$luogo_key][] = $pg;
}
gdrcd_query($result, 'free');

$total = 0;
$groups = [];
foreach ($grouped as $mappa_key => $luoghi) {
    foreach ($luoghi as $luogo_key => $pgs) {
        $meta = $group_meta[$mappa_key][$luogo_key] ?? [
            'is_invisible' => false,
            'ultima_mappa' => 0,
            'ultimo_luogo' => 0,
        ];
        $luogo_link = null;
        if (!$mapwise && !$meta['is_invisible'] && $meta['ultimo_luogo'] > 0) {
            $luogo_link = 'main.php?dir=' . $meta['ultimo_luogo']
                        . '&map_id=' . $meta['ultima_mappa'];
        }
        $count = count($pgs);
        $total += $count;
        $groups[] = [
            'mappa'      => (string)$mappa_key,
            'luogo'      => (string)$luogo_key,
            'luogo_link' => $luogo_link,
            'pgs'        => $pgs,
        ];
    }
}

echo json_encode([
    'total'  => $total,
    'groups' => $groups,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
