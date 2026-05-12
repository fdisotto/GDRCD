<?php
/**
 * Menu navigazione scheda PG.
 * Render come pill button group (border + hover accent).
 */

$pg       = $_REQUEST['pg'] ?? '';
$me       = $_SESSION['login'] ?? '';
$permessi = (int)($_SESSION['permessi'] ?? 0);
$current  = $_REQUEST['page'] ?? 'scheda';

$pg_url = urlencode($pg);

$render_link = function (string $page, string $label) use ($pg_url, $current) {
    $active = ($current === $page);
    $cls = $active
        ? 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full bg-gdrcd-accent text-white border border-gdrcd-accent'
        : 'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full text-gdrcd-text-soft border border-gdrcd-border hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent-hover hover:border-gdrcd-accent-ring/60 transition-colors';
    return '<a href="main.php?page=' . $page . '&pg=' . $pg_url . '" class="' . $cls . '">' . $label . '</a>';
};

$lbl_m = $MESSAGE['interface']['sheet']['menu'];

echo $render_link('scheda', 'Scheda');

if ($pg === $me || $permessi >= GUILDMODERATOR) {
    echo $render_link('scheda_modifica', gdrcd_filter('out', $lbl_m['update']));
}
echo $render_link('scheda_descrizione', gdrcd_filter('out', $lbl_m['detail']));
echo $render_link('scheda_storia',      gdrcd_filter('out', $lbl_m['background']));
echo $render_link('scheda_trans',       gdrcd_filter('out', $lbl_m['transictions']));
echo $render_link('scheda_px',          gdrcd_filter('out', $lbl_m['experience']));
echo $render_link('scheda_oggetti',     gdrcd_filter('out', $lbl_m['inventory']));
echo $render_link('scheda_equip',       gdrcd_filter('out', $lbl_m['equipment']));

if (defined('PG_DIARY_ENABLED') && PG_DIARY_ENABLED) {
    echo $render_link('scheda_diario', gdrcd_filter('out', $lbl_m['diary']));
}

echo $render_link('scheda_quest', 'Quest');

// Stampa / export PDF: apre la versione print-friendly in popup. Tab nuova
// per non perdere il contesto della scheda corrente.
$print_url = 'popup.php?page=scheda_print&pg=' . $pg_url;
echo '<a href="' . $print_url . '" target="_blank" rel="noopener"'
   . ' class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full text-gdrcd-text-soft border border-gdrcd-border hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent-hover hover:border-gdrcd-accent-ring/60 transition-colors">'
   . '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">'
   . '<path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>'
   . 'Stampa / PDF</a>';

if ((($permessi >= ROLE_PERM) || ($pg === $me)) && REG_ROLE) {
    echo $render_link('scheda_roles', 'Giocate registrate');
}

if ($permessi >= MODERATOR) {
    echo $render_link('scheda_log', gdrcd_filter('out', $lbl_m['log']));
    echo $render_link('scheda_gst', gdrcd_filter('out', $lbl_m['gst']));
}
