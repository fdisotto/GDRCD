<?php
/**
 * Form nuovo esito in chat: una skill+chat con 4 esiti per CD differenti.
 * Submit POST a esito_index?op=add gestito da add.php.
 */

if (($_GET['op'] ?? '') !== 'newchat') {
    return;
}

$blocco = gdrcd_query(
    "SELECT pg, master, titolo FROM blocco_esiti
     WHERE id = '" . gdrcd_filter('num', $_GET['blocco'] ?? 0) . "' LIMIT 1"
);

if ($_SESSION['permessi'] < ESITI_PERM || !ESITI_CHAT || ($blocco['pg'] ?? '') === ($_SESSION['login'] ?? '')) {
    echo '<div class="gdrcd-alert-warning">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>Esito in chat non disponibile in questo contesto.</div>'
       . '</div>';
    return;
}
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Esito in chat</h2>
        <p class="gdrcd-muted">
            Serie <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $blocco['titolo']) ?></strong>
            <span class="text-gdrcd-subtle">·</span>
            PG <span class="gdrcd-badge-accent ml-1"><?= gdrcd_filter('out', $blocco['pg']) ?></span>
        </p>
        <p class="gdrcd-prose"><?= $MESSAGE['interface']['esiti']['esitochat'] ?></p>
    </header>

    <section class="gdrcd-card">
        <div class="gdrcd-card-body">
            <form action="main.php?page=gestione_segnalazioni&segn=esito_index" method="post" class="space-y-5">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="gdrcd-label" for="nc_titolo">Titolo</label>
                        <input class="gdrcd-input" type="text" id="nc_titolo" name="titolo" required/>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="nc_chat">Chat</label>
                        <select class="gdrcd-select" id="nc_chat" name="chat">
                            <?php $rooms = gdrcd_query("SELECT id, nome FROM mappa ORDER BY id", 'result');
                            while ($r = gdrcd_query($rooms, 'fetch')): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= gdrcd_filter('out', $r['nome']) ?></option>
                            <?php endwhile;
                            gdrcd_query($rooms, 'free');
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="nc_id_ab">Skill da tirare</label>
                        <select class="gdrcd-select" id="nc_id_ab" name="id_ab">
                            <?php $abs = gdrcd_query("SELECT id_abilita, nome FROM abilita WHERE id_razza = -1 ORDER BY nome", 'result');
                            while ($ab = gdrcd_query($abs, 'fetch')): ?>
                                <option value="<?= (int)$ab['id_abilita'] ?>"><?= gdrcd_filter('out', $ab['nome']) ?></option>
                            <?php endwhile;
                            gdrcd_query($abs, 'free');
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="gdrcd-label" for="nc_mod">Modificatore</label>
                        <input class="gdrcd-input" type="number" id="nc_mod" name="mod" value="0"/>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="gdrcd-eyebrow">Esiti per ciascuna soglia di Classe di Difficoltà</div>
                    <?php foreach ([1, 2, 3, 4] as $cd):
                        $tip = $cd === 1 ? 'Fallimento critico' : ($cd === 2 ? 'Fallimento' : ($cd === 3 ? 'Successo' : 'Successo critico'));
                        ?>
                        <div>
                            <label class="gdrcd-label" for="nc_CD_<?= $cd ?>">CD <?= $cd ?> — <?= $tip ?></label>
                            <textarea class="gdrcd-textarea" id="nc_CD_<?= $cd ?>" name="CD_<?= $cd ?>" rows="3"></textarea>
                        </div>
                    <?php endforeach; ?>
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
