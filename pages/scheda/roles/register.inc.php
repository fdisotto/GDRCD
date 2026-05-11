<?php
/**
 * Scheda PG — form registrazione manuale giocata.
 */

$pg_url = gdrcd_filter('url', $_REQUEST['pg']);

$render_back = function () use ($pg_url, $MESSAGE) { ?>
    <div>
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['link']['back_roles']) ?>
        </a>
    </div>
<?php };

if ($_REQUEST['pg'] != $_SESSION['login']) {
    echo '<div class="gdrcd-alert-error">Non puoi inserire registrazioni nella scheda altrui.</div>';
    $render_back();
    return;
}

$mese  = (int)($_POST['mese'] ?? date('m'));
$anno  = (int)($_POST['anno'] ?? date('Y'));
$mesi_avail = [];
if ($mese !== 1)  $mesi_avail[] = $mese - 1;
$mesi_avail[] = $mese;
if ($mese !== 12) $mesi_avail[] = $mese + 1;

$chat_res = gdrcd_query("SELECT nome, id FROM mappa WHERE chat=1 ORDER BY nome", 'result');
?>

<form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="space-y-4">

    <div class="gdrcd-alert-info">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
        <div>
            <p>Registra le giocate dimenticate in chat. Specifica chat, intervallo orario e dettagli.</p>
            <p class="text-sm mt-1">L'orario sarà verificato: servono almeno <strong><?= REG_MIN_AZIONI ?> azioni</strong>. In caso di margini troppo larghi, il sistema salverà fra la prima e l'ultima azione del personaggio.</p>
        </div>
    </div>

    <article class="gdrcd-card space-y-3">
        <h3 class="font-display text-lg text-gdrcd-accent">Chat di gioco</h3>
        <label class="block">
            <span class="text-sm text-gdrcd-text-soft">Seleziona la chat</span>
            <select name="luogo" class="gdrcd-select mt-1 w-full">
                <?php while ($r_chat = gdrcd_query($chat_res, 'fetch')): ?>
                    <option value="<?= (int)$r_chat['id'] ?>"><?= gdrcd_filter('out', $r_chat['nome']) ?></option>
                <?php endwhile; gdrcd_query($chat_res, 'free'); ?>
            </select>
        </label>
    </article>

    <div class="grid md:grid-cols-2 gap-4">
        <?php foreach (['a' => 'inizio', 'b' => 'fine'] as $sx => $label): ?>
        <article class="gdrcd-card space-y-3">
            <h3 class="font-display text-lg text-gdrcd-accent">Data di <?= $label ?></h3>
            <div class="grid grid-cols-3 gap-2">
                <label class="block">
                    <span class="text-xs text-gdrcd-text-soft">Giorno</span>
                    <select name="day_<?= $sx ?>" class="gdrcd-select mt-1 w-full">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs text-gdrcd-text-soft">Mese</span>
                    <select name="month_<?= $sx ?>" class="gdrcd-select mt-1 w-full">
                        <?php foreach ($mesi_avail as $mm): ?>
                            <option value="<?= $mm ?>" <?= $mm === $mese ? 'selected' : '' ?>><?= $mm ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs text-gdrcd-text-soft">Anno</span>
                    <input type="text" value="<?= $anno ?>" class="gdrcd-input mt-1 w-full" disabled>
                </label>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="block">
                    <span class="text-xs text-gdrcd-text-soft">Ora</span>
                    <select name="hour_<?= $sx ?>" class="gdrcd-select mt-1 w-full">
                        <?php for ($i = 0; $i <= 23; $i++): ?>
                            <option value="<?= $i ?>"><?= sprintf('%02d', $i) ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs text-gdrcd-text-soft">Minuti</span>
                    <select name="minut_<?= $sx ?>" class="gdrcd-select mt-1 w-full">
                        <?php for ($i = 0; $i <= 60; $i += 5): ?>
                            <option value="<?= $i ?>"><?= sprintf('%02d', $i) ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <article class="gdrcd-card space-y-3">
        <label class="block">
            <span class="text-sm text-gdrcd-text-soft">Tag</span>
            <input name="ab" type="text" value="" class="gdrcd-input mt-1 w-full" placeholder="Brevi tag per ritrovare la giocata">
            <span class="text-xs text-gdrcd-text-soft">I tag possono essere utili per ritrovare rapidamente una role.</span>
        </label>
        <label class="block">
            <span class="text-sm text-gdrcd-text-soft">Note di trama</span>
            <input name="quest" type="text" value="" class="gdrcd-input mt-1 w-full" placeholder="Breve riassunto delle interazioni di trama">
            <span class="text-xs text-gdrcd-text-soft">In assenza di una segnalazione un GM non riceve alcuna notifica.</span>
        </label>
    </article>

    <div class="flex justify-end gap-2">
        <input type="hidden" name="op" value="send_segn">
        <input type="hidden" name="mese" value="<?= $mese ?>">
        <input type="hidden" name="anno" value="<?= $anno ?>">
        <button type="submit" class="gdrcd-btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Registra la giocata
        </button>
    </div>
</form>

<?php $render_back(); ?>
