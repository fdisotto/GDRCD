<?php
$dont_check = true;
require 'header.inc.php'; /*Header comune*/

$installer_message = null;
$installer_status  = null; // 'success' | 'error'

if (!empty($_POST['do_update'])) {
    $target_migration = empty($_POST['target']) ? null : (int)$_POST['target'];
    try {
        DbMigrationEngine::updateDbSchema($target_migration);
        $installer_message = gdrcd_filter('out', $MESSAGE['homepage']['installer']['done']);
        $installer_status  = 'success';
    } catch (Exception $e) {
        $installer_message = gdrcd_filter('out', $e->getMessage());
        $installer_status  = 'error';
    }
}

$db_needs_update = DbMigrationEngine::dbNeedsUpdate();
?>
<div class="gdrcd-shell">
    <div class="gdrcd-container-sm">

        <div class="text-center mb-8">
            <span class="gdrcd-icon-circle mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10"/>
                </svg>
            </span>
            <h1 class="gdrcd-h1">
                GDRCD <span class="text-gdrcd-accent"><?= htmlspecialchars($PARAMETERS['info']['GDRCD']) ?></span>
            </h1>
            <p class="gdrcd-muted mt-2"><?= htmlspecialchars($PARAMETERS['info']['site_name']) ?></p>
        </div>

        <section class="gdrcd-card">
            <?php if ($installer_message !== null): ?>
                <div class="px-6 md:px-8 pt-6">
                    <div class="<?= $installer_status === 'success' ? 'gdrcd-alert-success' : 'gdrcd-alert-error' ?>">
                        <?php if ($installer_status === 'success'): ?>
                            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                        <?php endif; ?>
                        <div><?= $installer_message ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="gdrcd-card-body">
                <?php if ($db_needs_update): ?>
                    <form method="post" action="installer.php" class="space-y-6">
                        <div>
                            <h2 class="gdrcd-h2 mb-2">
                                <?= $MESSAGE['homepage']['installer']['install_title'] ?>
                            </h2>
                            <p class="gdrcd-prose">
                                <?= $MESSAGE['homepage']['installer']['install_text'] ?>
                            </p>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2">
                            <a href="/" class="gdrcd-btn-ghost">Annulla</a>
                            <button type="submit" name="do_update" value="Installa" class="gdrcd-btn-primary">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                Installa
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center space-y-4 py-2">
                        <span class="gdrcd-icon-circle bg-gdrcd-success-soft text-gdrcd-success border-green-200">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <h2 class="gdrcd-h3">Database aggiornato</h2>
                        <p class="gdrcd-muted">Il database è alla versione corrente. Nessuna installazione necessaria.</p>
                        <a href="index.php" class="gdrcd-btn-primary">
                            Vai al gioco
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="mt-6 text-center">
            <a href="index.php" class="gdrcd-link-quiet text-xs">
                ← <?= gdrcd_filter('out', $PARAMETERS['info']['homepage_name']) ?>
            </a>
        </div>

    </div>
</div>
<?php require('footer.inc.php');  /*Footer comune*/ ?>
