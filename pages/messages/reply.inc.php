<?php
/**
 * Form risposta (reply / attach) a un messaggio esistente.
 */

$lbl = $MESSAGE['interface']['messages'];
$prefill_dest    = gdrcd_filter('get', $_REQUEST['reply_dest']    ?? '');
$prefill_subject = trim($_REQUEST['reply_subject'] ?? '');
$prefill_tipo    = $_REQUEST['reply_tipo'] ?? null;
$prefill_body    = isset($_POST['testo']) ? ("\n\n\n[" . gdrcd_filter('out', trim($_POST['testo'])) . "]") : '';
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $lbl['reply']) ?></h2>
        <p class="gdrcd-muted">A <strong class="text-gdrcd-text"><?= htmlspecialchars($prefill_dest) ?></strong>.</p>
    </header>

    <section class="gdrcd-card">
        <div class="gdrcd-card-body">
            <form action="main.php?page=messages_center" method="post" class="space-y-5">
                <?= gdrcd_csrf_field() ?>

                <div>
                    <label class="gdrcd-label" for="rep_dest"><?= gdrcd_filter('out', $lbl['recipient']) ?></label>
                    <input class="gdrcd-input" type="text" id="rep_dest" name="destinatario" list="personaggi"
                           placeholder="Nome del personaggio"
                           value="<?= htmlspecialchars($prefill_dest) ?>" required/>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $lbl['multiple']['info']) ?></p>
                </div>

                <?php echo gdrcd_list('personaggi'); ?>

                <?php if ($_SESSION['permessi'] >= GUILDMODERATOR): ?>
                    <div>
                        <label class="gdrcd-label" for="rep_multipli">Modalità invio</label>
                        <select class="gdrcd-select" id="rep_multipli" name="multipli" required>
                            <option value="private" selected><?= gdrcd_filter('out', $lbl['multiple']['options']['private']) ?></option>
                            <option value="presenti"><?= gdrcd_filter('out', $lbl['multiple']['options']['online']) ?></option>
                            <?php if ($_SESSION['permessi'] >= MODERATOR): ?>
                                <option value="broadcast"><?= gdrcd_filter('out', $lbl['multiple']['options']['all']) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="gdrcd-label" for="rep_tipo"><?= gdrcd_filter('out', $lbl['type']['title']) ?></label>
                        <select class="gdrcd-select" id="rep_tipo" name="tipo" required>
                            <?php foreach ($lbl['type']['options'] as $tipoID => $tipoNome):
                                $sel = ((string)$prefill_tipo === (string)$tipoID) ? ' selected' : '';
                                ?>
                                <option value="<?= gdrcd_filter('out', $tipoID) ?>"<?= $sel ?>>
                                    <?= gdrcd_filter('out', $tipoNome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="rep_oggetto"><?= gdrcd_filter('out', $lbl['subject']) ?></label>
                        <input class="gdrcd-input" type="text" id="rep_oggetto" name="oggetto"
                               placeholder="Oggetto del messaggio"
                               value="<?= htmlspecialchars($prefill_subject) ?>" required/>
                    </div>
                </div>

                <div>
                    <label class="gdrcd-label" for="rep_testo"><?= gdrcd_filter('out', $lbl['body']) ?></label>
                    <textarea class="gdrcd-textarea" id="rep_testo" name="testo" rows="10" required><?= $prefill_body ?></textarea>
                    <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode']) ?></p>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <a href="main.php?page=messages_center&offset=0" class="gdrcd-btn-ghost">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <?= gdrcd_filter('out', $lbl['go_back']) ?>
                    </a>
                    <input type="hidden" name="op" value="send_message"/>
                    <input type="hidden" name="reply_attach" value="<?= gdrcd_filter('get', $_POST['reply_attach'] ?? '') ?>"/>
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
