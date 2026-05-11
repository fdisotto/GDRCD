<?php
/**
 * Endpoint JSON: info del luogo corrente ($_SESSION['luogo']).
 *
 * Risponde con nome, stato, descrizione, immagine, meteo (calcolato se
 * auto_meteo=ON, altrimenti valore manuale dalla mappa), fase lunare e
 * data di gioco. Stessa logica di pages/info_location.inc.php.
 *
 * Richiede sessione valida.
 *
 * @see pages/info_location.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (empty($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!function_exists('gdrcd_api_lunar_phase')) {
    /**
     * Replica di pages/info_location.inc.php::gdrcd_lunar_phase() senza
     * mantenere stato condiviso fra include (definita con prefisso "api_").
     */
    function gdrcd_api_lunar_phase(): array
    {
        $year  = (int)date('Y');
        $month = (int)date('n');
        $days  = (int)date('j');
        if ($month < 4) { $year--; $month += 12; }
        $plenilunio = (365.25 * $year + 30.42 * $month + $days - 694039.09) / 29.53;
        $phase = (int)$plenilunio;
        $phase = (int)round(($plenilunio - $phase) * 8 + 0.5);
        if ($phase === 8) {
            $phase = 0;
        }
        $names  = ['nuova', 'crescente', 'primo-quarto', 'gibbosa-crescente', 'piena', 'gibbosa-calante', 'ultimo-quarto', 'calante'];
        $titles = ['Nuova', 'Crescente', 'Primo Quarto', 'Gibbosa crescente', 'Piena', 'Gibbosa calante', 'Ultimo quarto', 'Calante'];
        return ['phase' => $names[$phase], 'title' => $titles[$phase]];
    }
}

$handleDBConnection = gdrcd_connect();

$luogo_id = (int)($_SESSION['luogo'] ?? 0);

$result = gdrcd_query(
    "SELECT mappa.nome, mappa.descrizione, mappa.stato, mappa.immagine,
            mappa.stanza_apparente, mappa.scadenza, mappa_click.meteo
     FROM mappa_click LEFT JOIN mappa ON mappa_click.id_click = mappa.id_mappa
     WHERE id = " . $luogo_id,
    'result'
);
$record_exists = gdrcd_query($result, 'num_rows');
$record = gdrcd_query($result, 'fetch');
gdrcd_query($result, 'free');

if (empty($record['nome'])) {
    $nome_mappa = gdrcd_query(
        "SELECT nome FROM mappa_click WHERE id_click = " . (int)($_SESSION['mappa'] ?? 0)
    );
    $nome_luogo = $nome_mappa['nome'] ?? '';
} else {
    $nome_luogo = $record['nome'];
}

// Meteo: se la mappa esiste o siamo nel "limbo" (-1), calcola/preleva.
$meteo = null;
if ($record_exists > 0 || $luogo_id === -1) {
    if (($PARAMETERS['mode']['auto_meteo'] ?? 'OFF') === 'ON') {
        $ore    = (int)date('H');
        $mese   = (int)date('m');
        $giorno = (int)date('z') + 1;
        $caso   = ((int)floor($giorno / 3)) % 2 + 1;

        $offsets = [1 => 0, 2 => 4, 3 => 8, 4 => 14, 5 => 20, 6 => 28,
                    7 => 30, 8 => 27, 9 => 21, 10 => 15, 11 => 5, 12 => 1];
        $minima = ($PARAMETERS['date']['base_temperature'] ?? 0) + ($offsets[$mese] ?? 0);

        if ($ore < 14) {
            $gradi = $minima + ((int)floor($ore / 3) * $caso);
        } else {
            $gradi = $minima + (4 * $caso) - ((int)floor($ore / 3) * $caso) + (3 * $caso);
        }

        $caso = ($giorno + ($ore / 4)) % 12;
        $cond_map = [
            0 => 0, 6 => 0, 10 => 0, 11 => 0, 1 => 0,
            7 => 1, 5 => 1, 2 => 1,
            9 => 2, 3 => 2,
            8 => 3, 4 => 3,
        ];
        $idx = $cond_map[(int)$caso] ?? 0;
        if ($idx === 3 && $minima < 4) {
            $idx = 4;
        }
        $meteo_cond = $MESSAGE['interface']['meteo']['status'][$idx] ?? '';
        $meteo = [
            'condizione' => (string)$meteo_cond,
            'gradi'      => (int)$gradi,
        ];
    } else {
        // Meteo manuale: stringa libera salvata su mappa_click.meteo.
        $meteo_str = (string)($record['meteo'] ?? '');
        if ($meteo_str !== '') {
            $meteo = [
                'condizione' => $meteo_str,
                'gradi'      => null,
            ];
        }
    }
}

$data_gioco = date('d/m/') . (date('Y') + ($PARAMETERS['date']['offset'] ?? 0));
$luna = (defined('MOON') && MOON) ? gdrcd_api_lunar_phase() : null;

echo json_encode([
    'nome'        => (string)$nome_luogo,
    'stato'       => isset($record['stato']) ? (string)$record['stato'] : null,
    'descrizione' => isset($record['descrizione']) ? (string)$record['descrizione'] : null,
    'immagine'    => !empty($record['immagine']) ? (string)$record['immagine'] : 'standard_luogo.png',
    'meteo'       => $meteo,
    'luna'        => $luna,
    'data_gioco'  => $data_gioco,
    'exists'      => ($record_exists > 0),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
