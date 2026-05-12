<?php
declare(strict_types=1);

/**
 * Funzioni di protezione CSRF (Cross-Site Request Forgery)
 *
 * Strategia:
 *  - un token unico per sessione, generato la prima volta che viene richiesto
 *  - regenerato all'autenticazione (vedi login.php) per evitare session fixation
 *  - validato in modo timing-safe con hash_equals()
 *  - enforcement centralizzato in main.php su ogni richiesta POST
 *
 * Esposizione:
 *  - gdrcd_csrf_token()  -> string  (token corrente)
 *  - gdrcd_csrf_field()  -> string  (input hidden HTML pronto da inserire nel form)
 *  - gdrcd_csrf_check()  -> bool    (validazione del token POST)
 *  - gdrcd_csrf_guard()  -> void    (fail-closed: stampa errore ed esce se non valido)
 */

/**
 * Restituisce il token CSRF della sessione, generandolo se non esistente.
 * @return string
 */
function gdrcd_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Restituisce il campo hidden HTML da inserire dentro un form POST.
 * @return string
 */
function gdrcd_csrf_field(): string
{
    $token = gdrcd_csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica il token CSRF presente in $_POST['_csrf'] contro quello in sessione.
 * Il confronto è timing-safe (hash_equals).
 * @return bool true se valido
 */
function gdrcd_csrf_check(): bool
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    if (!isset($_POST['_csrf']) || !is_string($_POST['_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['_csrf']);
}

/**
 * Enforcement fail-closed: se il token CSRF non è valido stampa un messaggio
 * di errore localizzato e termina l'esecuzione.
 *
 * Da chiamare su tutti i POST sensibili (mutazioni). In GDRCD viene chiamata
 * centralmente in main.php prima del dispatch quando REQUEST_METHOD === POST.
 *
 * @return void
 */
function gdrcd_csrf_guard(): void
{
    if (gdrcd_csrf_check()) {
        return;
    }

    $msg = 'Sessione scaduta o richiesta non valida. Ricarica la pagina.';
    if (isset($GLOBALS['MESSAGE']['error']['csrf_invalid'])) {
        $msg = $GLOBALS['MESSAGE']['error']['csrf_invalid'];
    }

    // Output minimale e indipendente dal layout corrente (potremmo essere
    // dentro o fuori il layout principale).
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    echo '<div class="gdrcd-alert-error" style="padding:1rem;border:1px solid #b00;background:#fee;color:#900;font-family:sans-serif;">'
       . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
       . '</div>';
    exit;
}
