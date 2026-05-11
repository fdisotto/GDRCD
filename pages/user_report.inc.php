<?php
/**
 * Utente - Segnalazione di un altro giocatore/contenuto al team di moderazione.
 *   main.php?page=user_report
 *
 * La segnalazione viene salvata in `moderation_reports` con status='pending';
 * verra' processata dallo staff dal pannello main.php?page=gestione/moderation
 * (vedi migrazione 2026051118_GDRCDReportsModeration).
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

$login   = (string)$_SESSION['login'];
$login_q = gdrcd_filter('in', $login);

/* ------------------------------------------------------------------
 * Etichette per i campi enum (UI italiano).
 * ------------------------------------------------------------------ */
$kinds = [
    'chat'     => 'Comportamento in chat',
    'behavior' => 'Comportamento generale',
    'content'  => 'Contenuto inappropriato',
    'other'    => 'Altro',
];
$severities = [
    'low'    => 'Bassa',
    'medium' => 'Media',
    'high'   => 'Alta',
];

$flash    = null;
$values   = [
    'subject'     => (string)($_GET['subject'] ?? ''),
    'kind'        => 'other',
    'severity'    => 'medium',
    'body'        => '',
    'context_url' => '',
];

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'submit') {

    $subject     = trim((string)($_POST['subject'] ?? ''));
    $kind        = (string)($_POST['kind'] ?? 'other');
    $severity    = (string)($_POST['severity'] ?? 'medium');
    $body        = trim((string)($_POST['body'] ?? ''));
    $context_url = trim((string)($_POST['context_url'] ?? ''));

    if (!isset($kinds[$kind])) {
        $kind = 'other';
    }
    if (!isset($severities[$severity])) {
        $severity = 'medium';
    }
    if ($context_url !== '' && !filter_var($context_url, FILTER_VALIDATE_URL)) {
        // Permetti anche URL relativi al sito (main.php?page=...).
        if (strpos($context_url, 'main.php') !== 0 && strpos($context_url, '/') !== 0) {
            $context_url = '';
        }
    }

    // Aggiorna i valori del form per ripopolarlo in caso di errore.
    $values['subject']     = $subject;
    $values['kind']        = $kind;
    $values['severity']    = $severity;
    $values['body']        = $body;
    $values['context_url'] = $context_url;

    if ($subject === '') {
        $flash = ['kind' => 'warning', 'message' => 'Seleziona il personaggio segnalato.'];
    } elseif ($subject === $login) {
        $flash = ['kind' => 'warning', 'message' => 'Non puoi segnalare il tuo stesso personaggio.'];
    } elseif ($body === '' || mb_strlen($body) < 10) {
        $flash = ['kind' => 'warning', 'message' => 'Descrivi la segnalazione con almeno 10 caratteri.'];
    } else {
        // Verifica che il subject esista come personaggio attivo.
        $subject_q  = gdrcd_filter('in', $subject);
        $check      = gdrcd_query(
            "SELECT nome FROM personaggio WHERE nome = '" . $subject_q . "' LIMIT 1"
        );
        if (empty($check['nome'])) {
            $flash = ['kind' => 'warning', 'message' => 'Personaggio segnalato non trovato.'];
        } else {
            $body_q        = gdrcd_filter('in', $body);
            $kind_q        = gdrcd_filter('in', $kind);
            $severity_q    = gdrcd_filter('in', $severity);
            $context_url_q = $context_url !== '' ? "'" . gdrcd_filter('in', $context_url) . "'" : 'NULL';

            gdrcd_query(
                "INSERT INTO moderation_reports "
                . "(reporter, subject, kind, body, context_url, severity, status) VALUES ("
                . "'" . $login_q . "', "
                . "'" . $subject_q . "', "
                . "'" . $kind_q . "', "
                . "'" . $body_q . "', "
                . $context_url_q . ", "
                . "'" . $severity_q . "', "
                . "'pending')"
            );

            if (function_exists('gdrcd_log_info')) {
                gdrcd_log_info('User submitted moderation report', [
                    'reporter' => $login,
                    'subject'  => $subject,
                    'kind'     => $kind,
                    'severity' => $severity,
                ]);
            }

            // Resetta i valori del form dopo invio.
            $values = [
                'subject'     => '',
                'kind'        => 'other',
                'severity'    => 'medium',
                'body'        => '',
                'context_url' => '',
            ];

            $flash = ['kind' => 'success', 'message' => 'Segnalazione inviata. Lo staff valutera\' il caso.'];
        }
    }
}

/* ------------------------------------------------------------------
 * Lista personaggi disponibili per il select (esclude se stesso e disattivati).
 * ------------------------------------------------------------------ */
$pgs_rs = gdrcd_query(
    "SELECT nome FROM personaggio "
    . "WHERE permessi >= 0 AND nome <> '" . $login_q . "' "
    . "ORDER BY nome",
    'result'
);
?>

<div class="space-y-6">

    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle text-gdrcd-accent">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 21v-4a4 4 0 014-4h4l5 5v-5h2a4 4 0 004-4V7a4 4 0 00-4-4H7a4 4 0 00-4 4v10z"/>
                </svg>
            </span>
            Segnala utente
        </h2>
        <p class="gdrcd-muted">
            Apri una segnalazione al team di moderazione. Le segnalazioni sono valutate manualmente
            e vengono conservate per audit interno.
        </p>
    </header>

    <div class="gdrcd-alert-info">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="space-y-1">
            <p>
                Usa questo modulo per segnalare violazioni del regolamento da parte di altri giocatori
                (comportamento, contenuti, abuso in chat). Per problemi tecnici contatta direttamente
                lo staff.
            </p>
            <p>
                Descrivi i fatti in modo chiaro: contesto, eventuale data/ora, e se possibile inserisci
                un link diretto al log o alla pagina dove e' avvenuto l'episodio.
            </p>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success" role="status">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning" role="alert">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Nuova segnalazione</h3>
        </header>
        <div class="gdrcd-card-body">
            <form action="main.php?page=user_report" method="post" class="space-y-4">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="op" value="submit">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="gdrcd-label" for="report_subject">Personaggio segnalato</label>
                        <select class="gdrcd-select w-full" id="report_subject" name="subject" required>
                            <option value="">&mdash; Seleziona &mdash;</option>
                            <?php while ($pg = gdrcd_query($pgs_rs, 'assoc')):
                                $nome = (string)$pg['nome'];
                            ?>
                                <option value="<?= htmlspecialchars($nome, ENT_QUOTES) ?>"
                                    <?= $values['subject'] === $nome ? 'selected' : '' ?>>
                                    <?= gdrcd_filter('out', $nome) ?>
                                </option>
                            <?php endwhile; gdrcd_query($pgs_rs, 'free'); ?>
                        </select>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="report_kind">Tipo di segnalazione</label>
                        <select class="gdrcd-select w-full" id="report_kind" name="kind" required>
                            <?php foreach ($kinds as $k => $lbl): ?>
                                <option value="<?= htmlspecialchars($k) ?>"
                                    <?= $values['kind'] === $k ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lbl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="gdrcd-label" for="report_body">Descrizione</label>
                    <textarea class="gdrcd-textarea w-full" id="report_body" name="body"
                              rows="6" minlength="10" maxlength="4000" required
                              placeholder="Descrivi i fatti, il contesto e ogni dettaglio utile alla moderazione..."><?= gdrcd_filter('out', $values['body']) ?></textarea>
                    <p class="gdrcd-help text-xs">Minimo 10 caratteri. Sii preciso: lo staff valuta solo sulla base di quanto descritto.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="gdrcd-label" for="report_severity">Gravita'</label>
                        <select class="gdrcd-select w-full" id="report_severity" name="severity">
                            <?php foreach ($severities as $k => $lbl): ?>
                                <option value="<?= htmlspecialchars($k) ?>"
                                    <?= $values['severity'] === $k ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lbl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="report_context_url">Link al contesto (opzionale)</label>
                        <input class="gdrcd-input w-full" type="text" id="report_context_url" name="context_url"
                               maxlength="255"
                               value="<?= gdrcd_filter('out', $values['context_url']) ?>"
                               placeholder="es: main.php?page=log_chat&amp;...">
                        <p class="gdrcd-help text-xs">URL del log/pagina dove e' avvenuto l'episodio.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-gdrcd-border">
                    <a href="main.php?page=utenti" class="gdrcd-btn-ghost">Annulla</a>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Invia segnalazione
                    </button>
                </div>
            </form>
        </div>
    </article>

</div>
