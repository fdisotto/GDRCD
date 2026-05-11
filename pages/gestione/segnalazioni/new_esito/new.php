<?php
/**
 * Form nuovo esito narrativo da aggiungere a una serie esistente.
 * Submit POST a esito_index?op=add gestito da add.php.
 */

if (($_GET['op'] ?? '') !== 'new') {
    return;
}

$blocco = gdrcd_query(
    "SELECT pg, master, titolo FROM blocco_esiti
     WHERE id = '" . gdrcd_filter('num', $_GET['blocco'] ?? 0) . "' LIMIT 1"
);
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            Nuovo esito
        </h2>
        <p class="gdrcd-muted">
            Serie <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $blocco['titolo'] ?? '') ?></strong>
            <span class="text-gdrcd-subtle">·</span>
            PG <span class="gdrcd-badge-accent ml-1"><?= gdrcd_filter('out', $blocco['pg'] ?? '') ?></span>
        </p>
        <p class="gdrcd-prose"><?= $MESSAGE['interface']['esiti']['newesito'] ?></p>
    </header>

    <section class="gdrcd-card">
        <div class="gdrcd-card-body">
            <form action="main.php?page=gestione_segnalazioni&segn=esito_index" method="post" class="space-y-5">
                <?= gdrcd_csrf_field() ?>

                <div>
                    <label class="gdrcd-label" for="nw_titolo">Titolo</label>
                    <input class="gdrcd-input" type="text" id="nw_titolo" name="titolo" required/>
                </div>

                <div>
                    <label class="gdrcd-label" for="nw_contenuto">Contenuto ON</label>
                    <textarea class="gdrcd-textarea" id="nw_contenuto" name="contenuto" rows="8" data-bbcode></textarea>
                    <p class="gdrcd-help">Descrivere in modo narrativo (come in chat) quel che il personaggio può conoscere o scoprire.</p>
                </div>

                <?php if (TIRI_ESITO): ?>
                    <div class="border border-gdrcd-border rounded-gdrcd bg-gdrcd-panel-alt/30 p-4 space-y-2">
                        <div class="gdrcd-eyebrow">Tira dei dadi</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="gdrcd-label" for="nw_dice_num">Numero di dadi</label>
                                <input class="gdrcd-input" type="number" min="0" id="nw_dice_num" name="dice_num" value="0"/>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="nw_dice_face">Facce per dado</label>
                                <input class="gdrcd-input" type="number" min="0" id="nw_dice_face" name="dice_face" value="0"/>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="gdrcd-label" for="nw_note">Note OFF</label>
                    <input class="gdrcd-input" type="text" id="nw_note" name="note"/>
                    <p class="gdrcd-help">Solo brevi chiarimenti.</p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <a href="main.php?page=gestione_segnalazioni&segn=esiti_master" class="gdrcd-btn-ghost">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Annulla
                    </a>
                    <input type="hidden" name="op" value="add"/>
                    <input type="hidden" name="id" value="<?= (int)($_GET['blocco'] ?? 0) ?>"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
