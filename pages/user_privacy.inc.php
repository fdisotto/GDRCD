<?php
/**
 * Utente — Privacy e dati personali (GDPR).
 *
 * Mostra:
 *   - card informativa con link all'informativa estesa
 *   - tabella riepilogativa del contenuto dell'export (categoria, retention, formato)
 *   - bottone download per scaricare l'archivio ZIP via api/user-export.inc.php
 *   - sezione cancellazione account
 */
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z"/>
                </svg>
            </span>
            Privacy e dati
        </h2>
        <p class="gdrcd-muted">Esercita i tuoi diritti sui dati personali: accesso, portabilità e cancellazione.</p>
    </header>

    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Trattamento dei dati</h3>
        </header>
        <div class="gdrcd-card-body space-y-3 text-gdrcd-text leading-relaxed">
            <p>
                I tuoi dati personali sono trattati nel rispetto del Regolamento UE 2016/679 (GDPR).
                Da questa pagina puoi consultare ed esportare in qualsiasi momento una copia completa
                delle informazioni archiviate sul tuo account, oppure richiederne la cancellazione.
            </p>
            <p>
                Per i dettagli completi su finalità, base giuridica e tempi di conservazione consulta
                l'<a class="gdrcd-link" href="main.php?page=privacy_policy">Informativa sulla privacy</a>.
            </p>
        </div>
    </article>

    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3">Contenuto dell'export</h3>
        </header>
        <div class="gdrcd-card-body">
            <div class="gdrcd-table-wrap">
                <table class="gdrcd-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Retention</th>
                            <th>Formato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Scheda personaggio (anagrafica, razza, caratteristiche)</td>
                            <td>Storico completo</td>
                            <td>JSON</td>
                        </tr>
                        <tr>
                            <td>Messaggi privati inviati e ricevuti</td>
                            <td>Ultimi 24 mesi</td>
                            <td>JSON</td>
                        </tr>
                        <tr>
                            <td>Log eventi (accessi, modifiche, moderazione)</td>
                            <td>Ultimi 12 mesi</td>
                            <td>JSON</td>
                        </tr>
                        <tr>
                            <td>Diario personale</td>
                            <td>Storico completo</td>
                            <td>JSON</td>
                        </tr>
                        <tr>
                            <td>Inventario oggetti</td>
                            <td>Stato attuale</td>
                            <td>JSON</td>
                        </tr>
                        <tr>
                            <td>Segnalazioni di role inviate</td>
                            <td>Storico completo</td>
                            <td>JSON</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <a href="api/user-export.inc.php"
                   class="gdrcd-btn-primary inline-flex items-center gap-2"
                   download>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                    </svg>
                    Scarica i miei dati
                </a>
            </div>
        </div>
    </article>

    <article class="gdrcd-card border-red-300">
        <header class="gdrcd-card-header bg-gdrcd-error-soft text-gdrcd-error">
            <h3 class="gdrcd-h3 text-gdrcd-error">Cancellazione account</h3>
        </header>
        <div class="gdrcd-card-body space-y-3">
            <p class="text-gdrcd-text leading-relaxed">
                Puoi richiedere la cancellazione del tuo account in qualsiasi momento.
                L'operazione disattiva il personaggio e ne avvia il processo di rimozione
                secondo quanto previsto dall'informativa sulla privacy.
            </p>
            <div class="flex justify-end">
                <?php if (file_exists(__DIR__ . '/user_forget.inc.php')): ?>
                    <a href="main.php?page=user_forget" class="gdrcd-btn-secondary text-red-600 border-red-300 hover:bg-red-50">
                        Richiedi cancellazione
                    </a>
                <?php else: ?>
                    <a href="main.php?page=user_cancella_pg" class="gdrcd-btn-secondary text-red-600 border-red-300 hover:bg-red-50">
                        Cancella il mio account
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </article>
</div>
