#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * GDRCD WebSocket chat server.
 *
 * Server Ratchet che eroga push real-time dei messaggi chat ai client web,
 * sostituendo il polling HTTP di /api/chat.inc.php (intervallo ~4s) con un
 * canale persistente WebSocket (latenza ~1s).
 *
 * Architettura: DB-driven polling. Il server WS interroga ogni 1s la tabella
 * `chat` per ciascuna stanza con almeno un client connesso, recupera i record
 * con id > lastSeen e li broadcasta in JSON ai client iscritti alla stanza.
 * Nessun IPC con PHP-FPM: il flow di INSERT (ref_header / pages/chat.inc.php)
 * resta inalterato; il WS server agisce come "tail" del DB.
 *
 * Avvio:
 *   php bin/gdrcd-ws-server.php           # standalone, porta 8082
 *   docker compose up -d gdrcd-ws         # container dedicato (vedi compose)
 *
 * Variabili d'ambiente opzionali:
 *   GDRCD_WS_HOST   bind address (default 0.0.0.0)
 *   GDRCD_WS_PORT   listening port (default 8082)
 *   GDRCD_WS_TICK   intervallo poll in secondi (default 1.0)
 *
 * Fallback: il client (includes/chat.js) torna automaticamente al polling
 * HTTP se la connessione WS non si stabilisce o cade — il sistema resta
 * funzionante anche senza questo processo attivo.
 *
 * @see src/WebSocket/ChatHandler.php
 * @see includes/chat.js
 * @see api/chat.inc.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/required.php';

if (!class_exists(\Ratchet\Server\IoServer::class)) {
    fwrite(STDERR, "[gdrcd-ws] ERRORE: dipendenza cboden/ratchet non installata. Eseguire `composer install`.\n");
    exit(1);
}

$host = getenv('GDRCD_WS_HOST') !== false && getenv('GDRCD_WS_HOST') !== ''
    ? (string)getenv('GDRCD_WS_HOST')
    : '0.0.0.0';
$port = getenv('GDRCD_WS_PORT') !== false && getenv('GDRCD_WS_PORT') !== ''
    ? (int)getenv('GDRCD_WS_PORT')
    : 8082;
$tick = getenv('GDRCD_WS_TICK') !== false && getenv('GDRCD_WS_TICK') !== ''
    ? (float)getenv('GDRCD_WS_TICK')
    : 1.0;
if ($tick < 0.2) {
    $tick = 0.2;
}

$loop    = \React\EventLoop\Factory::create();
$handler = new \GDRCD\WebSocket\ChatHandler($loop, $tick);

$wsServer = new \Ratchet\WebSocket\WsServer($handler);
// Disabilita controllo origin: la pagina lato gioco serve da qualsiasi host
// (sviluppo via browsersync, prod su dominio diverso). Per ambienti pubblici
// si raccomanda di restringere via reverse proxy.
$wsServer->disableVersion(0);

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer($wsServer),
    $port,
    $host
);

fwrite(STDOUT, sprintf(
    "[gdrcd-ws] in ascolto su ws://%s:%d (tick=%.2fs)\n",
    $host,
    $port,
    $tick
));

$server->run();
