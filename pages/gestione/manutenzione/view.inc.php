<?php
/**
 * Form di manutenzione DB. Ogni operazione = una card.
 * I form POST a main.php?page=gestione/manutenzione con op=<chiave>.
 */

if (!gdrcd_controllo_permessi(SUPERUSER)) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl   = $MESSAGE['interface']['administration']['maintenance'];
$mesi  = $lbl['months'];
$submit_label = $MESSAGE['interface']['forms']['submit'];

/** Render select mesi (0..12 o 1..12). */
$render_mesi = function (string $id, int $min = 0, int $max = 12) use ($mesi) {
    $out = '<select class="gdrcd-select" id="' . htmlspecialchars($id) . '" name="mesi">';
    for ($i = $min; $i <= $max; $i++) {
        $out .= '<option value="' . $i . '">' . $i . ' ' . gdrcd_filter('out', $mesi) . '</option>';
    }
    $out .= '</select>';
    return $out;
};

/** Render card operazione di manutenzione. */
$render_op = function (array $opts) use ($submit_label) {
    $title       = $opts['title'];
    $desc        = $opts['desc']    ?? null;
    $info        = $opts['info']    ?? null;
    $field_html  = $opts['field']   ?? '';
    $op          = $opts['op'];
    $btn_label   = $opts['btn']     ?? $submit_label;
    $icon        = $opts['icon']    ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>';

    ob_start(); ?>
    <section class="gdrcd-card flex flex-col h-full">
        <div class="gdrcd-card-header flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gdrcd-error-soft text-gdrcd-error border border-red-200 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><?= $icon ?></svg>
            </span>
            <div>
                <h3 class="gdrcd-h3"><?= $title ?></h3>
                <?php if ($desc): ?><p class="gdrcd-muted text-xs"><?= $desc ?></p><?php endif; ?>
            </div>
        </div>
        <div class="gdrcd-card-body flex-1 space-y-3">
            <form action="main.php?page=gestione/manutenzione" method="post" class="space-y-3">
                <?= gdrcd_csrf_field() ?>
                <?= $field_html ?>
                <?php if ($info): ?>
                    <p class="gdrcd-help"><?= $info ?></p>
                <?php endif; ?>
                <div class="flex justify-end pt-2 border-t border-gdrcd-border">
                    <input type="hidden" name="op" value="<?= htmlspecialchars($op) ?>"/>
                    <button type="submit" class="gdrcd-btn-danger">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                        <?= $btn_label ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
    <?php
    return ob_get_clean();
};
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    <?= $render_op([
        'title' => gdrcd_filter('out', $lbl['old_log']),
        'op'    => 'old_log',
        'field' => '<label class="gdrcd-label" for="mn_log">Più vecchi di</label>' . $render_mesi('mn_log'),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
    ]) ?>

    <?= $render_op([
        'title' => gdrcd_filter('out', $lbl['old_chat']),
        'op'    => 'old_chat',
        'field' => '<label class="gdrcd-label" for="mn_chat">Più vecchi di</label>' . $render_mesi('mn_chat'),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
    ]) ?>

    <?= $render_op([
        'title' => gdrcd_filter('out', $lbl['old_messages']),
        'desc'  => gdrcd_filter('out', $lbl['old_messages_info']),
        'op'    => 'old_messages',
        'field' => '<label class="gdrcd-label" for="mn_msg">Più vecchi di</label>' . $render_mesi('mn_msg'),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
    ]) ?>

    <?= $render_op([
        'title' => gdrcd_filter('out', $lbl['deleted']),
        'desc'  => gdrcd_filter('out', $lbl['deleted_info']),
        'op'    => 'deleted',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
    ]) ?>

    <?= $render_op([
        'title' => gdrcd_filter('out', $lbl['missing']),
        'desc'  => gdrcd_filter('out', $lbl['missing_info']),
        'op'    => 'missing',
        'field' => '<label class="gdrcd-label" for="mn_missing">Inattivi da</label>' . $render_mesi('mn_missing', 1, 12),
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
    ]) ?>

    <?= $render_op([
        'title' => gdrcd_filter('out', $lbl['blacklisted']),
        'desc'  => gdrcd_filter('out', $lbl['blacklisted_info']),
        'op'    => 'blacklisted',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 11-12.728 0 9 9 0 0112.728 0zM12 9v3m0 3h.01"/>',
    ]) ?>

</div>
