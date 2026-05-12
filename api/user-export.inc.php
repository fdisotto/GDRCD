<?php
declare(strict_types=1);

/**
 * Endpoint download: export GDPR dei dati personali dell'utente loggato.
 *
 * Genera uno ZIP con:
 *   - manifest.json         metadati export (versione, utente, generated_at, file)
 *   - scheda.json           riga personaggio + razza joinata
 *   - messaggi.json         messaggi (mittente o destinatario) ultimi 24 mesi
 *   - log.json              log (interessato o autore) ultimi 12 mesi
 *   - diario.json           diario personale completo
 *   - oggetti.json          inventario (clgpersonaggiooggetto + oggetto)
 *   - segnalazioni_role.json  segnalazioni role inviate dall'utente
 *
 * Risposta:
 *   - 200 application/zip   download attachment "gdrcd-export-<user>-<YYYYMMDD>.zip"
 *   - 401                   sessione mancante
 *   - 500                   ZipArchive non disponibile / errore creazione
 *
 * Richiede sessione valida.
 *
 * @see pages/user_privacy.inc.php
 */

require_once __DIR__ . '/../includes/required.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ZipArchive non disponibile su questo server'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$handleDBConnection = gdrcd_connect();

$auth = gdrcd_api_authenticate();
if ($auth === null) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = (string)$auth['login'];

// ---- Dati: scheda personaggio + razza --------------------------------------
$scheda = Db::preparedFetch(
    "SELECT personaggio.nome, personaggio.cognome, personaggio.email,
            personaggio.data_iscrizione, personaggio.ultimo_cambiopass,
            personaggio.sesso, personaggio.permessi, personaggio.id_razza,
            personaggio.descrizione, personaggio.affetti, personaggio.storia,
            personaggio.stato, personaggio.url_img, personaggio.url_media,
            personaggio.esperienza, personaggio.salute, personaggio.salute_max,
            personaggio.car0, personaggio.car1, personaggio.car2,
            personaggio.car3, personaggio.car4, personaggio.car5,
            personaggio.soldi, personaggio.banca,
            personaggio.ultimo_refresh, personaggio.ora_entrata, personaggio.ora_uscita,
            razza.nome_razza, razza.sing_m, razza.sing_f
     FROM personaggio
     LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
     WHERE personaggio.nome = ?
     LIMIT 1",
    's',
    array($user)
);

// ---- Messaggi (mittente o destinatario), ultimi 24 mesi --------------------
$messaggi = Db::preparedFetchAll(
    "SELECT id, mittente, destinatario, spedito, letto, tipo, oggetto, testo
     FROM messaggi
     WHERE (mittente = ? OR destinatario = ?)
       AND spedito > (NOW() - INTERVAL 24 MONTH)
     ORDER BY spedito DESC",
    'ss',
    array($user, $user)
);

// ---- Log eventi (interessato o autore), ultimi 12 mesi ---------------------
$log = Db::preparedFetchAll(
    "SELECT id, nome_interessato, autore, data_evento, codice_evento, descrizione_evento
     FROM log
     WHERE (nome_interessato = ? OR autore = ?)
       AND data_evento > (NOW() - INTERVAL 12 MONTH)
     ORDER BY data_evento DESC",
    'ss',
    array($user, $user)
);

// ---- Diario completo del personaggio ---------------------------------------
$diario = Db::preparedFetchAll(
    "SELECT id, personaggio, data, data_inserimento, data_modifica, visibile, titolo, testo
     FROM diario
     WHERE personaggio = ?
     ORDER BY data DESC",
    's',
    array($user)
);

// ---- Inventario (oggetti posseduti) ----------------------------------------
$oggetti = Db::preparedFetchAll(
    "SELECT clgpersonaggiooggetto.nome AS proprietario,
            clgpersonaggiooggetto.id_oggetto,
            clgpersonaggiooggetto.numero,
            clgpersonaggiooggetto.cariche,
            clgpersonaggiooggetto.commento,
            clgpersonaggiooggetto.posizione,
            oggetto.nome AS oggetto_nome,
            oggetto.tipo,
            oggetto.descrizione,
            oggetto.attacco,
            oggetto.difesa,
            oggetto.costo
     FROM clgpersonaggiooggetto
     LEFT JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
     WHERE clgpersonaggiooggetto.nome = ?
     ORDER BY clgpersonaggiooggetto.posizione, clgpersonaggiooggetto.id_oggetto",
    's',
    array($user)
);

// ---- Segnalazioni role inviate dall'utente ---------------------------------
$segnalazioni = Db::preparedFetchAll(
    "SELECT id, stanza, conclusa, partecipanti, mittente, data_inizio, data_fine, tags, quest
     FROM segnalazione_role
     WHERE mittente = ?
     ORDER BY data_inizio DESC",
    's',
    array($user)
);

// ---- Costruzione ZIP -------------------------------------------------------
$json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

$files = [
    'scheda.json'              => $scheda,
    'messaggi.json'            => $messaggi,
    'log.json'                 => $log,
    'diario.json'              => $diario,
    'oggetti.json'             => $oggetti,
    'segnalazioni_role.json'   => $segnalazioni,
];

$manifest = [
    'version'       => '1.0',
    'user'          => $user,
    'generated_at'  => date('c'),
    'files'         => array_keys($files),
    'retention'     => [
        'messaggi.json' => 'ultimi 24 mesi',
        'log.json'      => 'ultimi 12 mesi',
        'altri'         => 'storico completo',
    ],
];

$tmp = tempnam(sys_get_temp_dir(), 'gdrcd-export-');
if ($tmp === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Impossibile creare il file temporaneo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    exit;
}

$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Impossibile creare lo ZIP'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (isset($handleDBConnection)) {
        gdrcd_close_connection($handleDBConnection);
    }
    exit;
}

$zip->addFromString('manifest.json', json_encode($manifest, $json_flags));
foreach ($files as $name => $data) {
    $zip->addFromString($name, json_encode($data, $json_flags));
}
$zip->close();

// ---- Streaming download ----------------------------------------------------
$safe_user = preg_replace('/[^A-Za-z0-9_-]+/', '_', $user);
$filename  = 'gdrcd-export-' . $safe_user . '-' . date('Ymd') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($tmp);
@unlink($tmp);

if (isset($handleDBConnection)) {
    gdrcd_close_connection($handleDBConnection);
    unset($handleDBConnection);
}
exit;
