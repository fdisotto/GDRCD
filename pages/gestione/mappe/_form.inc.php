<?php
/**
 * Template form mappa cliccabile — usato da create.inc.php e edit.inc.php.
 * Aspetta: $record (array), $is_edit (bool), $lbl_m (array).
 */
?>

<section class="gdrcd-card">
    <div class="gdrcd-card-header">
        <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica mappa' : 'Nuova mappa' ?></h3>
    </div>
    <div class="gdrcd-card-body">
        <form action="main.php?page=gestione/mappe" method="post" class="space-y-5">
            <?= gdrcd_csrf_field() ?>

            <div>
                <label class="gdrcd-label" for="mp_nome"><?= gdrcd_filter('out', $lbl_m['name']) ?></label>
                <input class="gdrcd-input" type="text" id="mp_nome" name="nome"
                       value="<?= gdrcd_filter('out', $record['nome']) ?>" required/>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-start gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                    <input type="checkbox" name="mobile" value="is_mobile"
                           <?= ((int)$record['mobile'] === 1) ? 'checked' : '' ?>
                           class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring mt-0.5"/>
                    <span>
                        <span class="font-medium"><?= gdrcd_filter('out', $lbl_m['is_mobile']) ?></span>
                        <span class="block text-xs text-gdrcd-muted"><?= gdrcd_filter('out', $lbl_m['is_mobile_info']) ?></span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                    <input type="checkbox" name="principale" value="is_main"
                           <?= ((int)$record['principale'] === 1) ? 'checked' : '' ?>
                           class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring mt-0.5"/>
                    <span>
                        <span class="font-medium"><?= gdrcd_filter('out', $lbl_m['is_main']) ?></span>
                        <span class="block text-xs text-gdrcd-muted"><?= gdrcd_filter('out', $lbl_m['is_main_info']) ?></span>
                    </span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="gdrcd-label" for="mp_pos"><?= gdrcd_filter('out', $lbl_m['position']) ?></label>
                    <input class="gdrcd-input" type="number" id="mp_pos" name="posizione"
                           value="<?= (int)$record['posizione'] ?>"/>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl_m['position_info']) ?></p>
                </div>
                <div>
                    <label class="gdrcd-label" for="mp_w"><?= gdrcd_filter('out', $lbl_m['width']) ?></label>
                    <input class="gdrcd-input" type="number" id="mp_w" name="larghezza"
                           value="<?= gdrcd_filter('out', $record['larghezza']) ?>"/>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl_m['width_info']) ?></p>
                </div>
                <div>
                    <label class="gdrcd-label" for="mp_h"><?= gdrcd_filter('out', $lbl_m['height']) ?></label>
                    <input class="gdrcd-input" type="number" id="mp_h" name="altezza"
                           value="<?= gdrcd_filter('out', $record['altezza']) ?>"/>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl_m['height_info']) ?></p>
                </div>
            </div>

            <div>
                <label class="gdrcd-label" for="mp_imm"><?= gdrcd_filter('out', $lbl_m['image']) ?></label>
                <input class="gdrcd-input" type="text" id="mp_imm" name="immagine"
                       value="<?= gdrcd_filter('out', $record['immagine']) ?>"/>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                <a href="main.php?page=gestione/mappe" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= gdrcd_filter('out', $lbl_m['link']['back']) ?>
                </a>
                <input type="hidden" name="op" value="save"/>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id_click" value="<?= (int)$record['id_click'] ?>"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= gdrcd_filter('out', $lbl_m['submit']['edit']) ?>
                    </button>
                <?php else: ?>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <?= gdrcd_filter('out', $lbl_m['submit']['create']) ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
