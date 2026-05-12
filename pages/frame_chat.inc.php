<?php
/**
 * Frame chat: visualizzazione di una singola stanza con form messaggi + skill/dice/item.
 * Tipi messaggio: (A azione, P parlato, N PNG, M Master, I Immagine, S sussurro, D dado, C skill check, O uso oggetto)
 */

$info = gdrcd_query(
    "SELECT nome, stanza_apparente, invitati, privata, proprietario, scadenza
     FROM mappa WHERE id = " . (int)$_SESSION['luogo'] . " LIMIT 1"
);

/* Controllo accesso stanza privata */
$allowance = true;
if (!empty($info['privata']) && (int)$info['privata'] === 1) {
    $allowance  = false;
    $invitati   = explode(',', $info['invitati'] ?? '');
    $me_cap     = gdrcd_capital_letter($_SESSION['login']);
    $owner_ok   = ($info['proprietario'] === $me_cap)
               || strpos((string)($_SESSION['gilda'] ?? ''), (string)$info['proprietario']) !== false
               || in_array($me_cap, $invitati, true);
    $spy_master = (($PARAMETERS['mode']['spyprivaterooms'] ?? 'OFF') === 'ON')
               && ((int)$_SESSION['permessi'] > MODERATOR);
    $not_expired = (($info['scadenza'] ?? '0') > date('Y-m-d H:i:s'));
    if (($owner_ok || $spy_master) && $not_expired) {
        $allowance = true;
    }
}

$is_priv_owner = ((int)$info['privata'] === 1)
              && (($info['proprietario'] === $_SESSION['login'])
                  || (is_numeric($info['proprietario']) && strpos((string)$_SESSION['gilda'], (string)$info['proprietario']) !== false));
$is_gm = ((int)$_SESSION['permessi'] >= GAMEMASTER);
?>

<div class="space-y-4">

    <header class="space-y-1">
        <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $info['nome']) ?></h2>
        <?php if (!empty($info['stanza_apparente'])): ?>
            <p class="gdrcd-muted text-sm"><?= gdrcd_filter('out', $info['stanza_apparente']) ?></p>
        <?php endif; ?>
    </header>

    <?= AudioController::build('chat') ?>

    <?php if (!$allowance): ?>
        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <div><?= $MESSAGE['chat']['whisper']['privat'] ?></div>
        </div>
    <?php else:
        $_SESSION['last_message'] = 0;
        ?>

        <!-- iframe nascosto: usato solo come target POST per i form chat;
             il polling messaggi avviene via fetch in includes/chat.js -->
        <div class="absolute -left-[9999px] w-px h-px overflow-hidden" aria-hidden="true">
            <iframe src="about:blank" id="chat_frame" name="chat_frame" title="Target invio form chat" frameborder="0"></iframe>
        </div>

        <!-- Chat output (popolato da includes/chat.js via WebSocket o fetch /api/chat.inc.php). -->
        <?php
            // WebSocket: se abilitato in config, calcolo URL ws(s):// da
            // passare al client. Vuoto = WS disabilitato, client va in
            // polling-only.
            $ws_enabled = !empty($PARAMETERS['websocket']['enabled']);
            $ws_url     = '';
            if ($ws_enabled) {
                $configured = isset($PARAMETERS['websocket']['url'])
                    ? (string)$PARAMETERS['websocket']['url']
                    : '';
                if ($configured !== '') {
                    $ws_url = $configured;
                } else {
                    // Fallback: stesso host della pagina + porta 8082, scheme
                    // wss:// se siamo su https, altrimenti ws://.
                    $scheme = (
                        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                    ) ? 'wss' : 'ws';
                    $host   = preg_replace('/:.*/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
                    $ws_url = $scheme . '://' . $host . ':8082';
                }
            }
        ?>
        <section class="gdrcd-card">
            <div id="pagina_chat"
                 class="chat_box p-2 sm:p-4 h-[65vh] overflow-y-auto overflow-x-hidden"
                 data-poll-url="/api/chat.inc.php"
                 data-poll-interval="4000"
                 data-ws-url="<?= htmlspecialchars($ws_url, ENT_QUOTES) ?>"
                 data-ws-room="<?= (int)$_SESSION['luogo'] ?>"
                 data-from-bottom="<?= (($PARAMETERS['mode']['chat_from_bottom'] ?? 'OFF') === 'ON') ? '1' : '0' ?>"
                 data-login="<?= htmlspecialchars($_SESSION['login'] ?? '', ENT_QUOTES) ?>"
                 data-permessi="<?= (int)($_SESSION['permessi'] ?? 0) ?>"
                 data-spy-private="<?= (($PARAMETERS['mode']['spyprivaterooms'] ?? 'OFF') === 'ON') ? '1' : '0' ?>"
                 data-chat-avatar="<?= (($PARAMETERS['mode']['chat_avatar'] ?? 'OFF') === 'ON') ? '1' : '0' ?>"
                 data-chat-icons="<?= (($PARAMETERS['mode']['chaticons'] ?? 'OFF') === 'ON') ? '1' : '0' ?>"
                 data-avatar-link="<?= (($PARAMETERS['settings']['chat_avatar']['link']['mode'] ?? 'OFF') === 'ON') ? '1' : '0' ?>"
                 data-avatar-popup="<?= (($PARAMETERS['settings']['chat_avatar']['link']['popup'] ?? 'OFF') === 'ON') ? '1' : '0' ?>"
                 data-theme="<?= htmlspecialchars($PARAMETERS['themes']['current_theme'] ?? '', ENT_QUOTES) ?>"
                 data-msg-whisper-by="<?= htmlspecialchars($MESSAGE['chat']['whisper']['by'] ?? 'ti sussurra', ENT_QUOTES) ?>"
                 data-msg-whisper-to="<?= htmlspecialchars($MESSAGE['chat']['whisper']['to'] ?? 'Sussurri a', ENT_QUOTES) ?>"
                 data-msg-whisper-from-to="<?= htmlspecialchars($MESSAGE['chat']['whisper']['from_to'] ?? 'sussurra a', ENT_QUOTES) ?>"></div>
        </section>

        <!-- Form invio messaggio -->
        <section class="gdrcd-card">
            <div class="gdrcd-card-body space-y-4">
                <form action="pages/chat.inc.php?ref=10&chat=yes" method="post" target="chat_frame" id="chat_form_messages" class="space-y-3">
                    <?= gdrcd_csrf_field() ?>
                    <div class="grid grid-cols-2 md:grid-cols-[10rem_12rem_minmax(0,1fr)_auto] gap-2 sm:gap-3 items-end">
                        <div class="min-w-0">
                            <label class="gdrcd-label" for="type"><?= gdrcd_filter('out', $MESSAGE['chat']['type']['info']) ?></label>
                            <select class="gdrcd-select" name="type" id="type">
                                <option value="0"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][0]) ?></option>
                                <option value="1"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][1]) ?></option>
                                <option value="4"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][4]) ?></option>
                                <?php if ($is_gm): ?>
                                    <option value="2"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][2]) ?></option>
                                    <option value="3"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][3]) ?></option>
                                <?php endif; ?>
                                <?php if ($is_priv_owner): ?>
                                    <option value="5"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][5]) ?></option>
                                    <option value="6"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][6]) ?></option>
                                    <option value="7"><?= gdrcd_filter('out', $MESSAGE['chat']['type'][7]) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label class="gdrcd-label" for="tag">Tag</label>
                            <input class="gdrcd-input" type="text" name="tag" id="tag" placeholder="dst / png"/>
                        </div>
                        <div class="col-span-2 md:col-span-1 min-w-0">
                            <label class="gdrcd-label" for="message">Messaggio</label>
                            <textarea class="gdrcd-textarea resize-none leading-snug" name="message" id="message"
                                      rows="1" autocomplete="off"
                                      data-autoresize-max="3"
                                      style="overflow-y:auto;"></textarea>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <input type="hidden" name="op" value="new_chat_message"/>
                            <button type="submit" class="gdrcd-btn-primary w-full">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                Invia
                            </button>
                        </div>
                    </div>

                    <p class="gdrcd-help">
                        <?= gdrcd_filter('out', $MESSAGE['chat']['tag']['info']['tag'] . ' ' . $MESSAGE['chat']['tag']['info']['dst']) ?>
                        <?php if ($is_gm): ?>
                            <span class="text-gdrcd-subtle">·</span>
                            <?= gdrcd_filter('out', $MESSAGE['chat']['tag']['info']['png']) ?>
                        <?php endif; ?>
                        <span class="text-gdrcd-subtle">·</span>
                        <?= gdrcd_filter('out', $MESSAGE['chat']['tag']['info']['msg']) ?>
                    </p>

                    <div class="flex flex-wrap gap-3 text-xs">
                        <?php if (($PARAMETERS['mode']['chatsave'] ?? 'OFF') === 'ON'): ?>
                            <a class="gdrcd-link-quiet inline-flex items-center gap-1"
                               href="javascript:void(0);"
                               onclick="window.open('chat_save.proc.php','Log','width=1,height=1,toolbar=no');">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                                Salva chat
                            </a>
                        <?php endif; ?>
                        <?php if (REG_ROLE): ?>
                            <a class="gdrcd-link-quiet inline-flex items-center gap-1"
                               href="javascript:parent.modalWindow('rolesreg', '', 'popup.php?page=chat_pannelli_index&pannello=segnalazione_role');">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                                Registra giocata
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <script>
        (function () {
            var ta = document.getElementById('message');
            if (!ta) return;
            var maxRows = parseInt(ta.getAttribute('data-autoresize-max'), 10) || 3;
            var cs = window.getComputedStyle(ta);
            var lh = parseFloat(cs.lineHeight) || 20;
            var padT = parseFloat(cs.paddingTop) || 0;
            var padB = parseFloat(cs.paddingBottom) || 0;
            var maxH = (lh * maxRows) + padT + padB;
            function resize() {
                ta.style.height = 'auto';
                var h = Math.min(ta.scrollHeight, maxH);
                ta.style.height = h + 'px';
                ta.style.overflowY = (ta.scrollHeight > maxH) ? 'auto' : 'hidden';
            }
            ta.addEventListener('input', resize);
            ta.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    var form = document.getElementById('chat_form_messages');
                    if (form && typeof form.requestSubmit === 'function') form.requestSubmit();
                    else if (form) form.submit();
                }
            });
            var form = document.getElementById('chat_form_messages');
            if (form) form.addEventListener('reset', function () { setTimeout(resize, 0); });
            resize();
        })();
        </script>

        <!-- Form azioni: skill, dadi, oggetti -->
        <?php $skills_on = (($PARAMETERS['mode']['skillsystem'] ?? 'OFF') === 'ON');
              $dice_on  = (($PARAMETERS['mode']['dices']       ?? 'OFF') === 'ON');
        if ($skills_on || $dice_on): ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h3 class="gdrcd-h3">Azioni</h3>
                </div>
                <div class="gdrcd-card-body">
                    <form action="pages/chat.inc.php?ref=30&chat=yes" method="post" target="chat_frame" id="chat_form_actions"
                          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                        <?= gdrcd_csrf_field() ?>

                        <?php if ($skills_on): ?>
                            <div>
                                <label class="gdrcd-label" for="id_ab"><?= gdrcd_filter('out', $MESSAGE['chat']['commands']['skills']) ?></label>
                                <select class="gdrcd-select" name="id_ab" id="id_ab">
                                    <option value="no_skill">—</option>
                                    <?php $skills = gdrcd_query(
                                        "SELECT id_abilita, nome FROM abilita
                                         WHERE id_razza = -1 OR id_razza IN (SELECT id_razza FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "')
                                         ORDER BY nome", 'result');
                                    while ($s = gdrcd_query($skills, 'fetch')): ?>
                                        <option value="<?= (int)$s['id_abilita'] ?>"><?= gdrcd_filter('out', $s['nome']) ?></option>
                                    <?php endwhile;
                                    gdrcd_query($skills, 'free');
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label class="gdrcd-label" for="id_stats"><?= gdrcd_filter('out', $MESSAGE['chat']['commands']['stats']) ?></label>
                                <select class="gdrcd-select" name="id_stats" id="id_stats">
                                    <option value="no_stats">—</option>
                                    <?php foreach ($PARAMETERS['names']['stats'] as $id_stats => $name_stats):
                                        if (is_numeric(substr($id_stats, 3))): ?>
                                            <option value="stats_<?= substr($id_stats, 3) ?>"><?= gdrcd_filter('out', $name_stats) ?></option>
                                        <?php endif;
                                    endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="id_ab" id="id_ab" value="no_skill"/>
                        <?php endif; ?>

                        <?php if ($dice_on): ?>
                            <div>
                                <label class="gdrcd-label" for="dice"><?= gdrcd_filter('out', $MESSAGE['chat']['commands']['dice']) ?></label>
                                <select class="gdrcd-select" name="dice" id="dice">
                                    <option value="no_dice">—</option>
                                    <?php foreach ($PARAMETERS['settings']['skills_dices'] as $dice_name => $dice_value): ?>
                                        <option value="<?= htmlspecialchars($dice_value) ?>"><?= htmlspecialchars($dice_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="dice" id="dice" value="no_dice"/>
                        <?php endif; ?>

                        <?php if ($skills_on): ?>
                            <div>
                                <label class="gdrcd-label" for="id_item"><?= gdrcd_filter('out', $MESSAGE['chat']['commands']['item']) ?></label>
                                <select class="gdrcd-select" name="id_item" id="id_item">
                                    <option value="no_item">—</option>
                                    <?php $items = gdrcd_query(
                                        "SELECT clgpersonaggiooggetto.id_oggetto, oggetto.nome, clgpersonaggiooggetto.cariche
                                         FROM clgpersonaggiooggetto JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
                                         WHERE clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' AND posizione > 0
                                         ORDER BY oggetto.nome", 'result');
                                    while ($it = gdrcd_query($items, 'fetch')): ?>
                                        <option value="<?= (int)$it['id_oggetto'] ?>"><?= gdrcd_filter('out', $it['nome']) ?></option>
                                    <?php endwhile;
                                    gdrcd_query($items, 'free');
                                    ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="id_item" id="id_item" value="no_item"/>
                        <?php endif; ?>

                        <div class="md:col-span-2 lg:col-span-4 flex justify-end pt-2 border-t border-gdrcd-border">
                            <input type="hidden" name="op" value="take_action"/>
                            <button type="submit" class="gdrcd-btn-secondary">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Esegui azione
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>
