<?php
/**
 * Widget sidebar — frame presenti.
 *
 * Container vuoto popolato lato client da includes/presenti.js, che polla
 * l'endpoint /api/presenti.inc.php ogni 30 secondi. Rispetto alla vecchia
 * implementazione iframe (60s di refresh hard) niente flicker, CSS non
 * ricaricato ad ogni tick, e nessun secondo bootstrap PHP per ciclo.
 *
 * @see api/presenti.inc.php
 * @see includes/presenti.js
 * @see pages/presenti.inc.php (fallback / vista non-JS)
 * @see pages/presenti_estesi.inc.php (vista estesa)
 */
?>
<div class="gdrcd-widget-title flex items-center gap-2 -m-4 mb-3 px-4 py-2">
    <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87a4 4 0 100-8 4 4 0 000 8zm0-8a4 4 0 100-8 4 4 0 000 8z"/>
    </svg>
    <span>Presenti</span>
</div>

<div id="gdrcd-presenti-list"
     class="space-y-2 text-sm font-sans text-gdrcd-text"
     data-poll-url="/api/presenti.inc.php"
     data-extended-url="main.php?page=presenti_estesi"
     data-login-url="index.php"
     data-me="<?= htmlspecialchars($_SESSION['login'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <div class="text-xs text-gdrcd-muted px-2 py-1" data-role="placeholder">Caricamento&hellip;</div>
</div>
