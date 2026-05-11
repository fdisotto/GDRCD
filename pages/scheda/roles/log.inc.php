<?php
/**
 * Scheda PG — log chat di una giocata registrata.
 */

if ($_SESSION['permessi'] >= LOG_PERM) {
    $pg = $_REQUEST['pg'];
} else {
    $pg = $_SESSION['login'];
}
$pg_url = gdrcd_filter('url', $_REQUEST['pg']);
$typeOrder = ($PARAMETERS['mode']['chat_from_bottom'] == 'ON') ? 'DESC' : 'ASC';

$render_back = function () use ($pg_url, $MESSAGE) { ?>
    <div>
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['link']['back_roles']) ?>
        </a>
    </div>
<?php };

$check = gdrcd_query(
    "SELECT * FROM segnalazione_role
     WHERE id = " . gdrcd_filter('num', $_POST['id'] ?? 0) . "
       AND mittente = '" . gdrcd_filter('in', $pg) . "'
       AND conclusa = 1",
    'result'
);
$check_f = gdrcd_query($check, 'fetch');

if (!$check_f) {
    echo '<div class="gdrcd-alert-error">Non hai accesso a questo log chat.</div>';
    $render_back();
    return;
}

$name = gdrcd_query("SELECT nome FROM mappa WHERE id = " . (int)$check_f['stanza'], 'result');
$r_nam = gdrcd_query($name, 'fetch');

$query = gdrcd_query(
    "SELECT chat.id, chat.imgs, chat.mittente, chat.destinatario, chat.tipo, chat.ora,
            chat.testo, personaggio.url_img_chat
     FROM chat
     INNER JOIN mappa ON mappa.id = chat.stanza
     LEFT JOIN personaggio ON personaggio.nome = chat.mittente
     WHERE stanza = " . (int)$check_f['stanza'] . "
       AND ora >= '" . gdrcd_filter('in', $check_f['data_inizio']) . "'
       AND ora <= '" . gdrcd_filter('in', $check_f['data_fine']) . "'
     ORDER BY ora " . $typeOrder,
    'result'
);
$num = gdrcd_query($query, 'num_rows');
?>

<article class="gdrcd-card overflow-hidden">
    <header class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gdrcd-border bg-gdrcd-panel-alt/30">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </span>
            <div>
                <h3 class="font-display text-lg text-gdrcd-accent"><?= htmlspecialchars($r_nam['nome'] ?? '') ?></h3>
                <p class="text-xs text-gdrcd-text-soft tabular-nums">
                    <?= gdrcd_filter('out', gdrcd_format_date($check_f['data_inizio'])) ?>
                    · <?= gdrcd_format_time($check_f['data_inizio']) ?> – <?= gdrcd_format_time($check_f['data_fine']) ?>
                </p>
            </div>
        </div>
        <span class="gdrcd-badge-accent tabular-nums whitespace-nowrap"><?= $num ?> messagg<?= $num === 1 ? 'io' : 'i' ?></span>
    </header>

    <?php if ($num == 0): ?>
        <div class="text-center text-gdrcd-text-soft py-8">
            <svg class="w-12 h-12 mx-auto text-gdrcd-subtle/50 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <div>Nessun messaggio disponibile in questo intervallo.</div>
    <?php else: ?>
        <div id="pagina_chat" class="log_roles space-y-1 p-4">
        <?php
        $add_chat = '';
        while ($row = gdrcd_query($query, 'fetch')) {
            switch ($row['tipo']) {
                case 'P':
                case 'A':
                    $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';
                    if ($PARAMETERS['mode']['chat_avatar'] == 'ON' && !empty($row['url_img_chat'])) {
                        $add_chat .= '<img src="' . htmlspecialchars($row['url_img_chat']) . '" class="chat_avatar" alt="">';
                    }
                    $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
                    $add_chat .= '<span class="chat_name">' . htmlspecialchars($row['mittente']);
                    if (!empty($row['destinatario'])) {
                        $add_chat .= '<span class="chat_tag"> [' . gdrcd_filter('out', $row['destinatario']) . ']</span>';
                    }
                    $add_chat .= ($row['tipo'] === 'P' ? ': </span> ' : '</span> ');
                    $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span>';
                    $add_chat .= '</div>';
                    break;

                case 'S':
                    if ($_SESSION['login'] == $row['destinatario']) {
                        $add_chat .= '<div class="chat_row_S">';
                        $add_chat .= '<span class="chat_name">' . htmlspecialchars($row['mittente']) . ' ' . $MESSAGE['chat']['whisper']['by'] . ': </span>';
                        $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span></div>';
                    } elseif ($_SESSION['login'] == $row['mittente']) {
                        $add_chat .= '<div class="chat_row_S">';
                        $add_chat .= '<span class="chat_msg">' . $MESSAGE['chat']['whisper']['to'] . ' ' . gdrcd_filter('out', $row['destinatario']) . ': </span>';
                        $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span></div>';
                    } elseif ($_SESSION['permessi'] >= MODERATOR && $PARAMETERS['mode']['spyprivaterooms'] == 'ON') {
                        $add_chat .= '<div class="chat_row_S">';
                        $add_chat .= '<span class="chat_msg">' . htmlspecialchars($row['mittente']) . ' ' . $MESSAGE['chat']['whisper']['from_to'] . ' ' . gdrcd_filter('out', $row['destinatario']) . '</span>';
                        $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span></div>';
                    }
                    break;

                case 'N':
                    $add_chat .= '<div class="chat_row_N">';
                    $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
                    $add_chat .= '<span class="chat_name">' . htmlspecialchars($row['destinatario']) . '</span> ';
                    $add_chat .= '<span class="chat_msg">' . gdrcd_chatcolor(gdrcd_filter('out', $row['testo'])) . '</span></div>';
                    break;

                case 'M':
                    $add_chat .= '<div class="chat_row_M"><span class="chat_master">' . gdrcd_filter('out', $row['testo']) . '</span></div>';
                    break;

                case 'I':
                    $add_chat .= '<div class="chat_row_I"><img class="chat_img" src="' . gdrcd_filter('out', $row['testo']) . '" alt=""></div>';
                    break;

                case 'C':
                case 'D':
                case 'O':
                    $add_chat .= '<div class="chat_row_' . $row['tipo'] . '">';
                    $add_chat .= '<span class="chat_time">' . gdrcd_format_time($row['ora']) . '</span>';
                    $add_chat .= '<span class="chat_msg">' . gdrcd_filter('out', $row['testo']) . '</span></div>';
                    break;
            }
        }
        echo $add_chat;
        ?>
        </div>
    <?php endif; ?>
</article>

<?php $render_back(); ?>
