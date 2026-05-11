<?php

/** Homepage
 * Markup e procedure della homepage
 * @author Blancks
 */

/*
 * Includo i Crediti
 */
require 'includes/credits.inc.php';

/*
 * Conteggio utenti online
 */
$users = gdrcd_query("SELECT COUNT(nome) AS online FROM personaggio WHERE ora_entrata > ora_uscita AND DATE_ADD(ultimo_refresh, INTERVAL 4 MINUTE) > NOW()");

?>
<header class="gdrcd-topbar">
    <div class="gdrcd-topbar-inner">
        <div>
            <h1 class="gdrcd-brand">
                <a href="index.php"><?= $MESSAGE['homepage']['main_content']['site_title'] ?></a>
            </h1>
            <div class="gdrcd-brand-subtitle">
                <?= $MESSAGE['homepage']['main_content']['site_subtitle'] ?>
            </div>
        </div>

        <form action="login.php" id="do_login" method="post" class="gdrcd-login-inline">
            <?= gdrcd_csrf_field() ?>
            <div>
                <label class="gdrcd-label" for="username"><?= $MESSAGE['homepage']['forms']['username'] ?></label>
                <input class="gdrcd-input" type="text" id="username" name="login1"/>
            </div>
            <div>
                <label class="gdrcd-label" for="password"><?= $MESSAGE['homepage']['forms']['password'] ?></label>
                <input class="gdrcd-input" type="password" id="password" name="pass1"/>
            </div>
            <?php if (!empty($PARAMETERS['themes']['available']) and count($PARAMETERS['themes']['available']) > 1): ?>
                <div>
                    <label class="gdrcd-label" for="theme"><?= gdrcd_filter('out', $MESSAGE['homepage']['forms']['theme_choice']) ?></label>
                    <select class="gdrcd-select" name="theme" id="theme">
                        <?php
                        foreach ($PARAMETERS['themes']['available'] as $k => $name) {
                            echo '<option value="' . gdrcd_filter('out', $k) . '"';
                            if ($k == $PARAMETERS['themes']['current_theme']) {
                                echo ' selected="selected"';
                            }
                            echo '>' . gdrcd_filter('out', $name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit" class="gdrcd-btn-primary mb-0.5">
                <?= $MESSAGE['homepage']['forms']['login'] ?>
            </button>
        </form>
    </div>
</header>

<main class="flex-1 w-full px-4 md:px-6 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-[18rem_minmax(0,1fr)] gap-6">

        <aside class="space-y-4">
            <nav class="gdrcd-widget" aria-label="Sezioni homepage">
                <div class="gdrcd-widget-title">Esplora</div>
                <div class="gdrcd-nav-list">
                    <a href="index.php?page=homepage&content=iscrizione"><?= $MESSAGE['homepage']['registration'] ?></a>
                    <a href="index.php?page=homepage&content=user_regolamento"><?= $MESSAGE['homepage']['rules'] ?></a>
                    <a href="index.php?page=homepage&content=user_ambientazione"><?= $MESSAGE['homepage']['storyline'] ?></a>
                    <a href="index.php?page=homepage&content=user_razze"><?= $MESSAGE['homepage']['races'] ?></a>
                </div>
            </nav>

            <div class="gdrcd-widget">
                <div class="gdrcd-widget-title">Online</div>
                <div class="gdrcd-widget-body">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-gdrcd-success"></span>
                        <span class="text-gdrcd-text font-semibold"><?= (int)$users['online'] ?></span>
                        <span class="gdrcd-muted"><?= gdrcd_filter('out', $MESSAGE['homepage']['forms']['online_now']) ?></span>
                    </div>
                </div>
            </div>

            <div class="gdrcd-widget">
                <div class="gdrcd-widget-title"><?= gdrcd_filter('out', $MESSAGE['homepage']['forms']['forgot']) ?></div>
                <div class="gdrcd-widget-body">
                    <?php include __DIR__ . '/reset_password.inc.php'; ?>
                </div>
            </div>

            <div class="gdrcd-widget">
                <div class="gdrcd-widget-title"><?= gdrcd_filter('out', $MESSAGE['interface']['user']['stats']['page_name']) ?></div>
                <div class="gdrcd-widget-body !py-2">
                    <?php include __DIR__ . '/user_stats.inc.php'; ?>
                </div>
            </div>
        </aside>

        <section class="gdrcd-card">
            <div class="gdrcd-card-body">
                <?php gdrcd_load_modules('homepage__' . $MODULE['content']); ?>
            </div>
        </section>

    </div>
</main>

<footer class="gdrcd-page-footer">
    <div class="gdrcd-page-footer-inner space-y-1">
        <p><?= $REFERENCES ?></p>
        <p><?= $CREDITS, ' ', $LICENCE ?></p>
        <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs">
            <a href="main.php?page=privacy_policy">Informativa privacy</a>
            <span class="text-gdrcd-subtle" aria-hidden="true">&middot;</span>
            <a href="main.php?page=tos">Termini di servizio</a>
        </p>
    </div>
</footer>
