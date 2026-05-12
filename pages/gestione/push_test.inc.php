<?php
/**
 * Pannello test Web Push (SUPERUSER)
 *   main.php?page=gestione/push_test
 *
 * Form semplice per inviare una notifica push di test a un utente:
 *   - destinatario (login)
 *   - titolo
 *   - corpo
 *   - URL di destinazione al click
 *
 * Il send delega a `gdrcd_push_send()` (includes/push.inc.php) che attualmente
 * e' uno STUB: log dell'intent + count delle subscription trovate. Il vero
 * dispatch HTTP verso il push service non e' ancora implementato (richiede
 * VAPID JWT ES256 + AES-128-GCM secondo RFC 8291). Vedi note in includes/push.inc.php.
 *
 * CSRF: validato centralmente in main.php sui POST.
 */

if (($_SESSION['permessi'] ?? 0) < SUPERUSER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed'] ?? 'Accesso non consentito.') . '</div>'
       . '</div>';
    return;
}

require_once __DIR__ . '/../../includes/push.inc.php';

$flash = null;

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'send') {
    $target = trim((string)($_POST['target'] ?? ''));
    $title  = trim((string)($_POST['title']  ?? ''));
    $body   = trim((string)($_POST['body']   ?? ''));
    $url    = trim((string)($_POST['url']    ?? '/main.php'));

    if ($target === '' || $title === '') {
        $flash = ['kind' => 'warning', 'message' => 'Destinatario e titolo sono obbligatori.'];
    } else {
        $count = gdrcd_push_send($target, [
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'tag'   => 'gdrcd-admin-test',
        ]);

        if ($count <= 0) {
            $flash = [
                'kind'    => 'warning',
                'message' => 'Nessuna subscription registrata per "' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '" (oppure VAPID non configurato).',
            ];
        } else {
            $flash = [
                'kind'    => 'success',
                'message' => 'Push inoltrato (STUB) a ' . (int)$count . ' subscription. Vedi log per dettagli; il dispatch reale richiede VAPID/AES-128-GCM completo.',
            ];
        }
    }
}

/* ------------------------------------------------------------------
 * Stato config: utile mostrarlo in pagina.
 * ------------------------------------------------------------------ */
$vapid_pub_set  = !empty($PARAMETERS['push']['vapid_public']  ?? '');
$vapid_priv_set = !empty($PARAMETERS['push']['vapid_private'] ?? '');

/* ------------------------------------------------------------------
 * Conteggio subscription totali (info al volo).
 * ------------------------------------------------------------------ */
$row = gdrcd_query("SELECT COUNT(*) AS c FROM push_subscriptions");
$totSubs = (int)($row['c'] ?? 0);
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Test notifiche push</h2>
        <p class="gdrcd-text-muted">
            Invia una notifica Web Push di test a un utente specifico. Le subscription
            sono raccolte da <code>push_subscriptions</code>.
        </p>
    </header>

    <?php if ($flash): ?>
        <div class="gdrcd-alert-<?= htmlspecialchars($flash['kind'], ENT_QUOTES, 'UTF-8') ?>">
            <div><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <section class="gdrcd-card space-y-2">
        <h3 class="gdrcd-h3">Stato configurazione</h3>
        <ul class="text-sm space-y-1">
            <li>VAPID public key: <strong><?= $vapid_pub_set  ? 'configurata' : 'mancante' ?></strong></li>
            <li>VAPID private key: <strong><?= $vapid_priv_set ? 'configurata' : 'mancante' ?></strong></li>
            <li>Subscription totali registrate: <strong><?= $totSubs ?></strong></li>
        </ul>
        <?php if (!$vapid_pub_set || !$vapid_priv_set): ?>
            <div class="gdrcd-alert-warning">
                Configura <code>$PARAMETERS['push']['vapid_public']</code> e
                <code>$PARAMETERS['push']['vapid_private']</code> in
                <code>config.inc.php</code> per abilitare l'invio reale.
            </div>
        <?php endif; ?>
        <div class="gdrcd-alert-info">
            Il sender attuale e' uno <strong>stub</strong>: registra l'intento ma non
            effettua ancora il dispatch HTTP cifrato verso il push service. Vedi
            <code>includes/push.inc.php</code> per la roadmap (integrazione
            <code>minishlink/web-push</code> o implementazione manuale VAPID/AES-128-GCM).
        </div>
    </section>

    <section class="gdrcd-card">
        <form method="POST" action="main.php?page=gestione/push_test" class="space-y-3">
            <input type="hidden" name="op" value="send">

            <label class="block">
                <span class="gdrcd-label">Destinatario (login PG)</span>
                <input type="text" name="target" class="gdrcd-input w-full" required
                       value="<?= htmlspecialchars((string)($_POST['target'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label class="block">
                <span class="gdrcd-label">Titolo</span>
                <input type="text" name="title" class="gdrcd-input w-full" required maxlength="120"
                       value="<?= htmlspecialchars((string)($_POST['title'] ?? 'Test GDRCD'), ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label class="block">
                <span class="gdrcd-label">Corpo</span>
                <textarea name="body" class="gdrcd-input w-full" rows="3" maxlength="500"><?=
                    htmlspecialchars((string)($_POST['body'] ?? 'Questa e\' una notifica push di prova.'), ENT_QUOTES, 'UTF-8')
                ?></textarea>
            </label>

            <label class="block">
                <span class="gdrcd-label">URL di destinazione (click)</span>
                <input type="text" name="url" class="gdrcd-input w-full"
                       value="<?= htmlspecialchars((string)($_POST['url'] ?? '/main.php'), ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <div class="flex gap-2">
                <button type="submit" class="gdrcd-btn-primary">Invia push di test</button>
            </div>
        </form>
    </section>

</div>
