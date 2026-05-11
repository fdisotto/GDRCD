<?php
/**
 * Form composizione di un nuovo topic o reply nel forum.
 */

$padre  = gdrcd_filter('num', $_REQUEST['what']  ?? -1);
$araldo = gdrcd_filter('num', $_REQUEST['where'] ?? 0);
$quote  = gdrcd_filter('num', $_REQUEST['quote'] ?? 0);

$join = '';
$cond = '';
if ((int)$padre !== -1) {
    $join = ' INNER JOIN messaggioaraldo AS MA ON araldo.id_araldo = MA.id_araldo ';
    $cond = ' AND id_messaggio = ' . $padre . ' AND id_messaggio_padre = -1';
}

$araldoData = gdrcd_query(
    "SELECT count(*) AS N FROM araldo" . $join . " WHERE araldo.id_araldo = " . $araldo . $cond
);

if ((int)$araldoData['N'] === 0): ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['forums']['not_exists']) ?></div>
    </div>
<?php
    return;
endif;

$is_topic = ((int)$padre === -1);

// Pre-fill quote
$quote_text = '';
if ($quote > 0) {
    $q = gdrcd_query("SELECT messaggio, autore FROM messaggioaraldo WHERE id_messaggio = " . $quote);
    if (!empty($q['autore'])) {
        $quote_text = '[quote=' . $q['autore'] . ']' . $q['messaggio'] . '[/quote]';
    }
}
?>

<div class="space-y-4">
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3"><?= $is_topic ? 'Nuovo topic' : 'Rispondi' ?></h3>
        </div>
        <div class="gdrcd-card-body">
            <form action="main.php?page=forum" method="post" class="space-y-5">
                <?= gdrcd_csrf_field() ?>
                <?php if ($is_topic): ?>
                    <div>
                        <label class="gdrcd-label" for="fc_titolo"><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['insert']['title']) ?></label>
                        <input class="gdrcd-input" type="text" id="fc_titolo" name="titolo" required/>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="gdrcd-label" for="fc_messaggio"><?= gdrcd_filter('out', $MESSAGE['interface']['forums']['insert']['message']) ?></label>
                    <textarea class="gdrcd-textarea" id="fc_messaggio" name="messaggio" rows="12" required data-bbcode><?= gdrcd_filter('out', $quote_text) ?></textarea>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <a href="main.php?page=forum" class="gdrcd-btn-ghost">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['topic']) ?>
                    </a>
                    <input type="hidden" name="op" value="insert"/>
                    <input type="hidden" name="araldo" value="<?= (int)$araldo ?>"/>
                    <input type="hidden" name="padre" value="<?= (int)$padre ?>"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
