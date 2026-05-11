<?php
/**
 * Pannello di gestione (main.php?page=gestione)
 * Render dei link di amministrazione raggruppati in categorie, come card grid.
 * Le voci provengono da $PARAMETERS['administration'] in config.inc.php.
 */

/* ------------------------------------------------------------------
 * Mapping categorie → chiavi config (icone heroicon-style inline).
 * Una chiave NON elencata finisce in "Altro".
 * ------------------------------------------------------------------ */
$categories = [
    'Log' => [
        'title' => 'Log',
        'desc'  => 'Storico attività di gioco.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3M5 7a2 2 0 012-2h10a2 2 0 012 2v3"/>',
        'keys'  => ['log_chat', 'log_eventi', 'log_messaggi'],
    ],
    'Segnalazioni' => [
        'title' => 'Segnalazioni',
        'desc'  => 'Giocate, esiti, moderazione.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21l3-1.5L9 21l3-1.5L15 21l3-1.5L21 21V5l-3 1.5L15 5l-3 1.5L9 5 6 6.5 3 5v16z"/>',
        'keys'  => ['send_GM', 'esiti'],
    ],
    'Utenti' => [
        'title' => 'Utenti',
        'desc'  => 'Permessi e account giocatori.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-3a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'keys'  => ['email', 'levels'],
    ],
    'Contenuti' => [
        'title' => 'Contenuti',
        'desc'  => 'Mondo di gioco, regole, oggetti.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
        'keys'  => ['plot', 'rules', 'skills', 'races', 'guilds', 'forums', 'items', 'locations', 'maps'],
    ],
    'Sistema' => [
        'title' => 'Sistema',
        'desc'  => 'Manutenzione e operazioni tecniche.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'keys'  => ['maintenance'],
    ],
];

/* ------------------------------------------------------------------
 * Distribuzione voci config nelle categorie.
 * ------------------------------------------------------------------ */
$assigned = [];
foreach ($categories as $cat_key => &$cat) {
    $cat['items'] = [];
}
unset($cat);

$other = [];
$permessi = (int)($_SESSION['permessi'] ?? 0);

foreach ($PARAMETERS['administration'] as $key => $entry) {
    if (!is_array($entry)) continue;
    if (empty($entry['url']) || empty($entry['text'])) continue;
    if (!isset($entry['access_level'])) continue;
    if ($entry['access_level'] > $permessi) continue;

    $placed = false;
    foreach ($categories as $cat_key => &$cat) {
        if (in_array($key, $cat['keys'], true)) {
            $cat['items'][] = ['key' => $key] + $entry;
            $placed = true;
            break;
        }
    }
    unset($cat);

    if (!$placed) {
        $other[] = ['key' => $key] + $entry;
    }
}

if (!empty($other)) {
    $categories['_altro'] = [
        'title' => 'Altro',
        'desc'  => 'Voci aggiuntive non categorizzate.',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>',
        'items' => $other,
    ];
}

/* ------------------------------------------------------------------
 * Icone per chiavi specifiche (override). Heroicons-style minimal.
 * ------------------------------------------------------------------ */
$item_icons = [
    'log_chat'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
    'log_eventi'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
    'log_messaggi'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
    'send_GM'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4a4 4 0 014-4h10a4 4 0 014 4v4M16 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
    'esiti'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
    'email'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
    'levels'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
    'skills'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    'plot'          => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>',
    'forums'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>',
    'guilds'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
    'locations'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0L6.343 16.657a8 8 0 1111.314 0zM12 12a2 2 0 100-4 2 2 0 000 4z"/>',
    'maps'          => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>',
    'items'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
    'races'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
    'rules'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
    'maintenance'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
];

$default_item_icon = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>';
?>

<div class="space-y-8">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $PARAMETERS['administration_page_name']) ?></h2>
        <p class="gdrcd-muted">Strumenti di amministrazione e moderazione organizzati per area.</p>
    </header>

    <?php
    $total_items = 0;
    foreach ($categories as $cat) { $total_items += count($cat['items']); }

    if ($total_items === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>Nessuna voce di gestione disponibile con i tuoi permessi.</div>
        </div>
    <?php else: ?>

        <?php foreach ($categories as $cat):
            if (empty($cat['items'])) continue; ?>
            <section class="space-y-3">
                <header class="flex items-center gap-3">
                    <span class="gdrcd-icon-circle w-10 h-10 text-gdrcd-accent">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <?= $cat['icon'] ?>
                        </svg>
                    </span>
                    <div>
                        <h3 class="gdrcd-h3"><?= htmlspecialchars($cat['title']) ?></h3>
                        <?php if (!empty($cat['desc'])): ?>
                            <p class="gdrcd-muted text-xs"><?= htmlspecialchars($cat['desc']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="ml-auto gdrcd-badge-neutral"><?= count($cat['items']) ?></span>
                </header>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($cat['items'] as $item):
                        $icon_svg = $item_icons[$item['key']] ?? $default_item_icon;
                        ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>"
                           class="group gdrcd-card h-full block hover:border-gdrcd-accent-ring/60 hover:shadow-gdrcd-elev transition-all"
                           title="<?= gdrcd_filter('out', $item['text']) ?>">
                            <div class="p-4 flex items-center gap-3 h-full">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30 shrink-0 group-hover:bg-gdrcd-accent group-hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <?= $icon_svg ?>
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gdrcd-text group-hover:text-gdrcd-accent-hover transition-colors leading-snug line-clamp-2 break-words">
                                        <?= gdrcd_filter('out', $item['text']) ?>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-gdrcd-subtle group-hover:text-gdrcd-accent transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

    <?php endif; ?>
</div>
