<?php
/**
 * Funzioni di core di gdrcd
 * Il file contiene una revisione del core originario introdotto in GDRCD5
 * @version 5.4
 * @author Breaker
 */

/**
 * Funzionalità di dialogo col database
 * Set di funzioni da core che implementano il dialogo gestito col db
 */

/**
 * Connettore al database MySql
 */
function gdrcd_connect()
{
    static $db_link = false;

    if ($db_link === false) {
        $db_user = $GLOBALS['PARAMETERS']['database']['username'];
        $db_pass = $GLOBALS['PARAMETERS']['database']['password'];
        $db_name = $GLOBALS['PARAMETERS']['database']['database_name'];
        $db_host = $GLOBALS['PARAMETERS']['database']['url'];
        $db_error = $GLOBALS['MESSAGE']['error']['db_not_found'];

        #$db = mysql_connect($db_host, $db_user, $db_pass)or die(gdrcd_mysql_error());
        #mysql_select_db($db_name)or die(gdrcd_mysql_error($db_error));

        $db_link = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

        mysqli_set_charset($db_link, "utf8");

        if (mysqli_connect_errno()) {
            gdrcd_mysql_error($db_error);
        }
    }
    return $db_link;
}

/**
 * Chiusura della connessione col db MySql
 * @param resource $db : una connessione mysqli
 */
function gdrcd_close_connection($db)
{
    // Chiudo la connessione al database
    if(is_resource($db) && get_resource_type($db)==='mysql link') mysqli_close($db);
}

/**
 * Gestore delle query, offre una basilare astrazione del database per la maggior parte delle funzionalità del database più usate.
 * @param string|mysqli_result $sql : il codice SQL da inviare al database o una risorsa risultato di MySqli
 * @param string $mode : La modalità con cui eseguire la query. Default "query"
 * Modalità accettate:
 *  query: esegue la query e ritorna come risultato la prima riga del resultset
 *  result: esegue la query e ritorna la risorsa MySql associata al risultato
 *  num_rows: accetta come parametro una risorsa mysqli e ritorna il numero di righe nel resultset
 *  fetch: accetta come parametro una risorsa mysqli e ritorna il successivo risultato dal resultset come array
 *  object: uguale a fetch, eccetto che ritorna un oggetto al posto di un array
 *  free: libera la memoria occupata dalla risorsa mysqli passata in $sql
 *  last_id: ritorna l'id del record generato dall'ultima query, se non era una INSERT o UPDATE ritorna 0. In questo caso $sql non viene considerato
 *  affected: ritorna il numero di record toccati dall'ultima query (INSERT, UPDATE, DELETE o SELECT). In questo caso $sql non viene considerato
 * @return un booleano in caso di esecuzione di query non SELECT e modalità 'query'. Altrimenti ritorna come specificato nella descrizione di $mode
 */
function gdrcd_query($sql, $mode = 'query', $throwOnError = false)
{
    $db_link = gdrcd_connect();

    switch (strtolower(trim($mode))) {
        case 'query':
            switch (strtoupper(substr(trim($sql), 0, 6))) {
                case 'SELECT':
                    $result = mysqli_query($db_link, $sql);
                    if($result === false){
                        if($throwOnError){
                            throw new Exception("Query DB Fallita: " . $sql . "\n\n" . mysqli_error($db_link));
                        }
                        else{
                            die(gdrcd_mysql_error($sql));
                        }
                    }
                    $row = mysqli_fetch_array($result, MYSQLI_BOTH);
                    mysqli_free_result($result);

                    return $row;
                    break;
                default:
                    $result = mysqli_query($db_link, $sql);
                    if($result === false){
                        if($throwOnError){
                            throw new Exception("Query DB Fallita: " . $sql . "\n\n" . mysqli_error($db_link));
                        }
                        else{
                            die(gdrcd_mysql_error($sql));
                        }
                    }
                    return $result;
                    break;
            }

        case 'result':
            $result = mysqli_query($db_link, $sql);
            if($result === false){
                if($throwOnError){
                    throw new Exception("Query DB Fallita: " . $sql . "\n\n" . mysqli_error($db_link));
                }
                else{
                    die(gdrcd_mysql_error($sql));
                }
            }

            return $result;
            break;

        case 'num_rows':
            return (int)mysqli_num_rows($sql);
            break;

        case 'fetch':
            return mysqli_fetch_array($sql, MYSQLI_BOTH);
            break;

        case 'assoc':
            return mysqli_fetch_array($sql, MYSQLI_ASSOC);
            break;

        case 'object':
            return mysqli_fetch_object($sql);
            break;

        case 'free':
            return mysqli_free_result($sql);
            break;

        case 'last_id':
            return mysqli_insert_id($db_link);
            break;

        case 'affected':
            return (int)mysqli_affected_rows($db_link);
            break;
    }
}


/*
    * Prepared Statements
    * @param string $sql: il codice SQL da inviare al database
    * @param array $binds: array dei parametri associati alla query
    *
    * E' obbligatorio specificare nell'indice zero dell'array binds i tipi delle variabili che si stanno immettendo nella query
    * Tali tipi sono i seguenti:
    * i      corrispondente ai valori integer
    * d     corrispondente ai valori float/double
    * s     corrispondente alle stringhe
    * b     corrispondende a valori di tipo blob
    *
    * @return mysqli_result
*/
function gdrcd_stmt($sql, $binds = array())
{
    $db_link = gdrcd_connect();

    if ($stmt = mysqli_prepare($db_link, $sql)) {

        if (!empty($binds)) {

            #> E' necessario referenziare ogni parametro da passare alla query
            #> MySqli è suscettibile in proposito.
            $ref = array();

            foreach ($binds as $k => $v) {
                if ($k > 0) {
                    $ref[$k] = &$binds[$k];
                } else {
                    $ref[$k] = $v;
                }
            }

            array_unshift($ref, $stmt);
            call_user_func_array('mysqli_stmt_bind_param', $ref);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stmtError = mysqli_stmt_error($stmt);

        if (!empty($stmtError))
            die(gdrcd_mysql_error($stmtError));

        mysqli_stmt_close($stmt);

        return $result;

    } else {
        die(gdrcd_mysql_error('Failed when creating the statement.'));
    }
}


/**
 * Helper idiomatici per la nuova API Db::*.
 *
 * Forniscono short-call procedurali ai metodi statici di Db, utili per
 * il codice nuovo senza rompere il vecchio (gdrcd_query() resta com'e').
 * Vedi includes/Db.class.php per documentazione completa.
 */

/**
 * Esegue una SELECT e ritorna la prima riga associativa, o null.
 * @param string $sql
 * @return array|null
 */
function gdrcd_db_fetch($sql)
{
    return Db::fetch($sql);
}

/**
 * Esegue una SELECT e ritorna tutte le righe associative.
 * @param string $sql
 * @return array
 */
function gdrcd_db_all($sql)
{
    return Db::fetchAll($sql);
}

/**
 * Esegue una SELECT e ritorna il primo valore della prima riga.
 * @param string $sql
 * @return mixed|null
 */
function gdrcd_db_value($sql)
{
    return Db::value($sql);
}

/**
 * Esegue INSERT/UPDATE/DELETE; ritorna bool successo.
 * @param string $sql
 * @return bool
 */
function gdrcd_db_exec($sql)
{
    return Db::execute($sql);
}

/**
 * Escape sicuro di una stringa per query SQL.
 * @param string $v
 * @return string
 */
function gdrcd_db_escape($v)
{
    return Db::escape((string)$v);
}


/**
 * Funzione di recupero delle colonne e della loro dichiarazione della tabella specificata.
 * Si usa per la verifica dell'aggiornamento db da vecchie versioni di gdrcd5
 * @param string $table : il nome della tabella da controllare
 * @return un oggetto contenente la descrizione della tabella negli attributi
 */
function gdrcd_check_tables($table)
{
    $result = gdrcd_query("SELECT * FROM $table LIMIT 1", 'result');
    $describe = gdrcd_query("SHOW COLUMNS FROM $table", 'result');

    $i = 0;
    $output = [];

    while ($field = gdrcd_query($describe, 'object')) {
        $defInfo = mysqli_fetch_field_direct($result, $i);

        $field->auto_increment = (strpos($field->Extra, 'auto_increment') === false ? 0 : 1);
        $field->definition = $field->Type;

        if ($field->Null == 'NO' && $field->Key != 'PRI') {
            $field->definition .= ' NOT NULL';
        }

        if ($field->Default) {
            $field->definition .= " DEFAULT '" . mysqli_real_escape_string(gdrcd_connect(), $field->Default) . "'";
        }

        if ($field->auto_increment) {
            $field->definition .= ' AUTO_INCREMENT';
        }

        switch ($field->Key) {
            case 'PRI':
                $field->definition .= ' PRIMARY KEY';
                break;
            case 'UNI':
                $field->definition .= ' UNIQUE KEY';
                break;
            case 'MUL':
                $field->definition .= ' KEY';
                break;
        }

        $field->len = $defInfo->length;
        $output[$field->Field] = $field;
        ++$i;

        unset($defInfo);
    }
    gdrcd_query($describe, 'free');

    return $output;
}

/**
 * Gestione degli errori tornati dalle query
 * @param string $details : una descrizione dell'errore avvenuto
 * @return una stringa HTML che descrive l'errore riscontrato
 */
function gdrcd_mysql_error($details = false)
{
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 50);

    $history = '';
    $base    = array('file' => '?', 'line' => '?');
    foreach($backtrace as $v) {
        if($v['function'] == 'gdrcd_query') {
            $base = $v;
        }
        $history .= '<strong>FILE: </strong>: ' . $v['file'] . ' - ';
        $history .= '<strong>LINE: </strong>: ' . $v['line'] . '</br />';
    }

    $mysqli_errno = @mysqli_errno(gdrcd_connect());
    $mysqli_error = @mysqli_error(gdrcd_connect());

    // Log strutturato in parallelo al messaggio HTML mostrato/restituito.
    if (function_exists('gdrcd_log_error')) {
        gdrcd_log_error('MySQL error', array(
            'errno' => $mysqli_errno,
            'error' => $mysqli_error,
            'query' => $details !== false ? (string)$details : null,
            'file'  => $base['file'] ?? null,
            'line'  => $base['line'] ?? null,
        ));
    }

    $error_msg  = '<div class="error mysql">';
    $error_msg .= '<strong>GDRCD MySQLi Error</strong>:</br>';
    if ($details !== false) {
        $error_msg .= '<strong>QUERY: </strong>: ' . $details . '</br>';
    }
    $error_msg .= '<strong>ERROR [' . $mysqli_errno . ']</strong>: ' . $mysqli_error .'<br />';
    $error_msg .= '<strong>FILE: </strong>: ' . $base['file'] . ' - ';
    $error_msg .= '<strong>LINE: </strong>: ' . $base['line'] . '<br />';
    $error_msg .= '<details>';
    $error_msg .= '<summary>Dettagli</summary>';
    $error_msg .= $history;
    $error_msg .= '</details>';
    $error_msg .= '</div>';
    return $error_msg;
}

/**
 * Funzionalità di escape
 * Set di funzioni escape per filtrare i possibili contenuti introdotti da un utente ;-)
 */

/**
 * Funzione di hashing delle password (LEGACY).
 * Mantiene la compatibilità con l'algoritmo phpass per gli hash già presenti in DB.
 * Per i nuovi hash usare gdrcd_password_hash().
 * @param string $str : la password o stringa di cui calcolare l'hash
 * @return string l'hash phpass calcolato a partire da $str
 */
function gdrcd_encript($str)
{
    require_once(dirname(__FILE__) . '/PasswordHash.php');
    $hasher = new PasswordHash(8, true);

    return $hasher->HashPassword($str);
}

/**
 * Verifica una password/stringa rispetto ad un hash memorizzato.
 * Supporta sia il nuovo formato bcrypt sia il vecchio formato phpass (gdrcd_encript)
 * per garantire la retrocompatibilità degli hash già presenti in database.
 * @param string $pass   la stringa in chiaro da verificare
 * @param string $stored l'hash memorizzato
 * @return bool true se la stringa corrisponde all'hash, false altrimenti
 */
function gdrcd_password_check($pass, $stored)
{
    if (!is_string($stored) || $stored === '') {
        return false;
    }

    $info = password_get_info($stored);
    if (!empty($info['algo'])) {
        // Hash moderno gestito da password_hash() (bcrypt, argon2, ...)
        return password_verify($pass, $stored);
    }

    // Fallback su phpass per gli hash legacy
    require_once(dirname(__FILE__) . '/PasswordHash.php');
    $hasher = new PasswordHash(8, true);
    return $hasher->CheckPassword($pass, $stored);
}

/**
 * Calcola un hash bcrypt per la password fornita.
 * @param string $plain password in chiaro
 * @return string hash bcrypt pronto per essere memorizzato in DB
 */
function gdrcd_password_hash($plain)
{
    return password_hash($plain, PASSWORD_BCRYPT);
}

/**
 * Verifica una password rispetto al suo hash memorizzato.
 * Alias semanticamente più chiaro di gdrcd_password_check(); supporta i nuovi
 * hash bcrypt e mantiene la retrocompatibilità con gli hash phpass legacy.
 * @param string $plain       la password in chiaro
 * @param string $stored_hash l'hash memorizzato
 * @return bool true se la password è valida
 */
function gdrcd_password_verify($plain, $stored_hash)
{
    return gdrcd_password_check($plain, $stored_hash);
}

/**
 * Indica se l'hash memorizzato deve essere rigenerato (es. perchè in formato legacy
 * oppure perchè i parametri di costo bcrypt sono cambiati).
 * @param string $stored_hash hash attualmente memorizzato in DB
 * @return bool
 */
function gdrcd_password_needs_rehash($stored_hash)
{
    if (!is_string($stored_hash) || $stored_hash === '') {
        return true;
    }
    $info = password_get_info($stored_hash);
    if (empty($info['algo'])) {
        // Hash non riconosciuto da password_hash() (tipicamente phpass): da rigenerare.
        return true;
    }
    return password_needs_rehash($stored_hash, PASSWORD_BCRYPT);
}

/**
 * TODO Controllo della validità della password
 * Funzione work in progress, da implementare.
 * Deve essere disabilitabile da config
 * Funzionalità da ON/OFF:
 * - numero di caratteri minimo scelto dall'utente
 * - non accettazione di password contenenti lettere accentate
 * - non accettazione di password troppo semplici (ad esempio uguali al nickname del personaggio)
 * @param string $str : la password da controllare
 * @return true se la password è valida, false altrimenti
 */
function gdrcd_check_pass($str)
{
    return true;
}

/**
 * Funzione di filtraggio di codici malevoli negli input utente
 * @param string $what : modalità da utilizzare per controllare la stringa. Sono opzioni valide: in o get, num, out, addslashes, email, includes
 * @param string $str : la stringa da controllare
 * @return una versione filtrata di $str
 */
function gdrcd_filter($what, $str)
{
    switch (strtolower($what)) {
        case 'in':
        case 'get':
            $str = addslashes(str_replace('\\', '', $str));
            break;

        case 'num':
            $str = (int)$str;
            break;

        case 'out':
            $str = gdrcd_html_filter(htmlentities($str, ENT_QUOTES, "UTF-8"));
            break;

        case 'addslashes':
            $str = addslashes($str);
            break;

        case 'email':
            $str = (preg_match("#^[a-z0-9._-]+@[a-z0-9._-]+\.[a-z]{2,4}$#is", $str)) ? $str : false;
            break;

        case 'includes':
            $str = (preg_match("#[^:]#is")) ? htmlentities($str, ENT_QUOTES) : false;
            break;

        case 'url':
            $str = urlencode($str);
            break;

        case 'fullurl':
            $str = filter_var(str_replace(' ', '%20', $str), FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);
            break;
    }

    return $str;
}

/**
 * Funzioni di alias per gdrcd_filter()
 */
function gdrcd_filter_in($str)
{
    return gdrcd_filter('in', $str);
}

function gdrcd_filter_out($str)
{
    return gdrcd_filter('out', $str);
}

function gdrcd_filter_get($str)
{
    return gdrcd_filter('get', $str);
}

function gdrcd_filter_num($str)
{
    return gdrcd_filter('num', $str);
}

function gdrcd_filter_addslashes($str)
{
    return gdrcd_filter('addslashes', $str);
}

function gdrcd_filter_email($str)
{
    return gdrcd_filter('email', $str);
}

function gdrcd_filter_includes($str)
{
    return gdrcd_filter('includes', $str);
}

function gdrcd_filter_url($str)
{
    return gdrcd_filter('url', $str);
}

/**
 * Funzione basilare di filtraggio degli elementi pericolosi in html
 * Serve a consentire l'uso di html e css in sicurezza nelle zone editabili della scheda
 * Il livello di filtraggio viene controllato da config: $PARAMETERS['settings']['html']
 * @param string $str : la stringa da filtrare
 * @return $str con gli elementi illegali sosituiti con una stringa di errore
 */
function gdrcd_html_filter($str)
{
    $notAllowed = [
        "#<script(.*?)>(.*?)</script>#is" => "Script non consentiti",
        "#(<iframe.*?\/?>.*?(<\/iframe>)?)#is" => "Frame non consentiti",
        "#(<object.*?>.*?(<\/object>)?)#is" => "Contenuti multimediali non consentiti",
        "#(<embed.*?\/?>.*?(<\/embed>)?)#is" => "Contenuti multimediali non consentiti",
        "#\bon([a-z]*?)=(['|\"])(.*?)\\2#mi" => " ",
        "#(javascript:[^\s\"']+)#is" => ""
    ];

    if ($GLOBALS['PARAMETERS']['settings']['html'] == HTML_FILTER_HIGH) {
        $notAllowed = array_merge($notAllowed, [
            "#(<img.*?\/?>)#is" => "Immagini non consentite",
            "#(url\(.*?\))#is" => "none",
        ]);
    }

    return preg_replace(array_keys($notAllowed), array_values($notAllowed), $str);
}

/**
 * Controlli di routine di gdrcd sui personaggi
 * Set di funzione per semplificare controlli frequenti sui personaggi nell'engine
 */

/**
 * Check validità della sessione utente
 */
function gdrcd_controllo_sessione()
{
    if (empty($_SESSION['login'])) {
        $msg  = $GLOBALS['MESSAGE']['error']['session_expired'];
        $hint = $GLOBALS['MESSAGE']['warning']['please_login_again'];
        $url  = $GLOBALS['PARAMETERS']['info']['site_url'];
        echo '<div class="gdrcd-shell"><div class="gdrcd-container-sm">'
           . '<div class="gdrcd-card"><div class="gdrcd-card-body text-center space-y-4 py-8">'
           . '<span class="gdrcd-icon-circle bg-gdrcd-error-soft text-gdrcd-error border-red-200">'
           . '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
           . '</span>'
           . '<h2 class="gdrcd-h2">' . htmlspecialchars($msg) . '</h2>'
           . '<p class="gdrcd-muted">' . htmlspecialchars($hint) . '</p>'
           . '<a class="gdrcd-btn-primary" href="' . htmlspecialchars($url) . '">Homepage</a>'
           . '</div></div></div></div>';
        die();
    }
}

/**
 * Controlla se l'utente è esiliato o meno
 * @param string $pg : il nome del pg da ricercare
 * @return true se il pg è esiliato, false altrimenti
 */
function gdrcd_controllo_esilio($pg)
{
    $exiled = gdrcd_query("SELECT autore_esilio, esilio, motivo_esilio FROM personaggio WHERE nome='" . gdrcd_filter('in', $pg) . "' LIMIT 1");

    if (strtotime($exiled['esilio']) > time()) {
        echo '<div class="error">', gdrcd_filter_out($pg), ' ', gdrcd_filter_out($GLOBALS['MESSAGE']['warning']['character_exiled']), ' ', gdrcd_format_date($exiled['esilio']), ' (', $exiled['motivo_esilio'], ' - ', $exiled['autore_esilio'], ')</div>';

        return true;
    }

    return false;
}

/**
 * Controlla se l'utente possiede i permessi indicati
 * @param string $permesso : il permesso da controllare
 * @return true se il pg possiede i permessi, false altrimenti
 */
function gdrcd_controllo_permessi($permesso)
{
    return (bool)$_SESSION['permessi'] >= $permesso;
}

/**
 * Funzione controllo permessi forum
 * @param int $tipo
 * @param mixed $proprietari
 * @return bool
 */
function gdrcd_controllo_permessi_forum($tipo, $proprietari = '')
{
    $tipo = gdrcd_filter('num', $tipo);
    $perm = gdrcd_filter('num', $_SESSION['permessi']);
    $razza = gdrcd_filter('num', $_SESSION['id_razza']);
    $gilda = gdrcd_filter('out', $_SESSION['gilda']);

    switch ($tipo) {
        case PERTUTTI:
        case INGIOCO:
            return true;

        case SOLORAZZA:
            return (($razza == $proprietari) || ($perm >= MODERATOR));

        case SOLOGILDA:

            if (empty($proprietari)) {
                return false;
            } else {
                return (strpos($gilda, '*' . $proprietari . '*') || ($perm >= MODERATOR));
            }

        case SOLOMASTERS:
            return ($perm >= GAMEMASTER);

        case SOLOMODERATORS:
            return ($perm >= MODERATOR);

        default:
            return ($perm >= SUPERUSER);
    }
}

/**
 * Controlla se l'utente è loggato da pochi minuti. Utile per l'icona entra/esce
 * @param string $time : data in un formato leggibile da strtotime()
 * @return int
 */
function gdrcd_check_time($time)
{
    // Converto l'orario $time in un formato leggibile
    $time_hours = (int)date('H', strtotime($time));
    $time_minutes = (int)date('i', strtotime($time));
    // Converto l'orario corrente in un formato leggibile
    $current_hours = (int)date('H');
    $current_minutes = (int)date('i');

    if ($time_hours == $current_hours) {
        return $current_minutes - $time_minutes;
    } elseif ($time_hours == ($current_hours - 1) || $time_hours == ($current_hours + 11)) {
        return $current_minutes - $time_minutes + 60;
    }

    return 61;
}

/**
 * Utilità
 * Set di funzioni di utilità generica per l'engine
 */

/**
 * Provvede al caricamento degli elementi nell'interfaccia
 * E' approssimata ma funziona, se qualcuno vuol far di meglio si faccia avanti
 * @param string $path : il percorso filesystem del file da includere
 * @param array $params : un array di dati aggiuntivi passabili al modulo
 */
function gdrcd_load_modules($page, $params = [])
{
    global $MESSAGE;
    global $PARAMETERS;

    // Costruisco i parametri del modulo
    $MODULE = $params;

    // Sostituisco i __ con i /
    $page = gdrcd_pages_format($page);

    try {
        // Controllo la tipologia di informazione passata (file o page) e poi determino il percorso del modulo
        $modulePath = is_file($page) ? $page : gdrcd_pages_path($page);

        if(!file_exists($modulePath)) {
            throw new Exception($MESSAGE['interface']['layout_not_found']);
        }

        // Includo il modulo
        include($modulePath);
    }
    catch(Exception $e) {
        echo $e->getMessage();
    }

}

/**
 * Formatto il nome della pagina per consentire la ricerca
 * @param string $page il nome della pagina
 * @return string
 */
function gdrcd_pages_format($page)
{
    $page = str_replace('\\',DIRECTORY_SEPARATOR, $page);
    //converte la combinaizone di caratteri __ nel separatore di directory
    $page = str_replace('__',DIRECTORY_SEPARATOR, $page);
    //
    return gdrcd_filter('include', $page);
}

/**
 * Eseguo un controllo sul contenuto della cartella /pages
 * e cerco una corrispondenza tra i moduli e i file presenti
 * @param string $page il nome della pagina da cercare
 * @return string
 * @throws Exception
 */
function gdrcd_pages_path($page)
{
    global $MESSAGE;

    // Controllo che sia stato attribuito un valore a page
    if(empty($page)) {
        throw new Exception($MESSAGE['interface']['page_missing']);
    }

    // Inizializzo le variabili del metodo
    $pagesPath = dirname(__FILE__) . DIRECTORY_SEPARATOR. '..'.DIRECTORY_SEPARATOR.'pages';
    $pageFormatted = gdrcd_pages_format($page);

    // Imposto i possibili percorsi che posso caricare
    $routes = [
        '.inc.php',
        DIRECTORY_SEPARATOR.'index.inc.php'
    ];

    // Inizializzo la variabile contenitore dei moduli
    $modules = [];

    // Scorro i percorsi impostati per individuare corrispondenze
    foreach ($routes AS $route) {
        $file = implode(DIRECTORY_SEPARATOR, [$pagesPath, $pageFormatted.$route]);
        // Se esiste la corrispondenza, allora inserisco
        if(file_exists($file)) {
            $modules[] = $file;
        }
    }

    // Controllo che sia stata trovata almeno una corrispondenza
    if(empty($modules)) {
        throw new Exception($MESSAGE['interface']['page_not_found']);
    }

    // Se sono state trovate piu corrispondenze, blocco il caricamento
    if(count($modules) > 1) {
        throw new Exception($MESSAGE['interface']['multiple_page_found']);
    }

    // Ritorno il modulo
    return $modules[0];
}

/**
 * Funzione di formattazione per la data nel formato italiano
 * @param string $date_in : la data in un formato leggibile da strtotime()
 * @return la data nel formato dd/mm/yyyy
 */
function gdrcd_format_date($date_in)
{
    return date('d/m/Y', strtotime($date_in));
}

/**
 * Funzione di formattazione del tempo nel formato italiano
 * @param string $time_in : la data-ora in un formato leggibile da strtotime()
 * @return l'ora nel formato hh:mm
 */
function gdrcd_format_time($time_in)
{
    return date('H:i', strtotime($time_in));
}

/**
 * Funzione di formattazione data completa nel formato italiano
 * @param $datetime_in : la data e ora in formato leggibile da strtotime()
 * @return string la data/ora nel formato DD/MM/YYYY hh:mm
 */
function gdrcd_format_datetime($datetime_in)
{
    return date('d/m/Y H:i', strtotime($datetime_in));
}

/**
 * Funzione di formattazione data completa nel formato standard del database
 * @param string $datetime_in : la data e ora in formato leggibile da strtotime()
 * @return string la data/ora nel formato YYYY-MM-DD hh:mm
 */
function gdrcd_format_datetime_standard($datetime_in)
{
    return date('Y-m-d H:i', strtotime($datetime_in));
}

/**
 * Funzione di formattazione data completa nel formato ita per nome file da catalogare
 * @param string $datetime_in : la data e ora in formato leggibile da strtotime()
 * @return data ora formattata nel formato YYYYMMDD_hhmm
 */
function gdrcd_format_datetime_cat($datetime_in)
{
    return date('Ymd_Hi', strtotime($datetime_in));
}

/**
 * Trasforma la prima lettera della parola in maiuscolo
 * @param string $word : la parola da manipolare
 * @return $word con solo la prima lettera maiuscola
 */
function gdrcd_capital_letter($word)
{
    return ucwords(strtolower($word));
}

function gdrcd_safe_name($word)
{
    return trim(gdrcd_capital_letter(gdrcd_filter_in($word)));
}

/**
 * Genera una password casuale, esclusivamente alfabetica con lettere maiuscole
 * @return una stringa casuale lunga 8 caratteri
 */
function gdrcd_genera_pass()
{
    $pass = '';
    for ($i = 0; $i < 8; ++$i) {
        $pass .= chr(mt_rand(0, 24) + ord("A"));
    }

    return $pass;
}

/**
 * BBcode nativo di GDRCD
 * Secondo me, questo bbcode presenta non poche vulnerabilità.
 * TODO Andrebbe aggiornata per essere più sicura
 * @param string $str : la stringa con i bbcode da tradurre, dovrebbe già essere stata filtrata per l'output su pagina web
 * @return $str con i tag bbcode tradotti in html
 * @author Blancks
 */
function gdrcd_bbcoder($str)
{
    global $MESSAGE;
    $str = gdrcd_close_tags('quote', $str);

    $search = [
        '#\n#',
        '#\[BR\]#is',
        '#\[B\](.+?)\[\/B\]#is',
        '#\[i\](.+?)\[\/i\]#is',
        '#\[U\](.+?)\[\/U\]#is',
        '#\[center\](.+?)\[\/center\]#is',
        '#\[img\](.+?)\[\/img\]#is',
        '#\[redirect\](.+?)\[\/redirect\]#is',
        '#\[url=(.+?)\](.+?)\[\/url\]#is',
        '#\[color=(.+?)\](.+?)\[\/color\]#is',
        '#\[quote(?::\w+)?\]#i',
        '#\[quote=(?:&quot;|"|\')?(.*?)["\']?(?:&quot;|"|\')?\]#i',
        '#\[/quote(?::\w+)?\]#si'
    ];
    $replace = [
        '<br />',
        '<br />',
        '<span style="font-weight: bold;">$1</span>',
        '<span style="font-style: italic;">$1</span>',
        '<span style="border-bottom: 1px solid;">$1</span>',
        '<div style="width:100%; text-align: center;">$1</div>',
        '<img src="$1">',
        '<meta http-equiv="Refresh" content="5;url=$1">',
        '<a href="$1">$2</a>',
        '<span style="color: $1;">$2</span>',
        '<div class="bb-quote">' . $MESSAGE['interface']['forums']['link']['quote'] . ':<blockquote class="bb-quote-body">',
        '<div class="bb-quote"><div class="bb-quote-name">$1 ha scritto:</div><blockquote class="bb-quote-body">',
        '</blockquote></div>'
    ];

    return preg_replace($search, $replace, $str);
}

/**
 * Aggiunge la chiusura dei tag BBCode per impedire agli utenti di rompere l'HTML del sito
 * @param array|string $tag : il tag da controllare, senza le parentesi quadre, può essere un array di tag
 * @param $body : il testo in cui controllare
 * @return Il testo corretto
 * TODO aggiunge correttamente i tag non chiusi, ma non fa nulla se ci sono troppi tag di chiusura
 */
function gdrcd_close_tags($tag, $body)
{
    if (is_array($tag)) {
        foreach ($tag as $value) {
            $body = gdrcd_close_tags($value, $body);
        }
    } else {
        $opentags = preg_match_all('/\[' . $tag . '/i', $body);
        $closed = preg_match_all('/\[\/' . $tag . '\]/i', $body);
        $unclosed = $opentags - $closed;
        for ($i = 0; $i < $unclosed; $i++) {
            $body .= '[/' . $tag . ']';
        }
    }

    return $body;
}

/**
 * Fa il redirect della pagina, diretto ocon delay
 * @param $url : l'URL verso cui fare redirect
 * @param $tempo : il numero di secondi da attendere prima di fare redirect. Se non attendere impostare a 0 o false
 */
function gdrcd_redirect($url, $tempo = false)
{
    if (!headers_sent() && $tempo == false) {
        header('Location:' . $url);
    } elseif (!headers_sent() && $tempo != false) {
        header('Refresh:' . $tempo . ';' . $url);
    } else {
        if ($tempo == false) {
            $tempo = 0;
        }
        echo "<meta http-equiv=\"refresh\" content=\"" . $tempo . ";" . $url . "\">";
    }
}

/**
 * Sostituisce eventuali parentesi angolari in coppia in una stringa con parentesi quadre
 * @param string $str : la stringa da controllare
 * @return string $str con la coppie di parentesi angolari sostituite con parentesi quadre
 */
function gdrcd_angs($str)
{
    $search = [
        '#\&lt;(.+?)\&gt;#is',
        '#\<(.+?)>#is',
    ];
    $replace = [
        '[$1]',
        '[$1]',
    ];

    return preg_replace($search, $replace, $str);
}

/**
 * Colora in HTML le parti di testo comprese tra parentesi angolari o parentesi quadre
 * Si usa in chat
 * @param string $str : la stringa da controllare
 * @return $str con la parti colorate
 */
function gdrcd_chatcolor($str)
{
    $search = [
        '#\&lt;(.+?)\&gt;#is',
        '#\[(.+?)\]#is',
    ];
    $replace = [
        '<span class="color2">&lt;$1&gt;</span>',
        '<span class="color2">&lt;$1&gt;</span>',
    ];

    return preg_replace($search, $replace, $str);
}

/**
 * Sottolinea in HTML una stringa presente in un testo. Usata per sottolineare il proprio nome in chat
 * @param string $user : la stringa da sottolineare, in genere un nome utente
 * @param string $str : la stringa in cui cercare e sottolineare $user
 * @return $str con tutte le occorrenze di $user sottolineate
 */
function gdrcd_chatme($user, $str, $master = false)
{
    $search = "|\\b" . preg_quote($user, "|") . "\\b|si";
    if (!$master) {
        $replace = '<span class="chat_me">' . gdrcd_filter('out', $user) . '</span>';
    } else {
        $replace = '<span class="chat_me_master">' . gdrcd_filter('out', $user) . '</span>';
    }

    return preg_replace($search, $replace, $str);
}

/**
 * Crea un campo di autocompletamento HTML5 (<datalist>) per vari contenuti
 * @param string $str : specifica il soggetto di cui creare la lista. Attualmente è supportato solo 'personaggi', che crea una lista di tutti gli utenti del gdr
 * @return string il tag html <datalist> già pronto per essere stampato sulla pagina
 */
function gdrcd_list($str)
{
    switch (strtolower($str)) {
        case 'personaggi':
            $list = '<datalist id="personaggi">';
            $query = "SELECT nome FROM personaggio ORDER BY nome";
            $characters = gdrcd_query($query, 'result');

            while ($option = gdrcd_query($characters, 'fetch')) {
                $list .= '<option value="' . htmlspecialchars($option['nome'], ENT_QUOTES) . '" />';
            }
            gdrcd_query($characters, 'free');
            $list .= '</datalist>';
            break;
    }

    return $list;
}

/**
 * Mostro in modo leggibile le informazioni di una variabile, tra cui il suo contenuto
 * @param string $object Variabile da consultare
 * @return  void    Mostra a schermo il contenuto della variabile, formattato
 */
function gdrcd_dump($object)
{
    echo '<xmp style="text-align: left;font-size:13px;">';
    print_r($object);
    echo '</xmp><br />';
}

/**
 * Raccolgo le informazioni di una variabile e le mostro in modo leggibile
 * @param mixed $args Variabile da consultare
 * @return  void    Mostra a schermo il contenuto della variabile, formattato
 * @usage   gdrcd_debug($var); gdrcd_debug($var1, $var2, ...);
 */
function gdrcd_debug($args)
{
    $args = func_get_args();
    foreach ($args as $arg) {
        gdrcd_dump($arg);
    }
}

/**
 * Raccolgo le informazioni di una variabile e le mostro in modo leggibile, poi interrompo il caricamento della pagina
 * @param mixed $args Variabile da consultare
 * @return  void    Mostra a schermo il contenuto della variabile, formattato
 * @usage   gdrcd_brute_debug($var); gdrcd_brute_debug($var1, $var2, ...);
 */
function gdrcd_brute_debug($args)
{
    $args = func_get_args();
    foreach ($args as $arg) {
        gdrcd_dump($arg);
    }
    die('FINE');
}

/**
 * Conta i tentativi di login falliti dall'IP indicato nell'intervallo di minuti specificato.
 * Usato per il rate limiting del login per prevenire attacchi di brute-force.
 *
 * @param string $ip      Indirizzo IP da controllare (tipicamente $_SERVER['REMOTE_ADDR']).
 * @param int    $minutes Finestra temporale in minuti su cui contare i tentativi falliti.
 * @return int Numero di tentativi falliti nell'intervallo.
 */
function gdrcd_login_attempts_count($ip, $minutes = 5)
{
    if (!gdrcd_login_attempts_table_ready()) {
        return 0;
    }
    $ip = gdrcd_filter('in', (string)$ip);
    $minutes = (int)$minutes;
    if ($minutes <= 0) {
        $minutes = 5;
    }

    try {
        $row = gdrcd_query("SELECT COUNT(*) AS n FROM login_attempts WHERE ip = '" . $ip . "' AND success = 0 AND attempted_at >= (NOW() - INTERVAL " . $minutes . " MINUTE)");
        return (int)($row['n'] ?? 0);
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('login_attempts_count failed', array(
                'ip'        => $ip,
                'exception' => $e->getMessage(),
            ));
        }
        return 0;
    }
}

/**
 * Crea la tabella login_attempts se non esiste. Operazione idempotente, eseguita
 * una sola volta per request, e ignora qualsiasi errore (es. permessi DDL mancanti).
 *
 * @return bool true se la tabella è pronta all'uso
 */
function gdrcd_login_attempts_table_ready()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        gdrcd_query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(50) NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_ip_attempted_at (ip, attempted_at),
            INDEX idx_username_attempted_at (username, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('cannot ensure login_attempts table', array(
                'exception' => $e->getMessage(),
            ));
        }
        $ready = false;
    }
    return $ready;
}

/**
 * Registra un tentativo di login (riuscito o fallito) nella tabella login_attempts.
 *
 * @param string      $ip       Indirizzo IP del tentativo.
 * @param string|null $username Username tentato, oppure null se non disponibile.
 * @param bool        $success  True se il login è riuscito, false altrimenti.
 * @return void
 */
function gdrcd_login_attempt_log($ip, $username, $success)
{
    if (!gdrcd_login_attempts_table_ready()) {
        return;
    }
    $ip = gdrcd_filter('in', (string)$ip);
    $success_flag = $success ? 1 : 0;

    try {
        if ($username === null || $username === '') {
            gdrcd_query("INSERT INTO login_attempts (ip, username, attempted_at, success) VALUES ('" . $ip . "', NULL, NOW(), " . $success_flag . ")");
        } else {
            $username = gdrcd_filter('in', (string)$username);
            gdrcd_query("INSERT INTO login_attempts (ip, username, attempted_at, success) VALUES ('" . $ip . "', '" . $username . "', NOW(), " . $success_flag . ")");
        }
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('login_attempt_log failed', array(
                'ip'        => $ip,
                'username'  => $username,
                'success'   => $success_flag,
                'exception' => $e->getMessage(),
            ));
        }
    }
}

/**
 * Registra una toast notification da mostrare alla prossima renderizzazione di pagina.
 *
 * Le toast vengono accodate in $_SESSION['_toasts'] e poi flushate (e svuotate)
 * da gdrcd_flash_toasts(), tipicamente invocata nel footer. Pensato per essere
 * usato in handler che terminano con un redirect (PRG pattern).
 *
 * @param string $kind    Uno tra: success | error | warning | info.
 * @param string $message Testo della notifica (verrà escapato in JS).
 * @return void
 */
function gdrcd_toast($kind, $message)
{
    $valid = array('success', 'error', 'warning', 'info');
    $kind = in_array($kind, $valid, true) ? $kind : 'info';

    if (!isset($_SESSION['_toasts']) || !is_array($_SESSION['_toasts'])) {
        $_SESSION['_toasts'] = array();
    }

    $_SESSION['_toasts'][] = array(
        'kind' => $kind,
        'message' => (string)$message,
    );
}

/**
 * Estrae le toast accodate in sessione e produce un blocco <script> con le
 * chiamate a window.gdrcdToast(...). La coda viene svuotata dopo il flush.
 *
 * @return string Blocco <script> pronto da inserire nel footer, o '' se vuoto.
 */
function gdrcd_flash_toasts()
{
    if (empty($_SESSION['_toasts']) || !is_array($_SESSION['_toasts'])) {
        return '';
    }

    $toasts = $_SESSION['_toasts'];
    $_SESSION['_toasts'] = array();

    $calls = array();
    foreach ($toasts as $t) {
        if (!is_array($t) || !isset($t['kind'], $t['message'])) {
            continue;
        }
        $kind_json    = json_encode((string)$t['kind'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $message_json = json_encode((string)$t['message'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($kind_json === false || $message_json === false) {
            continue;
        }
        $calls[] = '    window.gdrcdToast(' . $kind_json . ', ' . $message_json . ');';
    }

    if (empty($calls)) {
        return '';
    }

    return "<script>\n"
         . "(function(){\n"
         . "  function fire(){\n"
         . implode("\n", $calls) . "\n"
         . "  }\n"
         . "  if (typeof window.gdrcdToast === 'function') { fire(); }\n"
         . "  else { document.addEventListener('DOMContentLoaded', fire); }\n"
         . "})();\n"
         . "</script>\n";
}

/**
 * Rimuove i tentativi di login più vecchi di un'ora per l'IP indicato.
 * Operazione di cleanup leggera, tipicamente chiamata dopo un login riuscito.
 *
 * @param string $ip Indirizzo IP per cui ripulire la cronologia.
 * @return void
 */
function gdrcd_login_attempts_cleanup($ip)
{
    if (!gdrcd_login_attempts_table_ready()) {
        return;
    }
    $ip = gdrcd_filter('in', (string)$ip);
    try {
        gdrcd_query("DELETE FROM login_attempts WHERE ip = '" . $ip . "' AND attempted_at < (NOW() - INTERVAL 1 HOUR)");
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('login_attempts_cleanup failed', array(
                'ip'        => $ip,
                'exception' => $e->getMessage(),
            ));
        }
    }
}

/**
 * Crea la tabella `config_settings` se non esiste. Operazione idempotente,
 * eseguita una sola volta per request, ignora gli errori (es. permessi DDL
 * mancanti su DB già in produzione).
 *
 * Stesso pattern di gdrcd_login_attempts_table_ready(): consente al sistema
 * di funzionare anche se la migrazione non è ancora stata eseguita,
 * cadendo silenziosamente sui default delle costanti PHP.
 *
 * @return bool true se la tabella è pronta all'uso.
 */
function gdrcd_config_settings_table_ready()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        gdrcd_query("CREATE TABLE IF NOT EXISTS config_settings (
            setting_key   VARCHAR(64) NOT NULL,
            setting_value TEXT NULL,
            setting_type  VARCHAR(16) NOT NULL DEFAULT 'string',
            description   VARCHAR(255) NULL,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ready = true;
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('cannot ensure config_settings table', array(
                'exception' => $e->getMessage(),
            ));
        }
        $ready = false;
    }
    return $ready;
}

/**
 * Carica (la prima volta) e mette in cache l'intera tabella config_settings.
 * Le letture successive nello stesso request non toccano il DB.
 *
 * @return array<string, array{value:?string, type:string, description:?string}>
 */
function gdrcd_config_settings_cache()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array();

    if (!gdrcd_config_settings_table_ready()) {
        return $cache;
    }

    try {
        $res = gdrcd_query("SELECT setting_key, setting_value, setting_type, description FROM config_settings", 'result');
        while ($row = gdrcd_query($res, 'assoc')) {
            $cache[(string)$row['setting_key']] = array(
                'value'       => $row['setting_value'],
                'type'        => (string)$row['setting_type'],
                'description' => $row['description'],
            );
        }
        gdrcd_query($res, 'free');
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('config_settings load failed', array(
                'exception' => $e->getMessage(),
            ));
        }
    }
    return $cache;
}

/**
 * Forza il reload della cache config_settings (da chiamare dopo una scrittura).
 *
 * @return void
 */
function gdrcd_config_settings_invalidate()
{
    // Sfrutta il fatto che static $cache è inizializzata solo se ===null.
    // Reimpostarla richiede un wrapper: usiamo una closure-friendly riassegnazione
    // sostituendo l'intera entry tramite un re-fetch on-demand.
    // Trick semplice: marcare un flag globale che il prossimo accessor riconosce.
    $GLOBALS['__gdrcd_config_settings_dirty'] = true;
}

/**
 * Restituisce il valore tipizzato di una chiave di config dal DB,
 * con fallback al default fornito dal chiamante.
 *
 * Tipi supportati:
 *  - 'int'    -> (int)
 *  - 'bool'   -> 1/0/true/false/'on'/'off' interpretati come bool
 *  - 'string' -> stringa così com'è (o $default se NULL/assente)
 *
 * Errori di DB / migrazione non eseguita ritornano $default in modo silenzioso:
 * questa funzione è progettata per essere "fail-open" verso le costanti di base.
 *
 * @param string $key     Chiave (es. 'role_perm').
 * @param mixed  $default Valore da restituire se la chiave manca.
 * @return mixed
 */
function gdrcd_config_get($key, $default = null)
{
    $key = (string)$key;
    if ($key === '') {
        return $default;
    }

    // Se è stata invalidata la cache, ricarichiamo bypassando la static.
    if (!empty($GLOBALS['__gdrcd_config_settings_dirty'])) {
        unset($GLOBALS['__gdrcd_config_settings_dirty']);
        $fresh = array();
        if (gdrcd_config_settings_table_ready()) {
            try {
                $res = gdrcd_query("SELECT setting_key, setting_value, setting_type, description FROM config_settings", 'result');
                while ($row = gdrcd_query($res, 'assoc')) {
                    $fresh[(string)$row['setting_key']] = array(
                        'value'       => $row['setting_value'],
                        'type'        => (string)$row['setting_type'],
                        'description' => $row['description'],
                    );
                }
                gdrcd_query($res, 'free');
            } catch (\Throwable $e) {
                // ignora, useremo cache vecchia
            }
        }
        $GLOBALS['__gdrcd_config_settings_override'] = $fresh;
    }

    if (!empty($GLOBALS['__gdrcd_config_settings_override'])) {
        $cache = $GLOBALS['__gdrcd_config_settings_override'];
    } else {
        $cache = gdrcd_config_settings_cache();
    }

    if (!isset($cache[$key])) {
        return $default;
    }
    $entry = $cache[$key];
    $val   = $entry['value'];
    $type  = isset($entry['type']) ? strtolower($entry['type']) : 'string';

    if ($val === null) {
        return $default;
    }

    switch ($type) {
        case 'int':
            return (int)$val;
        case 'bool':
            if (is_bool($val)) {
                return $val;
            }
            $v = strtolower(trim((string)$val));
            return in_array($v, array('1', 'true', 'on', 'yes', 'y'), true);
        default:
            return (string)$val;
    }
}

/**
 * Salva (UPSERT) il valore di una chiave di config nel DB e invalida la cache.
 *
 * Riservato all'admin UI: chiamanti devono già aver verificato i permessi
 * dell'utente (es. $_SESSION['permessi'] >= SUPERUSER) e il token CSRF.
 *
 * @param string $key   Chiave (es. 'role_perm').
 * @param mixed  $value Valore da serializzare (int/bool/string).
 * @param string $type  Uno tra 'int', 'bool', 'string'.
 * @return bool true se scritto correttamente.
 */
function gdrcd_config_set($key, $value, $type = 'string')
{
    if (!gdrcd_config_settings_table_ready()) {
        return false;
    }
    $key  = (string)$key;
    $type = strtolower((string)$type);
    if (!in_array($type, array('int', 'bool', 'string'), true)) {
        $type = 'string';
    }

    // Normalizza value -> stringa da memorizzare.
    switch ($type) {
        case 'int':
            $serialized = (string)(int)$value;
            break;
        case 'bool':
            $bool = is_bool($value)
                ? $value
                : in_array(strtolower(trim((string)$value)), array('1', 'true', 'on', 'yes', 'y'), true);
            $serialized = $bool ? '1' : '0';
            break;
        default:
            $serialized = (string)$value;
            break;
    }

    $k = gdrcd_filter('in', $key);
    $v = gdrcd_filter('in', $serialized);
    $t = gdrcd_filter('in', $type);

    try {
        gdrcd_query(
            "INSERT INTO config_settings (setting_key, setting_value, setting_type) VALUES "
            . "('" . $k . "', '" . $v . "', '" . $t . "') "
            . "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)"
        );
        gdrcd_config_settings_invalidate();
        return true;
    } catch (\Throwable $e) {
        if (function_exists('gdrcd_log_error')) {
            gdrcd_log_error('config_settings set failed', array(
                'key'       => $key,
                'exception' => $e->getMessage(),
            ));
        }
        return false;
    }
}

/**
 * Helper tipizzati per le costanti "gate" gestite via config_settings.
 * Ognuno legge dal DB e fa fallback al valore della costante PHP corrispondente,
 * così il codice esistente che usa direttamente la const continua a funzionare.
 *
 * I chiamanti dei vecchi `if ($x >= ROLE_PERM)` possono migrare a `gdrcd_role_perm()`
 * in modo incrementale: nessuna modifica forzata.
 */

/**
 * @return int Livello minimo per gestire registrazioni role.
 */
function gdrcd_role_perm()
{
    return (int)gdrcd_config_get('role_perm', defined('ROLE_PERM') ? ROLE_PERM : 2);
}

/**
 * @return int Livello minimo per accedere ai log chat.
 */
function gdrcd_log_perm()
{
    return (int)gdrcd_config_get('log_perm', defined('LOG_PERM') ? LOG_PERM : 2);
}

/**
 * @return int Livello minimo per editare registrazioni oltre la soglia temporale.
 */
function gdrcd_edit_perm()
{
    return (int)gdrcd_config_get('edit_perm', defined('EDIT_PERM') ? EDIT_PERM : 2);
}

/**
 * @return bool Abilita "Segnala ai Master" nelle giocate.
 */
function gdrcd_send_gm()
{
    return (bool)gdrcd_config_get('send_gm', defined('SEND_GM') ? SEND_GM : true);
}

/**
 * @return bool Abilita download HTML della giocata.
 */
function gdrcd_save_role()
{
    return (bool)gdrcd_config_get('save_role', defined('SAVE_ROLE') ? SAVE_ROLE : true);
}

/**
 * @return int Azioni minime per validare una registrazione di giocata.
 */
function gdrcd_reg_min_azioni()
{
    return (int)gdrcd_config_get('reg_min_azioni', defined('REG_MIN_AZIONI') ? REG_MIN_AZIONI : 4);
}

/**
 * Converte un file immagine locale in una data URL base64.
 * Usata per generare log di chat HTML autonomi (offline-friendly).
 *
 * @param string $localPath Percorso assoluto o relativo (rispetto alla root del progetto) al file immagine
 * @return string Stringa "data:image/...;base64,..." oppure stringa vuota se il file non esiste/non è leggibile
 */
function gdrcd_inline_image($localPath)
{
    if (!is_string($localPath) || $localPath === '') {
        return '';
    }

    // Rifiuta URL remoti: la funzione gestisce solo file locali.
    if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $localPath)) {
        return '';
    }

    // Risolve i percorsi relativi rispetto alla root del progetto (la cartella che contiene includes/).
    $candidate = $localPath;
    if (!@is_file($candidate)) {
        $projectRoot = dirname(__DIR__);
        $stripped    = ltrim($localPath, '/\\');
        $candidate   = $projectRoot . DIRECTORY_SEPARATOR . $stripped;
    }

    if (!@is_file($candidate) || !@is_readable($candidate)) {
        return '';
    }

    $data = @file_get_contents($candidate);
    if ($data === false || $data === '') {
        return '';
    }

    $ext  = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    switch ($ext) {
        case 'png':  $mime = 'image/png';     break;
        case 'jpg':
        case 'jpeg': $mime = 'image/jpeg';    break;
        case 'gif':  $mime = 'image/gif';     break;
        case 'webp': $mime = 'image/webp';    break;
        case 'svg':  $mime = 'image/svg+xml'; break;
        case 'bmp':  $mime = 'image/bmp';     break;
        case 'ico':  $mime = 'image/x-icon';  break;
    }

    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

/**
 * Restituisce un blocco CSS condensato per i log di chat HTML autonomi (offline).
 * Mantenuto inline anziché caricato da Tailwind output.css per ridurre la dimensione del file generato.
 *
 * @return string CSS, senza i tag <style>
 */
function gdrcd_chatlog_inline_css()
{
    return <<<CSS
:root{
    --bg:#f8f7f4;--panel:#ffffff;--border:#e5e0d4;
    --text:#1f2937;--soft:#374151;--muted:#6b7280;--subtle:#9ca3af;
    --accent:#a47e3b;--accent-soft:#f3ead4;--master:#8b5a1f;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{background:var(--bg);color:var(--text);
     font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,system-ui,sans-serif;
     line-height:1.55;font-size:14px;padding:24px}
.wrap{max-width:920px;margin:0 auto;background:var(--panel);
      border:1px solid var(--border);border-radius:12px;
      box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.chatlog-header{padding:20px 24px;border-bottom:1px solid var(--border);
                background:linear-gradient(180deg,#fbf7ee 0%,#ffffff 100%)}
.chatlog-header h1{font-family:"Cinzel",Georgia,serif;margin:0 0 4px;
                   font-size:22px;font-weight:700;color:var(--accent);letter-spacing:.3px}
.chatlog-header .meta{font-size:12.5px;color:var(--muted);display:flex;
                      flex-wrap:wrap;gap:14px;margin-top:8px}
.chatlog-header .meta b{color:var(--soft);font-weight:600}
.chatlog-body{padding:16px 24px 24px}
[class^="chat_row_"]{padding:8px 0;border-bottom:1px solid var(--border);
                     display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px}
[class^="chat_row_"]:last-child{border-bottom:0}
.chat_avatar{width:40px;height:40px;border-radius:50%;
             border:1px solid var(--border);flex-shrink:0;margin-right:4px;
             background-size:cover;background-position:center;
             vertical-align:middle;display:inline-block;object-fit:cover}
.chat_time{font-size:12px;color:var(--muted);
           font-variant-numeric:tabular-nums;flex-shrink:0;margin-top:2px;
           font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.chat_name{font-weight:600;color:var(--accent);flex-shrink:0}
.chat_name a{color:var(--accent);text-decoration:none;cursor:default}
.chat_tag{font-size:12px;color:var(--muted)}
.chat_msg{color:var(--soft);flex:1;min-width:0;word-wrap:break-word;overflow-wrap:anywhere}
.chat_master{color:var(--master);font-weight:600;font-style:italic}
.chat_icons{display:inline-flex;align-items:center;gap:4px;flex-shrink:0}
.presenti_ico{width:16px;height:16px;border-radius:2px;vertical-align:middle;
              background-size:contain;background-repeat:no-repeat;background-position:center;
              display:inline-block}
.chat_img{max-width:320px;border-radius:6px;border:1px solid var(--border)}
.chat_row_A{font-style:italic}
.chat_row_S{color:var(--muted);font-size:12.5px;font-style:italic}
.chat_row_M{background:var(--accent-soft);padding:6px 12px;border-radius:6px;
            border-bottom-color:var(--accent-soft)}
.chat_row_I{justify-content:center}
.chat_row_N .chat_name{color:var(--master)}
.chatlog-footer{padding:12px 24px;border-top:1px solid var(--border);
                font-size:11.5px;color:var(--subtle);text-align:center}
@media print{body{padding:0;background:#fff}.wrap{box-shadow:none;border:0}}
CSS;
}

/**
 * Costruisce l'header HTML del log di chat autonomo.
 *
 * @param string $siteName     Nome del sito di gioco
 * @param string $character    Nome del personaggio che ha generato il log
 * @param string|null $rangeStart Data/ora di inizio intervallo (stringa formattata pronta per la stampa)
 * @param string|null $rangeEnd   Data/ora di fine intervallo (stringa formattata pronta per la stampa)
 * @return string Frammento HTML
 */
function gdrcd_chatlog_header_html($siteName, $character, $rangeStart = null, $rangeEnd = null)
{
    $generated = date('d/m/Y H:i');
    $range = '';
    if (!empty($rangeStart) && !empty($rangeEnd)) {
        $range = '<span><b>Periodo:</b> ' . htmlspecialchars($rangeStart) . ' &rarr; ' . htmlspecialchars($rangeEnd) . '</span>';
    } elseif (!empty($rangeStart)) {
        $range = '<span><b>Dal:</b> ' . htmlspecialchars($rangeStart) . '</span>';
    }

    $character = $character !== '' ? htmlspecialchars($character) : '&mdash;';
    $siteName  = htmlspecialchars($siteName);

    return '<div class="chatlog-header">'
        . '<h1>Log di chat</h1>'
        . '<div class="meta">'
        .   '<span><b>Sito:</b> ' . $siteName . '</span>'
        .   '<span><b>Personaggio:</b> ' . $character . '</span>'
        .   $range
        .   '<span><b>Generato:</b> ' . $generated . '</span>'
        . '</div>'
        . '</div>';
}

/**
 * Costruisce un nome file leggibile per i log di chat scaricati.
 * Esempio: chat-Aragorn-20260511-153012.html
 *
 * @param string $character Nome del personaggio (può contenere caratteri non ASCII)
 * @return string Nome file sanificato, senza directory
 */
function gdrcd_chatlog_filename($character)
{
    $safe = (string)$character;
    // Sostituisce caratteri non alfanumerici (preserva lettere accentate semplici riducendole)
    $safe = preg_replace('/[^A-Za-z0-9_\-]+/u', '_', $safe);
    $safe = trim($safe, '_');
    if ($safe === '') {
        $safe = 'anon';
    }
    return 'chat-' . $safe . '-' . date('Ymd-His') . '.html';
}

/**
 * Registro di immagini incorporate in un singolo log di chat.
 * Ogni immagine locale viene letta UNA sola volta; nel markup viene emesso
 * un <span class="..."> che fa riferimento a una classe CSS generata.
 * Il blocco <style> con tutte le definizioni viene poi reso da render_style().
 * Questo evita di duplicare lo stesso data URL base64 ad ogni riga della chat
 * (riduce di ordini di grandezza le dimensioni del file generato).
 */
class GdrcdChatlogImageRegistry
{
    private $byPath = array();   // path -> classe CSS (o false se non inlinabile)
    private $items  = array();   // classe CSS -> data URL
    private $counter = 0;

    /**
     * Registra un'immagine locale e restituisce la classe CSS associata.
     * @param string $localPath path relativo alla root del progetto (o assoluto)
     * @return string Nome classe CSS, oppure stringa vuota se non incorporabile
     */
    public function register($localPath)
    {
        if (!is_string($localPath) || $localPath === '') {
            return '';
        }
        if (array_key_exists($localPath, $this->byPath)) {
            return $this->byPath[$localPath] === false ? '' : $this->byPath[$localPath];
        }
        $dataUrl = gdrcd_inline_image($localPath);
        if ($dataUrl === '') {
            $this->byPath[$localPath] = false;
            return '';
        }
        $this->counter++;
        $cls = 'gdrcd-img-' . $this->counter;
        $this->byPath[$localPath] = $cls;
        $this->items[$cls] = $dataUrl;
        return $cls;
    }

    /**
     * Markup per un'icona di chat (razza/genere/gilda) — usa <span> con
     * background-image, ereditando la dimensione dalla classe extra passata.
     */
    public function iconTag($localPath, $extraClass = 'presenti_ico', $alt = '')
    {
        $cls = $this->register($localPath);
        if ($cls === '') {
            return '';
        }
        $extra = $extraClass !== '' ? ' ' . $extraClass : '';
        $title = $alt !== '' ? ' title="' . htmlspecialchars($alt, ENT_QUOTES) . '"' : '';
        return '<span class="' . $cls . $extra . '"' . $title . ' role="img" aria-label="' . htmlspecialchars($alt, ENT_QUOTES) . '"></span>';
    }

    /**
     * Markup per un avatar di chat (40x40 default, oppure misure custom).
     * Restituisce stringa vuota per URL esterni o file non leggibili.
     */
    public function avatarTag($urlImgChat, $width = null, $height = null)
    {
        if (empty($urlImgChat)) {
            return '';
        }
        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $urlImgChat)) {
            return '';
        }
        $cls = $this->register($urlImgChat);
        if ($cls === '') {
            return '';
        }
        $style = '';
        if ($width !== null && $height !== null) {
            $style = ' style="width:' . (int)$width . 'px;height:' . (int)$height . 'px;"';
        }
        return '<span class="' . $cls . ' chat_avatar"' . $style . ' role="img" aria-label=""></span>';
    }

    /**
     * Restituisce il blocco CSS con tutte le regole background-image per le
     * immagini registrate. Va inserito dentro al tag <style> del log.
     */
    public function renderStyle()
    {
        if (empty($this->items)) {
            return '';
        }
        $css = '';
        foreach ($this->items as $cls => $url) {
            $css .= '.' . $cls . '{background-image:url("' . $url . '");background-size:cover;background-position:center;display:inline-block}' . "\n";
        }
        return $css;
    }
}
