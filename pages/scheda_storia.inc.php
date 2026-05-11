<?php
/**
 * Scheda PG — storia/background.
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet'] ?? 'Personaggio sconosciuto') . '</div>';
    return;
}

$personaggio = gdrcd_query(
    "SELECT storia FROM personaggio
     WHERE nome = '" . gdrcd_filter('in', $_REQUEST['pg']) . "'"
);

$render_text = function (?string $txt) use ($PARAMETERS) {
    if ($txt === null || $txt === '') return '<span class="text-gdrcd-subtle italic">Nessun contenuto.</span>';
    if (($PARAMETERS['mode']['user_bbcode'] ?? 'OFF') === 'ON') {
        $type = $PARAMETERS['settings']['user_bbcode']['type'] ?? '';
        $free = ($PARAMETERS['settings']['bbd']['free_html'] ?? 'OFF') === 'ON';
        if ($type === 'bbd' && $free) return bbdecoder(gdrcd_html_filter($txt), true);
        if ($type === 'bbd')           return bbdecoder(gdrcd_filter('out', $txt), true);
        return gdrcd_bbcoder(gdrcd_filter('out', $txt));
    }
    return gdrcd_html_filter($txt);
};

$lbl_m = $MESSAGE['interface']['sheet']['menu'];
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['background']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $_REQUEST['pg']) ?></span>
        </h2>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <section class="gdrcd-card">
        <div class="gdrcd-card-body gdrcd-prose">
            <?= $render_text($personaggio['storia']) ?>
        </div>
    </section>
</div>
