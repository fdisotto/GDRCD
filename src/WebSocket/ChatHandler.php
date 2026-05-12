<?php
declare(strict_types=1);

namespace GDRCD\WebSocket;

use Db;
use GDRCD\Models\Quest;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use SplObjectStorage;
use Throwable;

/**
 * Handler Ratchet multi-canale per GDRCD.
 *
 * Tre canali push (DB-driven, stateless rispetto ai client web/FPM):
 *
 *   - chat:<room>    push dei nuovi record `chat` per stanza (tick 1s)
 *   - notifications  push del contatore PM/segnalazioni per-utente (tick 5s)
 *   - presenti       push della lista PG online globale (tick 10s)
 *
 * Protocollo client -> server:
 *   {"action":"subscribe","channel":"chat","room":<luogo>}
 *   {"action":"subscribe","channel":"notifications"}
 *   {"action":"subscribe","channel":"presenti"}
 *   {"action":"unsubscribe","channel":"chat|notifications|presenti"}
 *   {"action":"ping"}
 *
 * Retrocompat: `{"action":"subscribe","room":N}` (senza `channel`) equivale a
 * `subscribe chat`.
 *
 * Auth: best effort via PHPSESSID dal Cookie header dell'handshake HTTP.
 *
 * @see bin/gdrcd-ws-server.php
 * @see api/chat.inc.php
 * @see api/notifications.inc.php
 * @see api/presenti.inc.php
 */
final class ChatHandler implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface,null> Tutti i client connessi. */
    private SplObjectStorage $clients;

    /**
     * `room_id => [resource_id => ConnectionInterface]`.
     * @var array<int, array<int, ConnectionInterface>>
     */
    private array $rooms = [];

    /** @var array<int, int> Ultimo `chat.id` broadcastato per room. */
    private array $lastIds = [];

    /**
     * Subscriber notifiche per login.
     * `login => [resource_id => ConnectionInterface]`.
     * @var array<string, array<int, ConnectionInterface>>
     */
    private array $userConns = [];

    /**
     * Ultimo payload notifiche inviato per login (per diff).
     * `login => ['pm' => int, 'seg' => int, 'pm_id' => int, 'quest_id' => int, 'quest_status' => string]`.
     * @var array<string, array{pm:int,seg:int,pm_id:int,quest_id:int,quest_status:string}>
     */
    private array $notifLast = [];

    /**
     * Subscriber presenti (canale globale).
     * `resource_id => ConnectionInterface`.
     * @var array<int, ConnectionInterface>
     */
    private array $presentiConns = [];

    /** Firma MD5 dell'ultimo payload presenti broadcastato (diff). */
    private string $presentiLastSig = '';

    private LoopInterface $loop;
    private float $tickSeconds;
    private float $notifTick;
    private float $presentiTick;

    public function __construct(
        LoopInterface $loop,
        float $tickSeconds = 1.0,
        float $notifTick = 5.0,
        float $presentiTick = 10.0
    ) {
        $this->clients      = new SplObjectStorage();
        $this->loop         = $loop;
        $this->tickSeconds  = $tickSeconds;
        $this->notifTick    = max(1.0, $notifTick);
        $this->presentiTick = max(2.0, $presentiTick);

        $loop->addPeriodicTimer($this->tickSeconds, function (): void {
            try { $this->pollAndBroadcast(); }
            catch (Throwable $e) { fwrite(STDERR, '[gdrcd-ws] chat poll: ' . $e->getMessage() . "\n"); }
        });

        $loop->addPeriodicTimer($this->notifTick, function (): void {
            try { $this->pollNotifications(); }
            catch (Throwable $e) { fwrite(STDERR, '[gdrcd-ws] notif poll: ' . $e->getMessage() . "\n"); }
        });

        $loop->addPeriodicTimer($this->presentiTick, function (): void {
            try { $this->pollPresenti(); }
            catch (Throwable $e) { fwrite(STDERR, '[gdrcd-ws] presenti poll: ' . $e->getMessage() . "\n"); }
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

        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdUser    = $auth['login'];
        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdLuogo   = $auth['luogo'];
        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdPermessi = $auth['permessi'];
        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdRoom    = 0;

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

        $channel = isset($data['channel']) ? (string)$data['channel'] : '';
        // Retrocompat: vecchio client manda solo {action:'subscribe', room:N}.
        if ($channel === '' && isset($data['room'])) {
            $channel = 'chat';
        }

        switch ($data['action']) {
            case 'subscribe':
                if ($channel === 'chat') {
                    $room = isset($data['room']) ? (int)$data['room'] : 0;
                    if ($room <= 0) return;
                    $this->subscribeChat($from, $room);
                } elseif ($channel === 'notifications') {
                    $this->subscribeNotifications($from);
                } elseif ($channel === 'presenti') {
                    $this->subscribePresenti($from);
                }
                break;

            case 'unsubscribe':
                if ($channel === 'chat' || $channel === '') {
                    $this->removeFromAllRooms($from);
                }
                if ($channel === 'notifications' || $channel === '') {
                    $this->removeFromNotifications($from);
                }
                if ($channel === 'presenti' || $channel === '') {
                    $this->removeFromPresenti($from);
                }
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
        $this->removeFromNotifications($conn);
        $this->removeFromPresenti($conn);
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        fwrite(STDERR, '[gdrcd-ws] connection error: ' . $e->getMessage() . "\n");
        $conn->close();
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    /**
     * @return array{login:string,luogo:int,permessi:int}|null
     */
    private function authenticateFromHandshake(ConnectionInterface $conn): ?array
    {
        $sessionName = session_name() ?: 'PHPSESSID';
        $cookieHeader = '';

        /** @phpstan-ignore-next-line dynamic property */
        $httpReq = $conn->httpRequest ?? null;
        if ($httpReq !== null && method_exists($httpReq, 'getHeader')) {
            $cookies = $httpReq->getHeader('Cookie');
            if (is_array($cookies) && !empty($cookies)) {
                $cookieHeader = implode('; ', $cookies);
            }
        }

        if ($cookieHeader === '') return null;

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
        if ($sid === null || $sid === '') return null;
        if (!preg_match('/^[A-Za-z0-9,\-]{1,128}$/', $sid)) return null;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id($sid);
        @session_start([
            'use_strict_mode' => false,
            'read_and_close'  => true,
        ]);

        $login    = isset($_SESSION['login'])    ? (string)$_SESSION['login']    : '';
        $luogo    = isset($_SESSION['luogo'])    ? (int)$_SESSION['luogo']       : 0;
        $permessi = isset($_SESSION['permessi']) ? (int)$_SESSION['permessi']    : 0;
        $_SESSION = [];

        if ($login === '') return null;
        return ['login' => $login, 'luogo' => $luogo, 'permessi' => $permessi];
    }

    // ------------------------------------------------------------------
    // Chat channel
    // ------------------------------------------------------------------

    private function subscribeChat(ConnectionInterface $conn, int $room): void
    {
        $this->removeFromAllRooms($conn);

        /** @phpstan-ignore-next-line dynamic property */
        $conn->gdrcdRoom = $room;
        $rid = $this->resourceId($conn);
        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = [];
        }
        $this->rooms[$room][$rid] = $conn;

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

    public function pollAndBroadcast(): void
    {
        if (empty($this->rooms)) return;
        foreach ($this->rooms as $room => $conns) {
            if (empty($conns)) continue;
            $lastId  = (int)($this->lastIds[$room] ?? 0);
            $newMsgs = $this->fetchNewMessages($room, $lastId);
            if (empty($newMsgs)) continue;

            $maxId = $lastId;
            foreach ($newMsgs as $m) {
                if ((int)$m['id'] > $maxId) $maxId = (int)$m['id'];
            }
            $this->lastIds[$room] = $maxId;

            $payload = json_encode([
                'type'     => 'messages',
                'room'     => $room,
                'messages' => $newMsgs,
                'last_id'  => $maxId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($conns as $conn) {
                try { $conn->send($payload); } catch (Throwable $e) { /* ignore */ }
            }
        }
    }

    /**
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
        if (!$result) return [];
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

    // ------------------------------------------------------------------
    // Notifications channel
    // ------------------------------------------------------------------

    private function subscribeNotifications(ConnectionInterface $conn): void
    {
        /** @phpstan-ignore-next-line dynamic property */
        $login = (string)($conn->gdrcdUser ?? '');
        if ($login === '') return;
        $rid = $this->resourceId($conn);

        if (!isset($this->userConns[$login])) {
            $this->userConns[$login] = [];
        }
        $this->userConns[$login][$rid] = $conn;

        // Forza emit immediato (anche se uguale all'ultimo) per bootstrap.
        $payload = $this->computeNotifications($conn);
        $conn->send(json_encode([
            'type'                => 'notifications',
            'unread_pm'           => $payload['pm'],
            'unread_segnalazioni' => $payload['seg'],
            'latest_pm'           => $payload['latest_pm'],
            'latest_quest'        => $payload['latest_quest'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->notifLast[$login] = [
            'pm'           => $payload['pm'],
            'seg'          => $payload['seg'],
            'pm_id'        => $payload['latest_pm']['id'] ?? 0,
            'quest_id'     => $payload['latest_quest']['id'] ?? 0,
            'quest_status' => $payload['latest_quest']['status'] ?? '',
        ];
    }

    private function removeFromNotifications(ConnectionInterface $conn): void
    {
        $rid = $this->resourceId($conn);
        foreach ($this->userConns as $login => $conns) {
            if (isset($conns[$rid])) {
                unset($this->userConns[$login][$rid]);
                if (empty($this->userConns[$login])) {
                    unset($this->userConns[$login]);
                    unset($this->notifLast[$login]);
                }
            }
        }
    }

    public function pollNotifications(): void
    {
        if (empty($this->userConns)) return;

        foreach ($this->userConns as $login => $conns) {
            if (empty($conns)) continue;

            // Una conn qualunque dello stesso login: serve solo per leggere
            // permessi salvati onOpen (sono uguali per ogni conn dello stesso utente).
            $sample = null;
            foreach ($conns as $c) { $sample = $c; break; }
            if ($sample === null) continue;

            $data = $this->computeNotifications($sample);
            $last = $this->notifLast[$login] ?? [
                'pm' => -1, 'seg' => -1, 'pm_id' => -1, 'quest_id' => -1, 'quest_status' => '',
            ];

            $latestId       = $data['latest_pm']['id']    ?? 0;
            $latestQuestId  = $data['latest_quest']['id'] ?? 0;
            $latestQuestSt  = $data['latest_quest']['status'] ?? '';
            $changed = (
                $data['pm']    !== $last['pm']           ||
                $data['seg']   !== $last['seg']          ||
                $latestId      !== $last['pm_id']        ||
                $latestQuestId !== $last['quest_id']     ||
                $latestQuestSt !== $last['quest_status']
            );
            if (!$changed) continue;

            $this->notifLast[$login] = [
                'pm'           => $data['pm'],
                'seg'          => $data['seg'],
                'pm_id'        => $latestId,
                'quest_id'     => $latestQuestId,
                'quest_status' => $latestQuestSt,
            ];

            $payload = json_encode([
                'type'                => 'notifications',
                'unread_pm'           => $data['pm'],
                'unread_segnalazioni' => $data['seg'],
                'latest_pm'           => $data['latest_pm'],
                'latest_quest'        => $data['latest_quest'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($conns as $conn) {
                try { $conn->send($payload); } catch (Throwable $e) { /* ignore */ }
            }
        }
    }

    /**
     * Replica logica di api/notifications.inc.php per il login della conn,
     * piu' ultimo evento quest (assegnazione o cambio stato) per la corsia
     * "notifiche in-app delle quest".
     *
     * @return array{
     *     pm:int,
     *     seg:int,
     *     latest_pm:?array{id:int,from:string,subject:string},
     *     latest_quest:?array{id:int,id_quest:int,titolo:string,status:string,assegnata_il:string,conclusa_il:?string}
     * }
     */
    private function computeNotifications(ConnectionInterface $conn): array
    {
        /** @phpstan-ignore-next-line dynamic property */
        $login    = (string)($conn->gdrcdUser ?? '');
        /** @phpstan-ignore-next-line dynamic property */
        $permessi = (int)($conn->gdrcdPermessi ?? 0);

        $pm = 0;
        $latestPm = null;
        $checkPm  = ($GLOBALS['PARAMETERS']['mode']['check_messages'] ?? 'OFF') === 'ON';

        if ($checkPm) {
            $row = Db::preparedFetch(
                "SELECT COUNT(*) AS c FROM messaggi
                 WHERE destinatario = ?
                   AND destinatario_del = 0
                   AND letto = 0",
                's',
                [$login]
            );
            $pm = (int)($row['c'] ?? 0);

            if ($pm > 0) {
                $r = Db::preparedFetch(
                    "SELECT id, mittente, oggetto FROM messaggi
                     WHERE destinatario = ?
                       AND destinatario_del = 0
                       AND letto = 0
                     ORDER BY spedito DESC
                     LIMIT 1",
                    's',
                    [$login]
                );
                if (!empty($r)) {
                    $subj = (string)($r['oggetto'] ?? '');
                    if (mb_strlen($subj) > 80) {
                        $subj = mb_substr($subj, 0, 77) . '...';
                    }
                    $latestPm = [
                        'id'      => (int)$r['id'],
                        'from'    => (string)$r['mittente'],
                        'subject' => $subj,
                    ];
                }
            }
        }

        $seg = 0;
        if (defined('ESITI') && ESITI && $permessi >= ESITI_PERM) {
            if ($permessi >= FULL_PERM) {
                $r = Db::preparedFetch(
                    "SELECT COUNT(*) AS c FROM esiti
                     WHERE letto_master = 0
                       AND autore <> ?",
                    's',
                    [$login]
                );
            } else {
                $r = Db::preparedFetch(
                    "SELECT COUNT(e.id) AS c FROM esiti e
                     INNER JOIN blocco_esiti b ON b.id = e.id_blocco
                     WHERE e.letto_master = 0
                       AND e.autore <> ?
                       AND (b.master = '0' OR b.master = ?)",
                    'ss',
                    [$login, $login]
                );
            }
            $seg = (int)($r['c'] ?? 0);
        }

        // --- Ultima quest del PG (assegnazione o cambio stato) -----------
        return [
            'pm'           => $pm,
            'seg'          => $seg,
            'latest_pm'    => $latestPm,
            'latest_quest' => Quest::latestForPg($login),
        ];
    }

    // ------------------------------------------------------------------
    // Presenti channel
    // ------------------------------------------------------------------

    private function subscribePresenti(ConnectionInterface $conn): void
    {
        $rid = $this->resourceId($conn);
        $this->presentiConns[$rid] = $conn;

        // Bootstrap immediato sul subscribe (ignora dedup globale).
        $snapshot = $this->fetchPresentiSnapshot();
        $conn->send(json_encode([
            'type'   => 'presenti',
            'total'  => $snapshot['total'],
            'groups' => $snapshot['groups'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function removeFromPresenti(ConnectionInterface $conn): void
    {
        $rid = $this->resourceId($conn);
        if (isset($this->presentiConns[$rid])) {
            unset($this->presentiConns[$rid]);
        }
        if (empty($this->presentiConns)) {
            $this->presentiLastSig = '';
        }
    }

    public function pollPresenti(): void
    {
        if (empty($this->presentiConns)) return;

        $snapshot = $this->fetchPresentiSnapshot();
        $sig = md5(json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: '');
        if ($sig === $this->presentiLastSig) return;
        $this->presentiLastSig = $sig;

        $payload = json_encode([
            'type'   => 'presenti',
            'total'  => $snapshot['total'],
            'groups' => $snapshot['groups'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($this->presentiConns as $conn) {
            try { $conn->send($payload); } catch (Throwable $e) { /* ignore */ }
        }
    }

    /**
     * Replica la query/shape di api/presenti.inc.php senza il flag is_me
     * (il client lo calcola da data-login).
     *
     * @return array{total:int, groups:list<array<string,mixed>>}
     */
    private function fetchPresentiSnapshot(): array
    {
        $show_state = ($GLOBALS['PARAMETERS']['mode']['user_online_state'] ?? 'OFF') === 'ON';
        $mapwise    = ($GLOBALS['PARAMETERS']['mode']['mapwise_links']     ?? 'OFF') !== 'OFF';

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

        $invisible_label = $GLOBALS['MESSAGE']['status_pg']['invisible'][1] ?? 'Invisibili';

        $grouped    = [];
        $group_meta = [];

        if ($result) {
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

                $activity  = gdrcd_check_time($row['ora_entrata']);
                $just_in   = ($activity <= 2);
                $razza_lbl = $row['sing_' . $row['sesso']] ?? '';

                $pg = [
                    'nome'          => (string)$row['nome'],
                    'cognome'       => (string)($row['cognome'] ?? ''),
                    'permessi'      => (int)$row['permessi'],
                    'sesso'         => (string)$row['sesso'],
                    'razza_label'   => (string)$razza_lbl,
                    'razza_icon'    => (string)($row['icon'] ?: 'standard_razza.png'),
                    'disponibile'   => (int)$row['disponibile'],
                    'is_invisible'  => $is_invisible,
                    'is_me'         => false, // calcolato client side
                    'just_entered'  => $just_in,
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
        }

        $total  = 0;
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
                $count   = count($pgs);
                $total  += $count;
                $groups[] = [
                    'mappa'      => (string)$mappa_key,
                    'luogo'      => (string)$luogo_key,
                    'luogo_link' => $luogo_link,
                    'pgs'        => $pgs,
                ];
            }
        }

        return ['total' => $total, 'groups' => $groups];
    }

    // ------------------------------------------------------------------
    // Common
    // ------------------------------------------------------------------

    private function resourceId(ConnectionInterface $conn): int
    {
        /** @phpstan-ignore-next-line property may exist */
        if (isset($conn->resourceId)) {
            return (int)$conn->resourceId;
        }
        return spl_object_id($conn);
    }
}
