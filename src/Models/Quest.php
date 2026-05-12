<?php
declare(strict_types=1);

namespace GDRCD\Models;

use Db;

/**
 * Model Quest — tutta la logica DB del sistema quest.
 *
 * Le view (pages/gestione/quests.inc.php, pages/scheda_quest.inc.php)
 * NON devono contenere SQL diretto: usano questa classe.
 *
 * @see docs/quests.md
 * @see db_versions/2026051119_GDRCDQuests.php
 */
final class Quest
{
    public const STATUSES = ['attiva', 'completata', 'fallita'];

    // ------------------------------------------------------------------
    // Definizione quest (tabella `quest`)
    // ------------------------------------------------------------------

    /**
     * Conteggi per tab (totale / attive / disattivate).
     *
     * @return array{all:int,active:int,inactive:int}
     */
    public static function counts(): array
    {
        $row = Db::preparedFetch(
            "SELECT SUM(attiva = 1) AS active_n,
                    SUM(attiva = 0) AS inactive_n,
                    COUNT(*)        AS all_n
             FROM quest",
            '',
            []
        );
        return [
            'all'      => (int)($row['all_n']      ?? 0),
            'active'   => (int)($row['active_n']   ?? 0),
            'inactive' => (int)($row['inactive_n'] ?? 0),
        ];
    }

    /**
     * Elenca quest con contatori assegnati / attive.
     *
     * @param string $tab 'all' | 'active' | 'inactive'
     * @return list<array<string,mixed>>
     */
    public static function listFiltered(string $tab = 'all', int $limit = 500): array
    {
        $where = '';
        if ($tab === 'active')   $where = ' WHERE q.attiva = 1';
        if ($tab === 'inactive') $where = ' WHERE q.attiva = 0';

        return Db::preparedFetchAll(
            "SELECT q.id_quest, q.titolo, q.descrizione, q.obiettivo, q.ricompensa,
                    q.autore, q.creata_il, q.attiva,
                    (SELECT COUNT(*) FROM clgquestpg cqp WHERE cqp.id_quest = q.id_quest) AS n_assegnati,
                    (SELECT COUNT(*) FROM clgquestpg cqp WHERE cqp.id_quest = q.id_quest AND cqp.status = 'attiva') AS n_attive
             FROM quest q"
             . $where .
             " ORDER BY q.creata_il DESC LIMIT " . (int)$limit,
            '',
            []
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::preparedFetch(
            "SELECT id_quest, titolo, descrizione, obiettivo, ricompensa, autore, creata_il, attiva
             FROM quest WHERE id_quest = ? LIMIT 1",
            'i',
            [$id]
        );
    }

    public static function create(
        string $titolo,
        string $descrizione,
        string $obiettivo,
        string $ricompensa,
        string $autore
    ): bool {
        return Db::preparedExecute(
            "INSERT INTO quest (titolo, descrizione, obiettivo, ricompensa, autore)
             VALUES (?, ?, ?, ?, ?)",
            'sssss',
            [$titolo, $descrizione, $obiettivo, $ricompensa, $autore]
        );
    }

    public static function update(
        int $id,
        string $titolo,
        string $descrizione,
        string $obiettivo,
        string $ricompensa
    ): bool {
        return Db::preparedExecute(
            "UPDATE quest SET titolo = ?, descrizione = ?, obiettivo = ?, ricompensa = ?
             WHERE id_quest = ?",
            'ssssi',
            [$titolo, $descrizione, $obiettivo, $ricompensa, $id]
        );
    }

    /** Toggle attiva/disattivata. */
    public static function toggle(int $id): bool
    {
        return Db::preparedExecute(
            "UPDATE quest SET attiva = 1 - attiva WHERE id_quest = ?",
            'i',
            [$id]
        );
    }

    // ------------------------------------------------------------------
    // Assegnazioni (tabella `clgquestpg`)
    // ------------------------------------------------------------------

    /**
     * Assegna quest a un PG. INSERT IGNORE: se esiste gia' la coppia
     * (id_quest, personaggio) non duplica.
     *
     * @return int numero righe affette (0 = gia' esistente).
     */
    public static function assign(int $idQuest, string $pg, string $note = ''): int
    {
        return Db::preparedAffected(
            "INSERT IGNORE INTO clgquestpg (id_quest, personaggio, status, note)
             VALUES (?, ?, 'attiva', ?)",
            'iss',
            [$idQuest, $pg, $note]
        );
    }

    /**
     * Cambia stato di un'assegnazione. Aggiorna anche `conclusa_il`
     * (NULL se status='attiva', NOW() altrimenti).
     */
    public static function setStatus(int $rowId, string $status, string $note = ''): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        $setConclusa = ($status === 'attiva')
            ? 'conclusa_il = NULL'
            : 'conclusa_il = NOW()';
        return Db::preparedExecute(
            "UPDATE clgquestpg SET status = ?, " . $setConclusa . ", note = ?
             WHERE id = ?",
            'ssi',
            [$status, $note, $rowId]
        );
    }

    public static function unassign(int $rowId): bool
    {
        return Db::preparedExecute(
            "DELETE FROM clgquestpg WHERE id = ?",
            'i',
            [$rowId]
        );
    }

    /**
     * Lista assegnatari di una quest, ordinati per status (attive prima)
     * e poi per data assegnazione decrescente.
     *
     * @return list<array<string,mixed>>
     */
    public static function assignees(int $idQuest): array
    {
        return Db::preparedFetchAll(
            "SELECT id, personaggio, status, assegnata_il, conclusa_il, note
             FROM clgquestpg WHERE id_quest = ?
             ORDER BY (status = 'attiva') DESC, assegnata_il DESC",
            'i',
            [$idQuest]
        );
    }

    /**
     * Quest assegnate a un PG, raggruppate per stato.
     *
     * @return array{active: list<array<string,mixed>>, done: list<array<string,mixed>>}
     */
    public static function forPg(string $pg): array
    {
        $active = Db::preparedFetchAll(
            "SELECT q.id_quest, q.titolo, q.descrizione, q.obiettivo, q.ricompensa,
                    q.autore, cqp.assegnata_il, cqp.note
             FROM clgquestpg cqp
             INNER JOIN quest q ON q.id_quest = cqp.id_quest
             WHERE cqp.personaggio = ?
               AND cqp.status = 'attiva'
             ORDER BY cqp.assegnata_il DESC",
            's',
            [$pg]
        );
        $done = Db::preparedFetchAll(
            "SELECT q.id_quest, q.titolo, q.descrizione, q.obiettivo, q.ricompensa,
                    q.autore, cqp.assegnata_il, cqp.conclusa_il, cqp.status, cqp.note
             FROM clgquestpg cqp
             INNER JOIN quest q ON q.id_quest = cqp.id_quest
             WHERE cqp.personaggio = ?
               AND cqp.status IN ('completata','fallita')
             ORDER BY cqp.conclusa_il DESC, cqp.assegnata_il DESC",
            's',
            [$pg]
        );
        return ['active' => $active, 'done' => $done];
    }

    /**
     * Ultima assegnazione del PG (per WS notification diff).
     *
     * @return array{id:int,id_quest:int,titolo:string,status:string,assegnata_il:string,conclusa_il:?string}|null
     */
    public static function latestForPg(string $pg): ?array
    {
        $row = Db::preparedFetch(
            "SELECT cqp.id, cqp.id_quest, cqp.status, cqp.assegnata_il, cqp.conclusa_il, q.titolo
             FROM clgquestpg cqp
             INNER JOIN quest q ON q.id_quest = cqp.id_quest
             WHERE cqp.personaggio = ?
             ORDER BY GREATEST(cqp.assegnata_il, IFNULL(cqp.conclusa_il, cqp.assegnata_il)) DESC,
                      cqp.id DESC
             LIMIT 1",
            's',
            [$pg]
        );
        if (empty($row)) return null;
        return [
            'id'           => (int)$row['id'],
            'id_quest'     => (int)$row['id_quest'],
            'titolo'       => (string)$row['titolo'],
            'status'       => (string)$row['status'],
            'assegnata_il' => (string)$row['assegnata_il'],
            'conclusa_il'  => isset($row['conclusa_il']) ? (string)$row['conclusa_il'] : null,
        ];
    }
}
