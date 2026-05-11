<?php
/**
 * Hub menu utente — elenco link a card uniformi.
 */

$title = gdrcd_filter('out', $PARAMETERS['user_page_name'] ?? 'Menu utente');

$entries = [];
foreach ($PARAMETERS['user'] as $link_menu) {
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span>
            <?= $title ?>
        </h2>
        <p class="text-gdrcd-text-soft">Strumenti utente e impostazioni account.</p>
    </header>

    <?php if (empty($entries)): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['ui']['empty']['no_entries']) ?></div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($entries as $link_menu):
                $text = gdrcd_filter('out', $link_menu['text']);
                $url  = $link_menu['url'];
                $img  = !empty($link_menu['image_file']) ? $link_menu['image_file'] : null;
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
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
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
