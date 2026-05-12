<?php
declare(strict_types=1);

/**
 * Db - Wrapper tipizzato attorno alle funzioni mysqli del core.
 *
 * Fornisce una API moderna e leggibile in alternativa alla storica
 * gdrcd_query($sql, $mode) multi-modale, che continua a funzionare
 * invariata per i ~100 call site esistenti. Questo refactor e' additivo:
 * non sostituisce ne' modifica gdrcd_query() ne' i suoi chiamanti.
 *
 * La classe riusa la connessione gestita da gdrcd_connect() (singleton
 * statico interno) e in caso di errore invoca gdrcd_log_error() per il
 * log strutturato e gdrcd_mysql_error() per mantenere il comportamento
 * legacy (output HTML / die()).
 *
 * Esempio d'uso idiomatico:
 *
 *     $row  = Db::fetch("SELECT * FROM personaggio WHERE id=" . (int)$id);
 *     $rows = Db::fetchAll("SELECT id, nome FROM personaggio ORDER BY nome");
 *     $name = Db::value("SELECT nome FROM personaggio WHERE id=" . (int)$id);
 *     Db::execute("UPDATE personaggio SET online=1 WHERE id=" . (int)$id);
 *     $newId = Db::lastInsertId();
 *     $safe  = Db::escape($_POST['nome']);
 *
 * Comportamento ai bordi:
 *  - fetch()    : ritorna null se la SELECT non produce righe.
 *  - fetchAll() : ritorna array() vuoto se la SELECT non produce righe.
 *  - value()    : ritorna null se la SELECT non produce righe.
 *  - execute()  : ritorna bool (true successo, false errore gestito).
 *  - In caso di SQL fallito viene eseguito gdrcd_mysql_error() che,
 *    coerentemente col comportamento legacy, puo' terminare lo script.
 *
 * @version 1.0
 * @author  GDRCD core
 */
class Db
{
    /**
     * Inizializza/ritorna la connessione mysqli usando gdrcd_connect().
     *
     * @return mysqli|false la risorsa mysqli condivisa.
     */
    public static function connect()
    {
        return gdrcd_connect();
    }

    /**
     * Esegue una qualsiasi query SQL.
     * Le SELECT (e in generale le query che producono un resultset)
     * ritornano la risorsa mysqli_result; le altre ritornano bool.
     *
     * @param string $sql
     * @return mysqli_result|bool
     */
    public static function query(string $sql)
    {
        $db_link = self::connect();
        $result  = mysqli_query($db_link, $sql);

        if ($result === false) {
            self::handleError($sql);
            return false;
        }

        return $result;
    }

    /**
     * Esegue una SELECT e ritorna la prima riga come array associativo,
     * oppure null se il resultset e' vuoto.
     *
     * @param string $sql
     * @return array|null
     */
    public static function fetch(string $sql): ?array
    {
        $result = self::query($sql);

        if (!($result instanceof mysqli_result)) {
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        return $row !== null ? $row : null;
    }

    /**
     * Esegue una SELECT e ritorna tutte le righe come array di array
     * associativi. Array vuoto se non ci sono risultati.
     *
     * @param string $sql
     * @return array
     */
    public static function fetchAll(string $sql): array
    {
        $result = self::query($sql);

        if (!($result instanceof mysqli_result)) {
            return array();
        }

        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);

        return $rows;
    }

    /**
     * Esegue una SELECT e ritorna la prima colonna della prima riga
     * (uno scalare). Null se il resultset e' vuoto.
     *
     * @param string $sql
     * @return mixed|null
     */
    public static function value(string $sql)
    {
        $result = self::query($sql);

        if (!($result instanceof mysqli_result)) {
            return null;
        }

        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);

        if (!is_array($row) || !array_key_exists(0, $row)) {
            return null;
        }

        return $row[0];
    }

    /**
     * Esegue una query di modifica (INSERT, UPDATE, DELETE, DDL...).
     * Ritorna true in caso di successo, false in caso di errore gestito.
     *
     * @param string $sql
     * @return bool
     */
    public static function execute(string $sql): bool
    {
        $db_link = self::connect();
        $ok      = mysqli_query($db_link, $sql);

        if ($ok === false) {
            self::handleError($sql);
            return false;
        }

        // Le query che producono un resultset ritornano una risorsa,
        // qui ci interessa solo il successo dell'esecuzione.
        if ($ok instanceof mysqli_result) {
            mysqli_free_result($ok);
        }

        return true;
    }

    /**
     * Numero di righe interessate dall'ultima query INSERT/UPDATE/DELETE.
     *
     * @return int
     */
    public static function affected(): int
    {
        return (int)mysqli_affected_rows(self::connect());
    }

    /**
     * ID generato dall'ultima INSERT su colonna AUTO_INCREMENT.
     *
     * @return int
     */
    public static function lastInsertId(): int
    {
        return (int)mysqli_insert_id(self::connect());
    }

    /**
     * Escape di una stringa per inclusione sicura in una query SQL.
     * Wrapper attorno a mysqli_real_escape_string sulla connessione condivisa.
     *
     * @param string $value
     * @return string
     */
    public static function escape(string $value): string
    {
        return mysqli_real_escape_string(self::connect(), $value);
    }

    /**
     * Esegue una query con prepared statement.
     *
     * Per SELECT ritorna la mysqli_result (o false se la query non
     * produce un resultset utilizzabile - es. INSERT/UPDATE/DELETE).
     * Per le altre query restituisce true in caso di successo.
     *
     * I parametri della stringa SQL devono usare il placeholder '?'
     * mysqli classico; $types e' la stringa di binding (es. 'sii').
     *
     * @param string $sql    SQL parametrizzato con '?'
     * @param string $types  stringa di tipo per bind_param ('i','d','s','b'...)
     * @param array  $params valori da bindare, in ordine
     * @return mysqli_result|bool
     */
    public static function prepared(string $sql, string $types, array $params)
    {
        $db_link = self::connect();
        $stmt    = mysqli_prepare($db_link, $sql);

        if ($stmt === false) {
            self::handleError($sql);
            return false;
        }

        if ($types !== '' && !empty($params)) {
            // bind_param vuole riferimenti: copia in array indicizzato e
            // poi prende le ref a quelle posizioni.
            $values = array_values($params);
            $refs   = array();
            foreach ($values as $k => $v) {
                $refs[$k] = &$values[$k];
            }
            array_unshift($refs, $types);
            call_user_func_array(array($stmt, 'bind_param'), $refs);
        }

        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            self::handleError($sql . ' [' . $err . ']');
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);

        // Per INSERT/UPDATE/DELETE mysqli_stmt_get_result ritorna false:
        // in quel caso restituiamo true per coerenza con execute().
        if ($result === false) {
            return true;
        }

        return $result;
    }

    /**
     * Esegue una SELECT preparata e ritorna la prima riga associativa,
     * o null se vuota.
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return array|null
     */
    public static function preparedFetch(string $sql, string $types, array $params): ?array
    {
        $result = self::prepared($sql, $types, $params);
        if (!($result instanceof mysqli_result)) {
            return null;
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row !== null ? $row : null;
    }

    /**
     * Esegue una SELECT preparata e ritorna tutte le righe associative.
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return array
     */
    public static function preparedFetchAll(string $sql, string $types, array $params): array
    {
        $result = self::prepared($sql, $types, $params);
        if (!($result instanceof mysqli_result)) {
            return array();
        }
        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }

    /**
     * Esegue una INSERT/UPDATE/DELETE preparata.
     * Ritorna true in caso di successo, false in caso di errore gestito.
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return bool
     */
    public static function preparedExecute(string $sql, string $types, array $params): bool
    {
        $result = self::prepared($sql, $types, $params);
        if ($result instanceof mysqli_result) {
            // Inatteso ma non drammatico: libera la risorsa.
            mysqli_free_result($result);
            return true;
        }
        return $result === true;
    }

    /**
     * Esegue una INSERT/UPDATE/DELETE preparata e ritorna il numero di
     * righe interessate (0 se la query non ha modificato nulla, -1 errore).
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return int
     */
    public static function preparedAffected(string $sql, string $types, array $params): int
    {
        $ok = self::preparedExecute($sql, $types, $params);
        if (!$ok) {
            return -1;
        }
        return (int)mysqli_affected_rows(self::connect());
    }

    /**
     * Gestione errore: log strutturato + comportamento legacy.
     *
     * @param string $sql query che ha generato l'errore
     * @return void
     */
    private static function handleError(string $sql): void
    {
        if (function_exists('gdrcd_log_error')) {
            $db_link = self::connect();
            gdrcd_log_error('Db query failed', array(
                'errno' => @mysqli_errno($db_link),
                'error' => @mysqli_error($db_link),
                'query' => $sql,
            ));
        }

        // Mantiene comportamento legacy: gdrcd_mysql_error() esegue
        // il logging e ritorna l'HTML descrittivo. Coerentemente con
        // gdrcd_query() viene invocato die() per terminare la richiesta.
        if (function_exists('gdrcd_mysql_error')) {
            die(gdrcd_mysql_error($sql));
        }
    }
}
