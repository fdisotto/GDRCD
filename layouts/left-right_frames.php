<?php
/**
 * Layout left-right: app shell con sidebar sinistra, contenuto principale, sidebar destra.
 * Tutto basato su design system (vedi docs/design-system.md).
 *
 * Il vecchio comportamento `?css=true` (CSS inline servito come stylesheet)
 * è stato rimosso: ora tutto lo styling proviene da Tailwind/design system.
 */

// Compat: se qualche header chiama ancora il vecchio endpoint CSS, restituisci vuoto.
if (isset($_GET['css'])) {
    header('Content-Type:text/css; charset=utf-8');
    exit;
}

$has_left  = ($PARAMETERS['left_column']['activate']  ?? 'OFF') === 'ON';
$has_right = ($PARAMETERS['right_column']['activate'] ?? 'OFF') === 'ON';

$me = htmlspecialchars($_SESSION['login'] ?? '');
?>

<header class="gdrcd-topbar">
    <div class="gdrcd-topbar-inner">
        <div>
            <h1 class="gdrcd-brand">
                <a href="main.php"><?= htmlspecialchars($PARAMETERS['info']['site_name']) ?></a>
            </h1>
            <?php if ($me !== ''): ?>
                <div class="gdrcd-brand-subtitle">
                    Bentornato, <span class="text-gdrcd-text font-medium"><?= $me ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <a href="main.php?page=scheda" class="gdrcd-btn-secondary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Scheda
            </a>
            <a href="logout.php" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Esci
            </a>
        </div>
    </div>
</header>

<main class="flex-1 w-full max-w-7xl mx-auto px-4 md:px-6 py-6">
    <div class="grid gap-6
                <?= $has_left && $has_right ? 'lg:grid-cols-[16rem_minmax(0,1fr)_16rem]' : '' ?>
                <?= $has_left && !$has_right ? 'lg:grid-cols-[16rem_minmax(0,1fr)]'      : '' ?>
                <?= !$has_left && $has_right ? 'lg:grid-cols-[minmax(0,1fr)_16rem]'      : '' ?>">

        <?php if ($has_left): ?>
            <aside class="space-y-4 order-2 lg:order-1">
                <?php foreach ($PARAMETERS['left_column']['box'] as $box): ?>
                    <div class="gdrcd-widget">
                        <div class="gdrcd-widget-body">
                            <?php gdrcd_load_modules($box['page'], $box); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </aside>
        <?php endif; ?>

        <section class="order-1 lg:order-2 min-w-0">
            <div class="gdrcd-card">
                <div class="gdrcd-card-body">
                    <?php gdrcd_load_modules($strInnerPage); ?>
                </div>
            </div>
        </section>

        <?php if ($has_right): ?>
            <aside class="space-y-4 order-3">
                <?php foreach ($PARAMETERS['right_column']['box'] as $box): ?>
                    <div class="gdrcd-widget">
                        <div class="gdrcd-widget-body">
                            <?php gdrcd_load_modules($box['page'], $box); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </aside>
        <?php endif; ?>

    </div>
</main>
