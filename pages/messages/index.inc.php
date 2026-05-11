<?php
/**
 * Inbox messaggi privati (ricevuti / inviati) — layout a card-list, stile mail client.
 */

$offset    = (int)($_REQUEST['offset'] ?? 0);
$per_page  = (int)$PARAMETERS['settings']['messages_per_page'];
$pagebegin = $offset * $per_page;

$isSentMessage = (($_GET['op'] ?? '') === 'inviati');
$msgType       = $isSentMessage ? 'mittente' : 'destinatario';
$delType       = $msgType . '_del';

$sqlMessages = "SELECT * FROM messaggi
                WHERE " . $msgType . " = '" . gdrcd_filter('in', $_SESSION['login']) . "'
                  AND " . $delType . " = 0
                ORDER BY spedito DESC";

$result        = gdrcd_query($sqlMessages . " LIMIT " . $pagebegin . ", " . $per_page, 'result');
$numresults    = (int)gdrcd_query($result, 'num_rows');
$totaleresults = (int)gdrcd_query(gdrcd_query($sqlMessages, 'result'), 'num_rows');

$base_query = $isSentMessage ? '&op=inviati' : '';
$page_label = $PARAMETERS['names']['private_message']['plur'];
?>

<div class="space-y-6">

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="space-y-2">
            <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $page_label) ?></h2>
            <p class="gdrcd-muted">
                <?= $totaleresults ?> messaggi <?= $isSentMessage ? 'inviati' : 'ricevuti' ?>.
            </p>
        </div>
        <a href="main.php?page=messages_center&op=create" class="gdrcd-btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['messages']['new']) ?>
        </a>
    </header>

    <nav class="inline-flex rounded-lg border border-gdrcd-border bg-gdrcd-panel overflow-hidden text-sm">
        <a href="main.php?page=messages_center"
           class="px-4 py-2 font-medium <?= !$isSentMessage ? 'bg-gdrcd-accent text-white' : 'text-gdrcd-text-soft hover:bg-gdrcd-panel-alt' ?>">
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Ricevuti
            </span>
        </a>
        <a href="main.php?page=messages_center&op=inviati"
           class="px-4 py-2 font-medium border-l border-gdrcd-border <?= $isSentMessage ? 'bg-gdrcd-accent text-white' : 'text-gdrcd-text-soft hover:bg-gdrcd-panel-alt' ?>">
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Inviati
            </span>
        </a>
    </nav>

    <?php if ($totaleresults > $PARAMETERS['settings']['messages_limit']): ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['interface']['messages']['please_erase']) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($numresults === 0): ?>
        <div class="gdrcd-alert-info">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><?= gdrcd_filter('out', $MESSAGE['interface']['messages']['no_message']) ?></div>
        </div>
    <?php else: ?>

        <form id="multiple_delete" method="post"
              action="main.php?page=messages_center<?= $base_query ?>
            <?= gdrcd_csrf_field() ?>"
              onsubmit="return gdrcd_msg_checked_delete();">
            <input type="hidden" name="op" value="erase_checked"/>
            <input type="hidden" name="type" value="<?= $delType ?>"/>

            <div class="gdrcd-card overflow-hidden">

                <!-- Toolbar bulk -->
                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5 border-b border-gdrcd-border bg-gdrcd-panel-alt/40">
                    <label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">
                        <input type="checkbox" id="msg_check_all"
                               class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"/>
                        <span>Tutti</span>
                    </label>
                    <span class="text-gdrcd-subtle">·</span>
                    <button type="submit" class="text-xs font-medium text-gdrcd-muted hover:text-gdrcd-error transition-colors inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                        Elimina selezionati
                    </button>
                </div>

                <!-- Lista -->
                <ul class="divide-y divide-gdrcd-border">
                    <?php while ($row = gdrcd_query($result, 'fetch')):
                        [$data_spedito, $ora_spedito] = explode(' ', $row['spedito']);
                        $unread = ((int)$row['letto'] === 0);
                        $counterpart = $isSentMessage ? $row['destinatario'] : $row['mittente'];
                        $is_guild = !$isSentMessage && is_numeric($row['mittente']);
                        $type_label = $MESSAGE['interface']['messages']['type']['options'][$row['tipo']] ?? '';
                        $read_url = 'main.php?page=messages_center&op=read&id_messaggio=' . (int)$row['id'];
                        $preview = mb_substr(strip_tags(nl2br(gdrcd_bbcoder($row['testo']))), 0, 120);
                        $initial = mb_strtoupper(mb_substr($is_guild ? 'G' : ($counterpart ?: '?'), 0, 1));
                        ?>
                        <li class="group relative hover:bg-gdrcd-accent-soft/30 transition-colors <?= $unread ? 'bg-gdrcd-accent-soft/10' : '' ?>">
                            <div class="flex items-start gap-3 px-4 py-3">
                                <!-- Checkbox -->
                                <label class="pt-1 cursor-pointer">
                                    <input type="checkbox" class="message_check rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"
                                           value="<?= (int)$row['id'] ?>"/>
                                </label>

                                <!-- Avatar -->
                                <div class="shrink-0">
                                    <?php if ($is_guild): ?>
                                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gdrcd-accent text-white font-display font-bold">G</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30 font-display font-bold">
                                            <?= htmlspecialchars($initial) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Contenuto cliccabile -->
                                <a href="<?= htmlspecialchars($read_url) ?>" class="flex-1 min-w-0 block">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap text-sm">
                                                <span class="<?= $unread ? 'font-semibold text-gdrcd-text' : 'text-gdrcd-text-soft' ?>">
                                                    <?php if ($is_guild): ?>
                                                        <?= gdrcd_filter('out', $MESSAGE['interface']['messages']['to_guild']) ?>
                                                    <?php else: ?>
                                                        <?= gdrcd_filter('out', $counterpart) ?>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if ($unread): ?>
                                                    <span class="inline-block w-2 h-2 rounded-full bg-gdrcd-accent" title="Non letto"></span>
                                                <?php endif; ?>
                                                <span class="gdrcd-badge-neutral text-[10px]"><?= gdrcd_filter('out', $type_label) ?></span>
                                            </div>
                                            <div class="mt-0.5 <?= $unread ? 'font-medium text-gdrcd-text' : 'text-gdrcd-text-soft' ?> truncate">
                                                <?= gdrcd_filter('out', $row['oggetto']) ?>
                                            </div>
                                            <div class="mt-0.5 text-xs text-gdrcd-muted truncate">
                                                <?= gdrcd_filter('out', $preview) ?><?= mb_strlen($preview) >= 120 ? '…' : '' ?>
                                            </div>
                                        </div>

                                        <!-- Data -->
                                        <div class="text-right shrink-0">
                                            <div class="text-xs <?= $unread ? 'font-semibold text-gdrcd-text' : 'text-gdrcd-muted' ?> whitespace-nowrap">
                                                <?= gdrcd_format_date($data_spedito) ?>
                                            </div>
                                            <div class="text-[11px] text-gdrcd-muted whitespace-nowrap">
                                                <?= gdrcd_format_time($ora_spedito) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>

                                <!-- Azioni visibili in hover -->
                                <div class="shrink-0 flex items-center gap-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                                    <button type="submit"
                                            form="reply_form_<?= (int)$row['id'] ?>"
                                            title="<?= gdrcd_filter('out', $MESSAGE['interface']['messages']['reply']) ?>"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    </button>
                                    <button type="submit"
                                            form="erase_form_<?= (int)$row['id'] ?>"
                                            title="<?= gdrcd_filter('out', $MESSAGE['interface']['messages']['erase']) ?>"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gdrcd-muted hover:bg-gdrcd-error-soft hover:text-gdrcd-error transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                    </button>
                                </div>
                            </div>

                            <?php
                            if (!$isSentMessage) {
                                if (!isset($lastMessageReceived) || $row['id'] > $lastMessageReceived) {
                                    $lastMessageReceived = $row['id'];
                                }
                            }
                            ?>
                        </li>
                    <?php endwhile;
                    gdrcd_query($result, 'free');
                    ?>
                </ul>
            </div>
        </form>

        <!-- Form di reply/erase posizionati fuori dalla form multipla per non annidarsi -->
        <?php
        // Recuperiamo nuovamente i dati per i form puntuali
        $result2 = gdrcd_query($sqlMessages . " LIMIT " . $pagebegin . ", " . $per_page, 'result');
        while ($row = gdrcd_query($result2, 'fetch')):
            $counterpart = $isSentMessage ? $row['destinatario'] : $row['mittente'];
            ?>
            <form id="reply_form_<?= (int)$row['id'] ?>" action="main.php?page=messages_center<?= $base_query ?>" method="post" class="hidden">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="reply_dest" value="<?= htmlspecialchars($counterpart) ?>"/>
                <input type="hidden" name="reply_subject" value="Re: <?= htmlspecialchars($row['oggetto']) ?>"/>
                <input type="hidden" name="reply_tipo" value="<?= (int)$row['tipo'] ?>"/>
                <input type="hidden" name="op" value="reply"/>
            </form>
            <form id="erase_form_<?= (int)$row['id'] ?>" action="main.php?page=messages_center<?= $base_query ?>" method="post" class="hidden">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="id_messaggio" value="<?= (int)$row['id'] ?>"/>
                <input type="hidden" name="type" value="<?= $delType ?>"/>
                <input type="hidden" name="op" value="erase"/>
            </form>
        <?php endwhile;
        gdrcd_query($result2, 'free');
        ?>

        <div class="flex flex-wrap justify-end">
            <form action="main.php?page=messages_center<?= $base_query ?>" method="post">
                <?= gdrcd_csrf_field() ?>
                <input type="hidden" name="op" value="eraseall"/>
                <input type="hidden" name="type" value="<?= $delType ?>"/>
                <button type="submit" class="gdrcd-btn-ghost text-xs">
                    Elimina tutti i letti
                </button>
            </form>
        </div>

    <?php endif; ?>

    <?php if ($totaleresults > $per_page): ?>
        <nav class="gdrcd-pager" aria-label="Paginazione">
            <span class="gdrcd-pager-label !border-0 !bg-transparent">
                <?= gdrcd_filter('out', $MESSAGE['interface']['pager']['pages_name']) ?>
            </span>
            <?php $pages = (int)ceil($totaleresults / $per_page) - 1;
            for ($i = 0; $i <= $pages; $i++):
                if ($i === $offset): ?>
                    <span class="is-current" aria-current="page"><?= $i + 1 ?></span>
                <?php else:
                    $url = 'main.php?' . http_build_query(array_filter([
                        'page'   => 'messages_center',
                        'op'     => $isSentMessage ? 'inviati' : null,
                        'offset' => $i,
                    ])); ?>
                    <a href="<?= htmlspecialchars($url) ?>"><?= $i + 1 ?></a>
                <?php endif;
            endfor; ?>
        </nav>
    <?php endif; ?>

</div>

<script>
    (function () {
        const checkAll = document.getElementById('msg_check_all');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.message_check').forEach(cb => cb.checked = checkAll.checked);
            });
        }
    })();

    function gdrcd_msg_checked_delete() {
        const form = document.getElementById('multiple_delete');
        const messages = document.getElementsByClassName('message_check');
        let checked = false;
        for (let i = 0; i < messages.length; i++) {
            if (messages[i].checked) {
                checked = true;
                const el = document.createElement('input');
                el.setAttribute('type', 'hidden');
                el.setAttribute('name', 'ids[]');
                el.setAttribute('value', messages[i].getAttribute('value'));
                form.appendChild(el);
            }
        }
        return checked;
    }
</script>
