<?php
/**
 * Utente — Diritto all'oblio (GDPR art. 17).
 *
 * Permette al giocatore di aprire una richiesta di anonimizzazione
 * del proprio account. La richiesta NON cancella nulla fisicamente:
 *   - viene salvata in `deletion_requests` con status='pending'
 *   - sara' processata manualmente da un SUPERUSER da
 *     main.php?page=gestione/forget_requests
 *   - l'anonimizzazione effettiva mantiene l'integrita' relazionale
 *     rinominando il personaggio in "Cancellato_<id>" su tutte le
 *     tabelle che referenziano il nome.
 *
 * Il CSRF e' validato centralmente da main.php sui POST.
 */

if (!isset($_SESSION['login']) || $_SESSION['login'] === '') {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

$login_q = gdrcd_filter('in', (string)$_SESSION['login']);

/* ------------------------------------------------------------------
 * Carica i dati di base del personaggio + richiesta pending corrente.
 * ------------------------------------------------------------------ */
$row = gdrcd_query(
    "SELECT email, pass FROM personaggio WHERE nome = '" . $login_q . "' LIMIT 1"
);
$email       = $row['email'] ?? '';
$stored_pass = $row['pass']  ?? '';

$pending = gdrcd_query(
    "SELECT id, requested_at FROM deletion_requests "
    . "WHERE user_login = '" . $login_q . "' AND status = 'pending' "
    . "ORDER BY requested_at DESC LIMIT 1"
);

$flash = null;

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'submit') {

    $password    = (string)($_POST['password'] ?? '');
    $reason      = trim((string)($_POST['reason'] ?? ''));
    $acknowledge = isset($_POST['acknowledge']);

    if (!$acknowledge) {
        $flash = ['kind' => 'warning', 'message' => 'Devi confermare di aver compreso le conseguenze della richiesta.'];
    } elseif ($password === '' || !gdrcd_password_verify($password, $stored_pass)) {
        $flash = ['kind' => 'warning', 'message' => 'Password non corretta: la richiesta non e\' stata inviata.'];
    } elseif (!empty($pending)) {
        $flash = ['kind' => 'warning', 'message' => 'Hai gia\' una richiesta di cancellazione in corso. Sarai contattato all\'email registrata.'];
    } else {
        $email_q  = gdrcd_filter('in', (string)$email);
        $reason_q = gdrcd_filter('in', $reason);

        gdrcd_query(
            "INSERT INTO deletion_requests (user_login, user_email, reason, status) VALUES ("
            . "'" . $login_q . "', '" . $email_q . "', '" . $reason_q . "', 'pending')"
        );

        if (function_exists('gdrcd_log_info')) {
            gdrcd_log_info('GDPR deletion request submitted', [
                'user'   => (string)$_SESSION['login'],
                'reason' => $reason,
            ]);
        }

        // Ricarica lo stato pending in modo che il form scompaia subito.
        $pending = gdrcd_query(
            "SELECT id, requested_at FROM deletion_requests "
            . "WHERE user_login = '" . $login_q . "' AND status = 'pending' "
            . "ORDER BY requested_at DESC LIMIT 1"
        );

        $flash = ['kind' => 'success', 'message' => 'Richiesta inviata. Sarai contattato all\'email registrata.'];
    }
}
?>

<div class="space-y-6">

    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle text-gdrcd-error">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                </svg>
            </span>
            Cancella i miei dati
        </h2>
        <p class="gdrcd-muted">
            Esercita il diritto all'oblio previsto dall'art. 17 del Regolamento UE 2016/679 (GDPR).
        </p>
    </header>

    <div class="gdrcd-alert-info">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="space-y-2">
            <p>
                La richiesta avvia una procedura di <strong>anonimizzazione</strong> del tuo account: i dati
                personali (email, descrizione, biografia, avatar, storia, messaggi privati...) verranno
                resi non riconducibili a te. I contenuti di gioco condivisi (chat, log, segnalazioni)
                restano nel database con un riferimento neutro tipo <em>Cancellato_&lt;id&gt;</em>, per
                preservare l'integrita' del contesto di gioco.
            </p>
            <p>
                La richiesta non viene applicata automaticamente: sara' esaminata da un amministratore.
                Una volta processata, l'operazione e' <strong>irreversibile</strong>.
            </p>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($pending)): ?>
        <article class="gdrcd-card">
            <header class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Richiesta in corso</h3>
            </header>
            <div class="gdrcd-card-body space-y-3 text-gdrcd-text leading-relaxed">
                <p>
                    Hai una richiesta di cancellazione in attesa di revisione
                    (id <code class="text-xs px-1 py-0.5 rounded bg-gdrcd-muted-bg">#<?= (int)$pending['id'] ?></code>,
                    aperta il
                    <strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$pending['requested_at']))) ?></strong>).
                </p>
                <p>
                    Sarai contattato all'indirizzo email registrato sul tuo account. Se cambi idea
                    prima che la richiesta venga processata, contatta lo staff per ritirarla.
                </p>
            </div>
        </article>
    <?php else: ?>
        <article class="gdrcd-card border-red-300">
            <header class="gdrcd-card-header bg-gdrcd-error-soft text-gdrcd-error">
                <h3 class="gdrcd-h3 text-gdrcd-error">Invia richiesta di cancellazione</h3>
            </header>
            <div class="gdrcd-card-body">
                <form action="main.php?page=user_forget" method="post" class="space-y-4">
                    <?= gdrcd_csrf_field() ?>
                    <input type="hidden" name="op" value="submit">

                    <div>
                        <label class="gdrcd-label" for="forget_reason">Motivo (facoltativo)</label>
                        <textarea class="gdrcd-textarea w-full" id="forget_reason" name="reason"
                                  rows="4" maxlength="2000"
                                  placeholder="Puoi indicare brevemente il motivo della richiesta."></textarea>
                        <p class="gdrcd-help text-xs">Il motivo aiuta lo staff a contestualizzare la richiesta ma non e' obbligatorio.</p>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="forget_password">Conferma password</label>
                        <input class="gdrcd-input w-full" type="password" id="forget_password" name="password"
                               autocomplete="current-password" required>
                        <p class="gdrcd-help text-xs">Per sicurezza, inserisci la password del tuo account.</p>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-gdrcd-text">
                        <input type="checkbox" name="acknowledge" value="1" class="mt-1" required>
                        <span>
                            Capisco che la procedura di anonimizzazione e' <strong>irreversibile</strong>,
                            che il mio personaggio sara' rinominato e che non potro' piu' recuperare i
                            contenuti personali associati al mio account.
                        </span>
                    </label>

                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <button type="submit"
                                class="gdrcd-btn-secondary text-red-600 border-red-300 hover:bg-red-50"
                                onclick="return confirm('Confermi l\'invio della richiesta di cancellazione?');">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22m-9 0V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3"/></svg>
                            Invia richiesta
                        </button>
                    </div>
                </form>
            </div>
        </article>
    <?php endif; ?>

    <div class="text-sm">
        <a class="gdrcd-link" href="main.php?page=user_privacy">
            &larr; Torna a Privacy e dati
        </a>
    </div>
</div>
