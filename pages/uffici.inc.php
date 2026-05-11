<?php
/**
 * Pagina servizi/uffici — elenco link a card uniformi.
 */

$theme = $PARAMETERS['themes']['current_theme'];
$title = gdrcd_filter('out', $PARAMETERS['office_page_name'] ?? 'Servizi');

$entries = [];
foreach ($PARAMETERS['office'] as $link_menu) {
    if (empty($link_menu['url']) || empty($link_menu['text'])) continue;
    if (!isset($link_menu['access_level'])) continue;
    if ($link_menu['access_level'] > $_SESSION['permessi']) continue;
    $entries[] = $link_menu;
}
?>

<div class="space-y-6">
    <header class="space-y-1">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>
                </svg>
            </span>
            <?= $title ?>
        </h2>
        <p class="text-gdrcd-text-soft">Servizi e uffici disponibili.</p>
    </header>

    <?php if (empty($entries)): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['ui']['empty']['no_services']) ?></div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($entries as $link_menu):
                $text  = gdrcd_filter('out', $link_menu['text']);
                $url   = $link_menu['url'];
                $img   = !empty($link_menu['image_file'])
                    ? 'themes/' . $theme . '/imgs' . $link_menu['image_file']
                    : null;
            ?>
                <a href="<?= htmlspecialchars($url) ?>"
                   class="gdrcd-card group hover:border-gdrcd-accent hover:shadow-lg transition-all duration-200 p-4 flex items-center gap-3">
                    <div class="shrink-0">
                        <?php if ($img !== null): ?>
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= $text ?>"
                                 class="w-12 h-12 object-contain rounded-md bg-gdrcd-panel-alt p-1"/>
                        <?php else: ?>
                            <span class="inline-flex items-center justify-center w-12 h-12 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent group-hover:bg-gdrcd-accent group-hover:text-white transition">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.93 23.93 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-display text-gdrcd-text group-hover:text-gdrcd-accent transition truncate">
                            <?= $text ?>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-gdrcd-text-soft group-hover:text-gdrcd-accent group-hover:translate-x-0.5 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
