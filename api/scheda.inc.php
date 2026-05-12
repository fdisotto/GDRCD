<?php
/**
 * Endpoint JSON: scheda PG ridotta (dati pubblici + dati privati se self).
 *
 * GET ?pg=<nome> — default $_SESSION['login'].
 *
 * Permission rules:
 *   - pg === $_SESSION['login']  → full data (HP, caratteristiche dettagliate)
 *   - altrimenti                 → solo dati pubblici (no HP, no caratteristiche)
 *
 * Richiede sessione valida.
 *
 * @see pages/scheda.inc.php
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

$me_login = (string)$auth['login'];
$pg_param = isset($_GET['pg']) ? (string)$_GET['pg'] : $me_login;
if ($pg_param === '') {
    $pg_param = $me_login;
}
$pg_safe = gdrcd_filter('in', $pg_param);

$result = gdrcd_query(
    "SELECT personaggio.nome, personaggio.cognome, personaggio.sesso, personaggio.permessi,
            personaggio.descrizione, personaggio.stato, personaggio.url_img,
            personaggio.salute, personaggio.salute_max, personaggio.esperienza,
            personaggio.car0, personaggio.car1, personaggio.car2,
            personaggio.car3, personaggio.car4, personaggio.car5,
            razza.sing_m, razza.sing_f, razza.icon,
            razza.bonus_car0, razza.bonus_car1, razza.bonus_car2,
            razza.bonus_car3, razza.bonus_car4, razza.bonus_car5
     FROM personaggio LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
     WHERE personaggio.nome = '" . $pg_safe . "'
     LIMIT 1",
    'result'
);

if (gdrcd_query($result, 'num_rows') === 0) {
    gdrcd_query($result, 'free');
    http_response_code(404);
    echo json_encode(['error' => 'Personaggio non trovato'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    exit;
}

$p = gdrcd_query($result, 'fetch');
gdrcd_query($result, 'free');

$is_self    = ($p['nome'] === $me_login);
$razza_lbl  = $p['sing_' . $p['sesso']] ?? '';

// Dati pubblici (sempre esposti)
$payload = [
    'nome'        => (string)$p['nome'],
    'cognome'     => (string)($p['cognome'] ?? ''),
    'sesso'       => (string)$p['sesso'],
    'permessi'    => (int)$p['permessi'],
    'razza'       => [
        'label' => (string)$razza_lbl,
        'icon'  => (string)($p['icon'] ?? 'standard_razza.png'),
    ],
    'descrizione' => (string)($p['descrizione'] ?? ''),
    'stato'       => (string)($p['stato'] ?? ''),
    'url_img'     => !empty($p['url_img']) ? gdrcd_filter('fullurl', $p['url_img']) : null,
    'esperienza'  => (int)floor((float)$p['esperienza']),
    'is_self'     => $is_self,
];

if ($is_self) {
    // Dati privati: HP e caratteristiche dettagliate (base + bonus razziale).
    // I bonus oggetti non li sommiamo qui: l'API è "scheda base", il calcolo
    // completo (incluso equipaggiamento) resta lato page per ora.
    $payload['salute']      = (int)$p['salute'];
    $payload['salute_max']  = (int)$p['salute_max'];
    $payload['caratteristiche'] = [];
    for ($i = 0; $i < 6; $i++) {
        $base  = (int)($p['car' . $i] ?? 0);
        $bonus = (int)($p['bonus_car' . $i] ?? 0);
        $payload['caratteristiche'][] = [
            'nome'  => (string)($PARAMETERS['names']['stats']['car' . $i] ?? ('car' . $i)),
            'base'  => $base,
            'bonus' => $bonus,
            'tot'   => $base + $bonus,
        ];
    }
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
