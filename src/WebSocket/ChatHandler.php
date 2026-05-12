<?php
declare(strict_types=1);

namespace GDRCD\WebSocket;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use SplObjectStorage;
use Throwable;

/**
 * Handler Ratchet per la chat real-time di GDRCD.
 *
 * Modello stateless DB-driven:
 *
 *   1. Il client web apre la WS (ws://host:8082) e invia subito
 *      {"action":"subscribe","room":<luogo>}.
 *   2. L'handler aggiunge la connessione alla room. Su `pollAndBroadcast()`
 *      (timer periodico, default 1s) esegue
 *      `SELECT ... FROM chat WHERE stanza=? AND id > <lastIds[room]>` per ogni
 *      room con almeno un client e fa broadcast in JSON dei record nuovi.
 *   3. Su `onClose()` la conn viene rimossa dalle strutture; se la room
 *      svuota, viene scartato anche il lastId.
 *
 * Auth: best effort via PHPSESSID dal Cookie header dell'handshake HTTP.
 * Se la sessione e' valida e ha `$_SESSION['login']`, la conn e' considerata
 * autenticata; altrimenti viene chiusa. NB: la struttura dati del JSON
 * broadcastato e' identica a quella di /api/chat.inc.php — il client riusa
 * il rendering esistente.
 *
 * @see bin/gdrcd-ws-server.php
 * @see api/chat.inc.php
 */
final class ChatHandler implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface,null> Tutti i client connessi. */
    private SplObjectStorage $clients;

    /**
     * Mapping `room_id => [resource_id => ConnectionInterface]`. Indicizzato
     * per resource_id per consentire `unset()` O(1) in onClose senza scan.
     *
     * @var array<int, array<int, ConnectionInterface>>
     */
    private array $rooms = [];

    /** @var array<int, int> Ultimo `chat.id` broadcastato per room. */
    private array $lastIds = [];

    private LoopInterface $loop;
    private float $tickSeconds;

    public function __construct(LoopInterface $loop, float $tickSeconds = 1.0)
    {
        $this->clients     = new SplObjectStorage();
        $this->loop        = $loop;
        $this->tickSeconds = $tickSeconds;

        $loop->addPeriodicTimer($this->tickSeconds, function (): void {
            try {
                $this->pollAndBroadcast();
            } catch (Throwable $e) {
                // Non lasciare crashare il loop su errori transitori (es. DB down).
                fwrite(STDERR, '[gdrcd-ws] pollAndBroadcast error: ' . $e->getMessage() . "\n");
            }
        });
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $auth = $this->authenticateFromHandshake($conn);
        if ($auth === null) {
            $conn->send(json_encode([
                'type'  => 'error',
                'error' => 'unauthenticated',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $conn->close();
            return;
        }

        // Persistiamo info utente direttamente sulla conn (Ratchet permette
        // di settare proprieta' dinamiche su ConnectionInterface).
        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdUser = $auth['login'];
        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdLuogo = $auth['luogo'];
        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdRoom = 0; // valorizzato a subscribe.

        $this->clients->attach($conn);

        $conn->send(json_encode([
            'type'    => 'welcome',
            'user'    => $auth['login'],
            'luogo'   => $auth['luogo'],
            'tick_ms' => (int)round($this->tickSeconds * 1000),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string)$msg, true);
        if (!is_array($data) || !isset($data['action'])) {
            return;
        }

        switch ($data['action']) {
            case 'subscribe':
                $room = isset($data['room']) ? (int)$data['room'] : 0;
                if ($room <= 0) {
                    return;
                }
                $this->subscribe($from, $room);
                break;

            case 'unsubscribe':
                $this->removeFromAllRooms($from);
                break;

            case 'ping':
                $from->send(json_encode([
                    'type' => 'pong',
                    'ts'   => time(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->removeFromAllRooms($conn);
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        fwrite(STDERR, '[gdrcd-ws] connection error: ' . $e->getMessage() . "\n");
        $conn->close();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Recupera PHPSESSID dal Cookie HTTP dell'handshake, decodifica la
     * sessione filesystem standard di PHP e ritorna login/luogo se l'utente
     * risulta autenticato.
     *
     * @return array{login:string,luogo:int}|null
     */
    private function authenticateFromHandshake(ConnectionInterface $conn): ?array
    {
        $sessionName = session_name() ?: 'PHPSESSID';
        $cookieHeader = '';

        // Ratchet 0.4 espone l'HTTP request handshake su $conn->httpRequest
        // (PSR-7 RequestInterface).
        /** @phpstan-ignore-next-line dynamic property */
        $httpReq = $conn->httpRequest ?? null;
        if ($httpReq !== null && method_exists($httpReq, 'getHeader')) {
            $cookies = $httpReq->getHeader('Cookie');
            if (is_array($cookies) && !empty($cookies)) {
                $cookieHeader = implode('; ', $cookies);
            }
        }

        if ($cookieHeader === '') {
            return null;
        }

        $sid = null;
        foreach (explode(';', $cookieHeader) as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if (trim($k) === $sessionName) {
                $sid = trim($v);
                break;
            }
        }
        if ($sid === null || $sid === '') {
            return null;
        }
        // Sanity check: solo caratteri ammessi per session id.
        if (!preg_match('/^[A-Za-z0-9,\-]{1,128}$/', $sid)) {
            return null;
        }

        // Apri la sessione filesystem standard (file save handler).
        // session_status() puo' essere PHP_SESSION_NONE all'avvio del loop.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id($sid);
        @session_start([
            'use_strict_mode' => false,
            'read_and_close'  => true,
        ]);

        $login = isset($_SESSION['login']) ? (string)$_SESSION['login'] : '';
        $luogo = isset($_SESSION['luogo']) ? (int)$_SESSION['luogo'] : 0;

        // Cleanup di $_SESSION per non inquinare le richieste successive.
        $_SESSION = [];

        if ($login === '') {
            return null;
        }
        return ['login' => $login, 'luogo' => $luogo];
    }

    private function subscribe(ConnectionInterface $conn, int $room): void
    {
        // Se era gia' in un'altra room, rimuovilo prima.
        $this->removeFromAllRooms($conn);

        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdRoom = $room;
        $rid = $this->resourceId($conn);
        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = [];
        }
        $this->rooms[$room][$rid] = $conn;

        // Inizializza lastId alla soglia attuale: bootstrap inviera' i
        // messaggi degli ultimi 30 minuti (allineato alla finestra storica
        // della chat in pages/frame_chat.inc.php).
        $bootstrap = $this->fetchRecentMessages($room, 30);
        if (!empty($bootstrap)) {
            $maxId = 0;
            foreach ($bootstrap as $m) {
                if ($m['id'] > $maxId) $maxId = (int)$m['id'];
            }
            $this->lastIds[$room] = max($this->lastIds[$room] ?? 0, $maxId);

            $conn->send(json_encode([
                'type'     => 'bootstrap',
                'room'     => $room,
                'messages' => $bootstrap,
                'last_id'  => $this->lastIds[$room],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            // Allinea il puntatore alla MAX(id) corrente per evitare di
            // ricevere come "nuovi" record gia' visualizzati dal client.
            $row = gdrcd_query("SELECT MAX(id) AS max_id FROM chat WHERE stanza = " . $room);
            $maxId = is_array($row) && isset($row['max_id']) ? (int)$row['max_id'] : 0;
            $this->lastIds[$room] = max($this->lastIds[$room] ?? 0, $maxId);

            $conn->send(json_encode([
                'type'    => 'subscribed',
                'room'    => $room,
                'last_id' => $this->lastIds[$room],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private function removeFromAllRooms(ConnectionInterface $conn): void
    {
        $rid = $this->resourceId($conn);
        foreach ($this->rooms as $room => $conns) {
            if (isset($conns[$rid])) {
                unset($this->rooms[$room][$rid]);
                if (empty($this->rooms[$room])) {
                    unset($this->rooms[$room]);
                    unset($this->lastIds[$room]);
                }
            }
        }
    }

    /**
     * Restituisce un id stabile per ciascuna connessione. Ratchet espone
     * `resourceId` ma non e' nell'interfaccia, quindi fallback su spl_object_id.
     */
    private function resourceId(ConnectionInterface $conn): int
    {
        /** @phpstan-ignore-next-line property may exist */
        if (isset($conn->resourceId)) {
            return (int)$conn->resourceId;
        }
        return spl_object_id($conn);
    }

    /**
     * Poll del DB per ogni room attiva, e broadcast dei nuovi messaggi ai
     * client iscritti.
     */
    public function pollAndBroadcast(): void
    {
        if (empty($this->rooms)) {
            return;
        }
        foreach ($this->rooms as $room => $conns) {
            if (empty($conns)) {
                continue;
            }
            $lastId = (int)($this->lastIds[$room] ?? 0);
            $newMsgs = $this->fetchNewMessages($room, $lastId);
            if (empty($newMsgs)) {
                continue;
            }
            $maxId = $lastId;
            foreach ($newMsgs as $m) {
                if ((int)$m['id'] > $maxId) {
                    $maxId = (int)$m['id'];
                }
            }
            $this->lastIds[$room] = $maxId;

            $payload = json_encode([
                'type'     => 'messages',
                'room'     => $room,
                'messages' => $newMsgs,
                'last_id'  => $maxId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($conns as $conn) {
                try {
                    $conn->send($payload);
                } catch (Throwable $e) {
                    // Conn morta: la ripuliremo a onClose; ignora qui.
                }
            }
        }
    }

    /**
     * Carica i messaggi della stanza con id > lastId, allineato al SELECT di
     * api/chat.inc.php (stessa shape JSON).
     *
     * @return list<array<string,mixed>>
     */
    private function fetchNewMessages(int $room, int $lastId): array
    {
        $sql = "SELECT chat.id, chat.imgs, chat.mittente, chat.destinatario, chat.tipo,
                       chat.ora, chat.testo, personaggio.url_img_chat
                FROM chat
                INNER JOIN mappa ON mappa.id = chat.stanza
                LEFT JOIN personaggio ON personaggio.nome = chat.mittente
                WHERE chat.id > " . $lastId . "
                  AND stanza = " . $room . "
                  AND chat.ora > IFNULL(mappa.ora_prenotazione, '0000-00-00 00:00:00')
                  AND DATE_SUB(NOW(), INTERVAL 30 MINUTE) < chat.ora
                ORDER BY chat.id ASC
                LIMIT 50";
        return $this->fetchMessagesSql($sql);
    }

    /**
     * Bootstrap iniziale: ultimi N minuti per la stanza, come bootstrap.
     *
     * @return list<array<string,mixed>>
     */
    private function fetchRecentMessages(int $room, int $minutes): array
    {
        $minutes = max(1, $minutes);
        $sql = "SELECT chat.id, chat.imgs, chat.mittente, chat.destinatario, chat.tipo,
                       chat.ora, chat.testo, personaggio.url_img_chat
                FROM chat
                INNER JOIN mappa ON mappa.id = chat.stanza
                LEFT JOIN personaggio ON personaggio.nome = chat.mittente
                WHERE stanza = " . $room . "
                  AND chat.ora > IFNULL(mappa.ora_prenotazione, '0000-00-00 00:00:00')
                  AND DATE_SUB(NOW(), INTERVAL " . $minutes . " MINUTE) < chat.ora
                ORDER BY chat.id ASC
                LIMIT 100";
        return $this->fetchMessagesSql($sql);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchMessagesSql(string $sql): array
    {
        $result = gdrcd_query($sql, 'result');
        if (!$result) {
            return [];
        }
        $out = [];
        while ($row = gdrcd_query($result, 'fetch')) {
            $out[] = [
                'id'           => (int)$row['id'],
                'tipo'         => (string)$row['tipo'],
                'mittente'     => (string)$row['mittente'],
                'destinatario' => empty($row['destinatario']) ? null : (string)$row['destinatario'],
                'ora'          => (string)$row['ora'],
                'testo'        => (string)$row['testo'],
                'imgs'         => (string)($row['imgs'] ?? ''),
                'url_img_chat' => !empty($row['url_img_chat'])
                    ? gdrcd_filter('fullurl', $row['url_img_chat'])
                    : null,
            ];
        }
        gdrcd_query($result, 'free');
        return $out;
    }
}
