<?php
/**
 * Nuova serie di esiti — primo step, raccolta dati iniziali del blocco.
 * Inviato POST a esito_index con op=insert che gestirà la creazione.
 */

if (($_GET['op'] ?? '') !== 'first') {
    return;
}
?>
<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Nuova serie di esiti</h2>
        <p class="gdrcd-prose"><?= $MESSAGE['interface']['esitiserie']['intro'] ?></p>
    </header>

    <section class="gdrcd-card">
        <div class="gdrcd-card-body">
            <form action="main.php?page=gestione_segnalazioni&segn=esito_index" method="post" class="space-y-5">
                <?= gdrcd_csrf_field() ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="gdrcd-label" for="ne_pg">Nome PG coinvolto</label>
                        <input class="gdrcd-input" type="text" id="ne_pg" name="pg" required/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="ne_titolo">Titolo</label>
                        <input class="gdrcd-input" type="text" id="ne_titolo" name="titolo" required/>
                    </div>
                </div>

                <div>
                    <label class="gdrcd-label" for="ne_contenuto">Contenuto ON</label>
                    <textarea class="gdrcd-textarea" id="ne_contenuto" name="contenuto" rows="6"></textarea>
                    <p class="gdrcd-help">Indicazioni sulle azioni ON da compiere o compiute.</p>
                </div>

                <div>
                    <label class="gdrcd-label" for="ne_note">Note OFF</label>
                    <input class="gdrcd-input" type="text" id="ne_note" name="note"/>
                    <p class="gdrcd-help">Solo brevi chiarimenti.</p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <a href="main.php?page=gestione_segnalazioni&segn=esiti_master" class="gdrcd-btn-ghost">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Annulla
                    </a>
                    <input type="hidden" name="op" value="insert"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
