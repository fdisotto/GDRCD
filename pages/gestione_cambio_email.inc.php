<?php
/**
 * Gestione cambio email (main.php?page=gestione_cambio_email)
 * Permette ai moderatori di forzare l'email di un PG.
 */

$lbl = $MESSAGE['interface']['administration']['email'];
$op  = $_POST['op'] ?? null;
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['page_name']) ?></h2>
        <p class="gdrcd-muted">Modifica l'indirizzo email associato a un personaggio.</p>
    </header>

    <?php if ($op === 'force'): ?>
        <?php if ($_SESSION['permessi'] >= MODERATOR):
            $query = ($_SESSION['permessi'] === SUPERUSER)
                ? "UPDATE personaggio SET email = '" . gdrcd_encript($_POST['new_email']) . "'
                   WHERE nome = '" . gdrcd_filter_in($_POST['account']) . "'"
                : "UPDATE personaggio SET email = '" . gdrcd_encript($_POST['new_email']) . "'
                   WHERE nome = '" . gdrcd_filter_in($_POST['account']) . "' AND permessi < " . SUPERUSER;
            gdrcd_query($query);
            ?>
            <div class="gdrcd-alert-success">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div>
                    <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
                    <span class="text-gdrcd-muted">·</span>
                    <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $_POST['account']) ?></strong>
                </div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-error">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $MESSAGE['warning']['cant_do']) ?></div>
            </div>
        <?php endif; ?>

        <div>
            <a href="main.php?page=gestione_cambio_email" class="gdrcd-btn-ghost">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <?= gdrcd_filter('out', $lbl['link']['back']) ?>
            </a>
        </div>

    <?php elseif ($op === null && $_SESSION['permessi'] >= MODERATOR):
        $query = ($_SESSION['permessi'] === SUPERUSER)
            ? "SELECT nome FROM personaggio ORDER BY nome"
            : "SELECT nome FROM personaggio WHERE permessi < " . SUPERUSER . " ORDER BY nome";
        $result = gdrcd_query($query, 'result');
        ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body">
                <form action="main.php?page=gestione_cambio_email" method="post" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label" for="ce_account">
                                <?= gdrcd_filter('out', $lbl['new']) ?>
                            </label>
                            <select class="gdrcd-select" id="ce_account" name="account" required>
                                <option value="" disabled selected>
                                    <?= gdrcd_filter('out', $lbl['change_to']) ?>
                                </option>
                                <?php while ($row = gdrcd_query($result, 'fetch')): ?>
                                    <option value="<?= gdrcd_filter('out', $row['nome']) ?>">
                                        <?= gdrcd_filter('out', $row['nome']) ?>
                                    </option>
                                <?php endwhile;
                                gdrcd_query($result, 'free');
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="gdrcd-label" for="ce_email">
                                <?= gdrcd_filter('out', $lbl['email']) ?>
                            </label>
                            <input class="gdrcd-input" type="email" id="ce_email" name="new_email" required/>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                        <input type="hidden" name="op" value="force"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $lbl['submit']['user']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

</div>
