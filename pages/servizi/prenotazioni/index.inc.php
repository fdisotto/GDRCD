<?php
/**
 * Servizi/Prenotazioni — booking stanze private.
 */

$alert = null;

if (gdrcd_filter('get', $_POST['action'] ?? '') === 'bookRoom') {
    $idRoom = gdrcd_filter('get', $_POST['id'] ?? 0);
    $timeRoom = max(1, (int)gdrcd_filter('num', gdrcd_filter('get', $_POST['ore'] ?? 1)));
    $checkRoom = gdrcd_query("SELECT costo, privata, scadenza, proprietario FROM mappa WHERE id = " . gdrcd_filter('num', $idRoom) . " LIMIT 1");

    $bookable = ($checkRoom['privata'] == 1 && $checkRoom['costo'] >= 0 && $checkRoom['scadenza'] <= date('Y-m-d H:i:s'));

    if ($bookable) {
        $checkPG = gdrcd_query("SELECT soldi FROM personaggio WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
        if ($checkPG['soldi'] >= ($timeRoom * $checkRoom['costo'])) {
            gdrcd_query("UPDATE mappa SET proprietario = '" . gdrcd_filter('in', $_SESSION['login']) . "', invitati='', ora_prenotazione=NOW(),
                         scadenza=DATE_ADD(NOW(), INTERVAL " . gdrcd_filter('num', $_POST['ore']) . " HOUR)
                         WHERE id = " . gdrcd_filter('num', $idRoom) . " AND scadenza < NOW() LIMIT 1");
            gdrcd_query("UPDATE personaggio SET soldi = soldi - " . gdrcd_filter('num', $timeRoom * $checkRoom['costo']) . "
                         WHERE nome = '" . gdrcd_filter('in', $_SESSION['login']) . "' LIMIT 1");
            $alert = ['success', gdrcd_filter('out', $MESSAGE['interface']['hotel']['ok'])];
        } else {
            $alert = ['error', gdrcd_filter('out', $MESSAGE['interface']['hotel']['no_bucks'])];
        }
    } else {
        if ($checkRoom['proprietario'] == $_SESSION['login']) {
            $alert = ['error', gdrcd_filter('out', $MESSAGE['interface']['hotel']['already_booked_by_user'])];
        } else {
            $alert = ['warning', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
        }
    }
}

$result = gdrcd_query("SELECT mappa.id, mappa.nome AS luogo, mappa.costo, mappa.proprietario, mappa.scadenza, mappa_click.nome
                        FROM mappa JOIN mappa_click ON mappa.id_mappa = mappa_click.id_click
                        WHERE mappa.privata = 1 ORDER BY mappa.nome, mappa.costo DESC", 'result');

$rooms = [];
while ($r = gdrcd_query($result, 'fetch')) {
    $r['booked'] = strtotime($r['scadenza']) > time();
    $rooms[] = $r;
}
gdrcd_query($result, 'free');

$enabled = ($PARAMETERS['mode']['privaterooms'] ?? 'OFF') === 'ON' && !empty($rooms);
?>

<?php if ($alert): ?>
    <div class="gdrcd-alert-<?= $alert[0] ?>">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <div><?= $alert[1] ?></div>
    </div>
<?php endif; ?>

<?php if (!$enabled): ?>
    <div class="gdrcd-alert-warning">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['interface']['hotel']['no_room']) ?></div>
    </div>
<?php else: ?>
    <article class="gdrcd-card">
        <header class="gdrcd-card-header">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['hotel']['form']['bookRoom']['title']) ?></h3>
        </header>
        <div class="gdrcd-card-body">
            <form method="POST" action="main.php?page=servizi_prenotazioni" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="block md:col-span-2">
                    <span class="text-sm text-gdrcd-text-soft"><?= $MESSAGE['interface']['hotel']['room'] ?></span>
                    <select name="id" class="gdrcd-select mt-1 w-full">
                        <?php foreach ($rooms as $r):
                            if ($r['booked']):
                        ?>
                            <option value="<?= (int)$r['id'] ?>" disabled>
                                <?= gdrcd_filter('out', $r['luogo'] . ', ' . $r['nome']) ?>
                                (<?= htmlspecialchars($r['proprietario']) ?>, <?= gdrcd_format_time($r['scadenza']) ?>)
                            </option>
                        <?php else: ?>
                            <option value="<?= (int)$r['id'] ?>"<?= (gdrcd_filter('get', $_REQUEST['id'] ?? '') == $r['id']) ? ' selected' : '' ?>>
                                <?= gdrcd_filter('out', $r['luogo'] . ', ' . $r['nome']) ?>
                                (<?= (int)$r['costo'] ?> <?= strtolower($PARAMETERS['names']['currency']['plur']) ?>/<?= gdrcd_filter('out', $MESSAGE['interface']['hotel']['per_hour']) ?>)
                            </option>
                        <?php endif; endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-gdrcd-text-soft"><?= $MESSAGE['interface']['hotel']['hours'] ?></span>
                    <select name="ore" class="gdrcd-select mt-1 w-full">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?> <?= gdrcd_filter('out', $MESSAGE['interface']['hotel']['hours']) ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <div class="md:col-span-3 flex justify-end">
                    <input type="hidden" name="action" value="bookRoom">
                    <button type="submit" class="gdrcd-btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                    </button>
                </div>
            </form>
        </div>
    </article>
<?php endif; ?>
