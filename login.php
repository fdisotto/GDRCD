<?php
/**
 * Login handler — autenticazione utente, rate-limit, CSRF, sessione.
 */

require_once __DIR__ . '/includes/required.php';

$handleDBConnection = gdrcd_connect();

$login1 = gdrcd_filter('get', $_POST['login1']);
$pass1  = gdrcd_filter('get', $_POST['pass1']);

switch ($_SERVER['REMOTE_ADDR']) {
    case '::1':
    case '127.0.0.1':
        $host = 'localhost';
        break;
    default:
        $host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        break;
}

$login1 = ucwords(strtolower(trim($login1)));

/**
 * Renderizza una pagina di errore login con design system e termina lo script.
 */
$render_error = function (string $title, string $details = '', array $extra_lines = []) use ($PARAMETERS) {
    $theme = htmlspecialchars($PARAMETERS['themes']['current_theme']);
    $home  = gdrcd_filter('out', $PARAMETERS['info']['homepage_name'] ?? 'Homepage');
    $site  = htmlspecialchars($PARAMETERS['info']['site_name'] ?? '');
    ?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('gdrcd_theme');
                if (saved === 'dark') document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    <title>Login · <?= $site ?></title>
    <link rel="stylesheet" href="themes/<?= $theme ?>/main.css" type="text/css">
    <link rel="stylesheet" href="themes/tailwind/output.css" type="text/css">
    <link rel="shortcut icon" href="imgs/favicon.ico">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#a47e3b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GDRCD">
</head>
<body class="min-h-screen bg-gdrcd-bg flex items-center justify-center px-4 py-10 dark:bg-gdrcd-dark-bg dark:text-gdrcd-dark-text">
    <main class="gdrcd-card max-w-md w-full text-center space-y-4 p-8 border-red-300">
        <div class="flex justify-center">
            <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gdrcd-error-soft text-gdrcd-error">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                </svg>
            </span>
        </div>
        <h1 class="font-display text-xl text-gdrcd-error"><?= $title ?></h1>
        <?php if ($details !== ''): ?>
            <p class="text-sm text-gdrcd-text-soft"><?= $details ?></p>
        <?php endif; ?>
        <?php foreach ($extra_lines as $line): ?>
            <p class="text-xs text-gdrcd-text-soft"><?= $line ?></p>
        <?php endforeach; ?>
        <div class="pt-2 border-t border-gdrcd-border">
            <a href="index.php" class="gdrcd-btn-primary inline-flex">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= $home ?>
            </a>
        </div>
    </main>
    <script src="/includes/pwa.js" defer></script>
</body>
</html><?php
    if (isset($GLOBALS['handleDBConnection'])) {
        gdrcd_close_connection($GLOBALS['handleDBConnection']);
    }
    exit();
};

/* Blacklist IP (ban permanenti o non ancora scaduti).
 * NB: la colonna expires_at è introdotta dalla migration 2026051117.
 * Migrazioni applicate via bin/gdrcd-migrate o installer.php. */
$result = gdrcd_query(
    "SELECT * FROM blacklist WHERE ip = '" . $_SERVER['REMOTE_ADDR'] . "' "
    . "AND granted = 0 AND (expires_at IS NULL OR expires_at > NOW())",
    'result'
);
if (gdrcd_query($result, 'num_rows') > 0) {
    gdrcd_query($result, 'free');
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $login1) . "', 'Login_procedure', NOW(), " . BLOCKED . ", '" . $_SERVER['REMOTE_ADDR'] . "')");
    $render_error(gdrcd_filter('out', $MESSAGE['warning']['blacklisted']));
}

/* Rate limiting brute-force */
$rate_limit_ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_failures = gdrcd_login_attempts_count($rate_limit_ip, 5);
if ($rate_limit_failures >= 5) {
    gdrcd_login_attempt_log($rate_limit_ip, $login1, false);
    gdrcd_log_warning('login rate-limit triggered', array(
        'username' => $login1,
        'ip'       => $rate_limit_ip,
        'failures' => $rate_limit_failures,
        'window_minutes' => 5,
    ));
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $login1) . "', 'Login_procedure', NOW(), " . BLOCKED . ", '" . $_SERVER['REMOTE_ADDR'] . " rate_limit')");
    $render_error(
        'Troppi tentativi falliti',
        'Per motivi di sicurezza l\'accesso da questo indirizzo è temporaneamente bloccato.',
        ['Riprova fra qualche minuto.']
    );
}

/* CSRF: il form di login deve contenere il token presente in sessione */
gdrcd_csrf_guard();

/* Carico profilo account */
$record = Db::preparedFetch(
    "SELECT personaggio.pass, personaggio.nome, personaggio.cognome, personaggio.permessi, personaggio.sesso,
            personaggio.ultima_mappa, personaggio.ultimo_luogo, personaggio.id_razza, personaggio.blocca_media,
            personaggio.ora_entrata, personaggio.ora_uscita, personaggio.ultimo_refresh,
            razza.sing_m, razza.sing_f, razza.icon AS url_img_razza
     FROM personaggio LEFT JOIN razza ON personaggio.id_razza = razza.id_razza
     WHERE nome = ? LIMIT 1",
    's',
    [$login1]
) ?? [];

$auth_ok = !empty($record)
    && gdrcd_password_verify($pass1, $record['pass'])
    && ((int)$record['permessi'] > -1)
    && (strtotime($record['ora_entrata']) < strtotime($record['ora_uscita'])
        || (strtotime($record['ultimo_refresh']) + 300) < time());

if ($auth_ok) {
    /* Rehash silenzioso se hash legacy */
    if (gdrcd_password_needs_rehash($record['pass'])) {
        $newHash = gdrcd_password_hash($pass1);
        Db::preparedExecute(
            "UPDATE personaggio SET pass = ? WHERE nome = ? LIMIT 1",
            'ss',
            [$newHash, $record['nome']]
        );
    }

    /* Rigenera CSRF + popola sessione */
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['login']        = gdrcd_filter_in($record['nome']);
    $_SESSION['cognome']      = $record['cognome'];
    $_SESSION['permessi']     = $record['permessi'];
    $_SESSION['sesso']        = $record['sesso'];
    $_SESSION['blocca_media'] = $record['blocca_media'];
    $_SESSION['ultima_uscita']= $record['ora_uscita'];
    $_SESSION['razza']        = ($record['sesso'] == 'f') ? $record['sing_f'] : $record['sing_m'];
    $_SESSION['img_razza']    = $record['url_img_razza'];
    $_SESSION['id_razza']     = $record['id_razza'];
    $_SESSION['posizione']    = $record['posizione'] ?? 0;
    $_SESSION['mappa']        = empty($record['ultima_mappa']) ? 1 : $record['ultima_mappa'];
    $_SESSION['luogo']        = empty($record['ultimo_luogo']) ? -1 : $record['ultimo_luogo'];
    $_SESSION['tag']          = '';
    $_SESSION['last_message'] = 0;
    $_SESSION['gilda']        = '';
    $_SESSION['img_gilda']    = '';

    $res = gdrcd_query(
        "SELECT ruolo.gilda, ruolo.immagine FROM ruolo
         JOIN clgpersonaggioruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
         WHERE clgpersonaggioruolo.personaggio = '" . gdrcd_filter('in', $record['nome']) . "'",
        'result'
    );
    while ($row = gdrcd_query($res, 'fetch')) {
        $_SESSION['gilda']     .= ',*' . $row['gilda'] . '*';
        $_SESSION['img_gilda'] .= $row['immagine'] . ',';
    }
    gdrcd_query($res, 'free');

    /* Tracciamento accessi multipli */
    $lastlogindata = gdrcd_query(
        "SELECT nome_interessato, autore FROM log WHERE nome_interessato = '" . gdrcd_filter('in', $_SESSION['login']) . "'
         AND codice_evento = " . LOGGEDIN . " ORDER BY data_evento DESC LIMIT 1"
    );
    if (isset($_COOKIE['lastlogin']) && $_COOKIE['lastlogin'] != $_SESSION['login']) {
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', 'doppio (cookie)', NOW(), "
                     . ACCOUNTMULTIPLO . ", '" . $_COOKIE['lastlogin'] . "')");
    } elseif (($lastlogindata['autore'] ?? '') == $_SERVER['REMOTE_ADDR']
              && ($lastlogindata['nome_interessato'] ?? '') != $_SESSION['login']) {
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', 'doppio (ip)', NOW(), "
                     . ACCOUNTMULTIPLO . ", '" . gdrcd_filter('in', $lastlogindata['nome_interessato']) . "')");
    }

    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . gdrcd_filter('in', $_SESSION['login']) . "', '" . $_SERVER['REMOTE_ADDR'] . "',
                         NOW(), " . LOGGEDIN . ", '" . $_SERVER['REMOTE_ADDR'] . "')");

    gdrcd_login_attempt_log($_SERVER['REMOTE_ADDR'], $_SESSION['login'], true);
    gdrcd_login_attempts_cleanup($_SERVER['REMOTE_ADDR']);

} elseif (!empty($record)
          && (strtotime($record['ora_entrata']) > strtotime($record['ora_uscita'])
              || (strtotime($record['ultimo_refresh']) + 300) > time())
          && gdrcd_password_verify($pass1, $record['pass'])) {
    /* Doppia connessione */
    gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                 VALUES ('" . $login1 . "', 'Login_procedure', NOW(), " . BLOCKED . ", '" . $_SERVER['REMOTE_ADDR'] . "')");
    $render_error(
        gdrcd_filter('out', $MESSAGE['warning']['double_connection']),
        'Attendi qualche minuto prima di riprovare.'
    );
} else {
    /* Credenziali errate */
    $_SESSION['login'] = '';

    $iErrorsNumber = 0;
    if ($login1 !== '' && $pass1 !== '') {
        gdrcd_query("INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento)
                     VALUES ('', '" . $host . "', NOW(), " . ERRORELOGIN . ", '" . $_SERVER['REMOTE_ADDR'] . "')");
        gdrcd_login_attempt_log($_SERVER['REMOTE_ADDR'], $login1, false);
        gdrcd_log_warning('failed login attempt', array(
            'username' => $login1,
            'ip'       => $_SERVER['REMOTE_ADDR'],
            'host'     => $host,
        ));

        $cnt = gdrcd_query("SELECT COUNT(*) AS n FROM log
                            WHERE descrizione_evento = '" . $_SERVER['REMOTE_ADDR'] . "'
                              AND codice_evento = " . ERRORELOGIN . "
                              AND DATE_ADD(data_evento, INTERVAL 60 MINUTE) > NOW()");
        $iErrorsNumber = (int)($cnt['n'] ?? 0);

        if ($iErrorsNumber >= 10) {
            gdrcd_query("INSERT INTO blacklist (ip, nota, ora, host) VALUES ('"
                . $_SERVER['REMOTE_ADDR'] . "', '" . $login1 . " (tenta password)', NOW(), '" . $host . "')");
        }
    }

    $extra = [];
    if ($iErrorsNumber > 0) {
        $extra[] = gdrcd_filter('out', $MESSAGE['error']['unknown_username_failure_count']) . ' <strong>' . $iErrorsNumber . '</strong>';
        $extra[] = gdrcd_filter('out', $MESSAGE['error']['unknown_username_warning']);
    }
    $extra[] = gdrcd_filter('out', $MESSAGE['warning']['mailto']) . ' '
             . '<a href="mailto:' . htmlspecialchars($PARAMETERS['menu']['webmaster_email'] ?? '') . '" class="text-gdrcd-accent hover:underline">'
             . htmlspecialchars($PARAMETERS['menu']['webmaster_email'] ?? '') . '</a>';

    session_destroy();
    $render_error(
        gdrcd_filter('out', $MESSAGE['error']['unknown_username']),
        gdrcd_filter('out', $MESSAGE['error']['unknown_username_details']),
        $extra
    );
}

/* Login riuscito: completa flusso ed esegui redirect */
if ($_SESSION['login'] !== '') {
    if (gdrcd_controllo_esilio($_SESSION['login']) === true) {
        session_destroy();
        $render_error(
            'Accesso negato',
            'Il personaggio è attualmente esiliato.'
        );
    }

    setcookie('lastlogin', $_SESSION['login'], 0, '', '', 0);

    if ($PARAMETERS['settings']['auto_salary'] == 'ON') {
        $row = gdrcd_query("SELECT soldi, banca, ultimo_stipendio FROM personaggio
                            WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        if ($row['ultimo_stipendio'] != date('Y-m-d')) {
            $sres = gdrcd_query("SELECT ruolo.stipendio FROM clgpersonaggioruolo
                                 LEFT JOIN ruolo ON clgpersonaggioruolo.id_ruolo = ruolo.id_ruolo
                                 WHERE clgpersonaggioruolo.personaggio = '" . gdrcd_filter('in', $_SESSION['login']) . "'", 'result');
            $stipendio = 0;
            while ($r = gdrcd_query($sres, 'fetch')) {
                $stipendio += (int)$r['stipendio'];
            }
            gdrcd_query("UPDATE personaggio SET banca = banca + " . $stipendio . ", ultimo_stipendio = NOW()
                         WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "'");
        }
    }

    if ($PARAMETERS['mode']['log_back_location'] == 'OFF') {
        $_SESSION['luogo'] = '-1';
        Db::preparedExecute(
            "UPDATE personaggio SET ora_entrata = NOW(), ultimo_luogo = '-1', ultimo_refresh = NOW(),
                                    last_ip = ?, is_invisible = 0
             WHERE nome = ?",
            'ss',
            [$_SERVER['REMOTE_ADDR'], $_SESSION['login']]
        );
        header('Location: main.php?page=mappaclick&map_id=' . $_SESSION['mappa'], true);
    } else {
        Db::preparedExecute(
            "UPDATE personaggio SET ora_entrata = NOW(), ultimo_refresh = NOW(),
                                    last_ip = ?, is_invisible = 0
             WHERE nome = ?",
            'ss',
            [$_SERVER['REMOTE_ADDR'], $_SESSION['login']]
        );
        header('Location: main.php?dir=' . $_SESSION['luogo'], true);
    }
    exit();
}

gdrcd_close_connection($handleDBConnection);
