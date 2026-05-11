<?php
/**
 * Handler: cancellazione di un topic o di un singolo post.
 */

$postID = (int)($_POST['id_record'] ?? 0);
$postData = gdrcd_query(
    "SELECT id_messaggio_padre AS padre, autore FROM messaggioaraldo WHERE id_messaggio = " . $postID
);

$is_owner_or_mod = ($_SESSION['permessi'] >= MODERATOR
                  || ($postData['autore'] ?? '') === $_SESSION['login']);
$query = null;
$back  = 'forum';

if ((int)($postData['padre'] ?? 0) === -1 && $is_owner_or_mod) {
    // Topic intero
    gdrcd_query("DELETE FROM araldo_letto WHERE thread_id = " . $postID);
    $query = "DELETE FROM messaggioaraldo WHERE id_messaggio_padre = " . $postID . " OR id_messaggio = " . $postID;
} elseif ((int)($postData['padre'] ?? 0) !== -1 && $is_owner_or_mod) {
    // Singolo post
    $query = "DELETE FROM messaggioaraldo WHERE id_messaggio = " . $postID;
    $back  = 'forum&op=read&what=' . (int)$postData['padre'];
}

if ($query) {
    gdrcd_query($query);
    ?>
    <div class="gdrcd-alert-success">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['warning']['deleted']) ?></div>
    </div>
    <div>
        <a href="main.php?page=<?= htmlspecialchars($back) ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['forums']['link']['topic']) ?>
        </a>
    </div>
<?php } else { ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['not_allowed']) ?></div>
    </div>
<?php }
