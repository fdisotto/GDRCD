<?php
/**
 * Scheda PG — vista stampabile / export PDF.
 *   popup.php?page=scheda_print&pg=<nome>
 *
 * Single-page layout pronto per `window.print()` (Stampa / Salva come PDF
 * dal dialog browser). Aggrega le sezioni piu' rilevanti della scheda:
 *
 *   - Anagrafica (nome, cognome, razza, sesso, eta')
 *   - Caratteristiche (somma base + bonus razza + bonus oggetti)
 *   - Descrizione / Storia / Affetti
 *   - Abilita' apprese (con grado)
 *   - Oggetti equipaggiati
 *   - Quest attive
 *
 * Niente dipendenze esterne (no dompdf/wkhtmltopdf): il browser fa
 * print-to-PDF.
 *
 * Pubblica: ogni utente loggato puo' aprire la versione stampabile della
 * scheda di un altro PG (parita' con scheda.inc.php).
 */

if (!isset($_REQUEST['pg']) || $_REQUEST['pg'] === '') {
    if (!empty($_SESSION['login'])) {
        $_REQUEST['pg'] = $_SESSION['login'];
    } else {
        echo '<div class="gdrcd-alert-error">PG non specificato.</div>';
        return;
    }
}

$pg_in  = gdrcd_filter('in', $_REQUEST['pg']);
$pg_out = gdrcd_filter('out', $_REQUEST['pg']);

$personaggio = Db::preparedFetch(
    "SELECT p.*, razza.sing_m, razza.sing_f,
            razza.bonus_car0, razza.bonus_car1, razza.bonus_car2,
            razza.bonus_car3, razza.bonus_car4, razza.bonus_car5
     FROM personaggio p
     LEFT JOIN razza ON p.id_razza = razza.id_razza
     WHERE p.nome = ?
     LIMIT 1",
    's',
    [$pg_in]
);

if (empty($personaggio)) {
    echo '<div class="gdrcd-alert-error">Personaggio non trovato.</div>';
    return;
}

$razza_label = (($personaggio['sesso'] ?? 'm') === 'f')
    ? ($personaggio['sing_f'] ?? '')
    : ($personaggio['sing_m'] ?? '');

// Bonus oggetti equipaggiati
$bonus_oggetti = Db::preparedFetch(
    "SELECT SUM(oggetto.bonus_car0) AS BO0, SUM(oggetto.bonus_car1) AS BO1,
            SUM(oggetto.bonus_car2) AS BO2, SUM(oggetto.bonus_car3) AS BO3,
            SUM(oggetto.bonus_car4) AS BO4, SUM(oggetto.bonus_car5) AS BO5
     FROM oggetto
     JOIN clgpersonaggiooggetto ON oggetto.id_oggetto = clgpersonaggiooggetto.id_oggetto
     WHERE clgpersonaggiooggetto.nome = ?
       AND clgpersonaggiooggetto.posizione > " . ZAINO,
    's',
    [$pg_in]
) ?: [];

$abilita = Db::preparedFetchAll(
    "SELECT a.nome, cpa.grado
     FROM clgpersonaggioabilita cpa
     INNER JOIN abilita a ON a.id_abilita = cpa.id_abilita
     WHERE cpa.nome = ?
     ORDER BY a.nome ASC",
    's',
    [$pg_in]
);

$equip = Db::preparedFetchAll(
    "SELECT o.nome, o.descrizione, cpo.posizione
     FROM clgpersonaggiooggetto cpo
     INNER JOIN oggetto o ON o.id_oggetto = cpo.id_oggetto
     WHERE cpo.nome = ?
       AND cpo.posizione > " . ZAINO . "
     ORDER BY cpo.posizione ASC",
    's',
    [$pg_in]
);

$quest_attive = Db::preparedFetchAll(
    "SELECT q.titolo, q.descrizione, q.obiettivo, cqp.assegnata_il
     FROM clgquestpg cqp
     INNER JOIN quest q ON q.id_quest = cqp.id_quest
     WHERE cqp.personaggio = ?
       AND cqp.status = 'attiva'
     ORDER BY cqp.assegnata_il DESC",
    's',
    [$pg_in]
);

$car_labels = ['Forza', 'Costituzione', 'Destrezza', 'Intelligenza', 'Volonta\'', 'Carisma'];

$car_total = function (int $i) use ($personaggio, $bonus_oggetti): int {
    $base  = (int)($personaggio['car' . $i] ?? 0);
    $rbon  = (int)($personaggio['bonus_car' . $i] ?? 0);
    $obon  = (int)($bonus_oggetti['BO' . $i] ?? 0);
    return $base + $rbon + $obon;
};

$render_text = function (?string $txt): string {
    if ($txt === null || trim($txt) === '') return '<em>—</em>';
    return gdrcd_bbcoder(gdrcd_filter('out', $txt));
};
?>

<style>
@media print {
    body { background: #fff !important; color: #000 !important; }
    .no-print { display: none !important; }
    .gdrcd-print-page { max-width: none !important; box-shadow: none !important; }
    a { color: #000 !important; text-decoration: none !important; }
    @page { margin: 1.5cm; size: A4; }
}
.gdrcd-print-page h1 { font-size: 1.6rem; }
.gdrcd-print-page h2 { font-size: 1.1rem; margin-top: 1.2rem; padding-bottom: .2rem; border-bottom: 1px solid #aaa; }
.gdrcd-print-page table.stat { width: 100%; border-collapse: collapse; }
.gdrcd-print-page table.stat td { padding: .15rem .4rem; border-bottom: 1px solid #ddd; }
.gdrcd-print-page table.stat td.lbl { font-weight: 600; width: 40%; }
.gdrcd-print-page table.stat td.val { text-align: right; font-variant-numeric: tabular-nums; }
</style>

<div class="gdrcd-print-page bg-white text-black p-6 rounded shadow-md mx-auto" style="max-width: 850px;">

    <div class="no-print flex justify-end gap-2 mb-4">
        <button type="button" class="gdrcd-btn-primary"
                onclick="window.print();">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Stampa / Salva PDF
        </button>
        <a class="gdrcd-btn-ghost"
           href="main.php?page=scheda&pg=<?= urlencode($_REQUEST['pg']) ?>">
            Torna alla scheda
        </a>
    </div>

    <header>
        <h1 class="font-bold">
            <?= $pg_out ?>
            <?php if (!empty($personaggio['cognome'])): ?>
                <span class="font-normal"><?= gdrcd_filter('out', (string)$personaggio['cognome']) ?></span>
            <?php endif; ?>
        </h1>
        <div class="text-sm opacity-80">
            <?= gdrcd_filter('out', (string)$razza_label) ?>
            <?php if (!empty($personaggio['eta'])): ?>
                · <?= (int)$personaggio['eta'] ?> anni
            <?php endif; ?>
            <?php if (!empty($personaggio['sesso'])): ?>
                · <?= ($personaggio['sesso'] === 'f') ? 'Femmina' : 'Maschio' ?>
            <?php endif; ?>
        </div>
    </header>

    <h2>Caratteristiche</h2>
    <table class="stat">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <tr>
                <td class="lbl"><?= $car_labels[$i] ?></td>
                <td class="val tabular-nums">
                    <?= (int)$personaggio['car' . $i] ?>
                    <?php
                    $rbon = (int)($personaggio['bonus_car' . $i] ?? 0);
                    $obon = (int)($bonus_oggetti['BO' . $i] ?? 0);
                    if ($rbon !== 0 || $obon !== 0):
                    ?>
                        <span class="opacity-60 text-xs">
                            <?= ($rbon >= 0 ? '+' . $rbon : $rbon) ?> razza
                            <?= ($obon >= 0 ? '+' . $obon : $obon) ?> ogg.
                        </span>
                        = <strong><?= $car_total($i) ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endfor; ?>
    </table>

    <?php if (!empty($personaggio['descrizione'])): ?>
        <h2>Aspetto</h2>
        <div class="text-sm"><?= $render_text($personaggio['descrizione']) ?></div>
    <?php endif; ?>

    <?php if (!empty($personaggio['storia'])): ?>
        <h2>Storia</h2>
        <div class="text-sm"><?= $render_text($personaggio['storia']) ?></div>
    <?php endif; ?>

    <?php if (!empty($personaggio['affetti'])): ?>
        <h2>Affetti</h2>
        <div class="text-sm"><?= $render_text($personaggio['affetti']) ?></div>
    <?php endif; ?>

    <?php if (!empty($abilita)): ?>
        <h2>Abilita'</h2>
        <ul class="text-sm columns-2 gap-6 list-disc list-inside">
            <?php foreach ($abilita as $a): ?>
                <li>
                    <?= gdrcd_filter('out', (string)$a['nome']) ?>
                    <span class="opacity-60">grado <?= (int)$a['grado'] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($equip)): ?>
        <h2>Equipaggiamento</h2>
        <ul class="text-sm list-disc list-inside">
            <?php foreach ($equip as $o): ?>
                <li><?= gdrcd_filter('out', (string)$o['nome']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($quest_attive)): ?>
        <h2>Quest attive</h2>
        <?php foreach ($quest_attive as $q): ?>
            <div class="text-sm mb-3">
                <strong><?= gdrcd_filter('out', (string)$q['titolo']) ?></strong>
                <span class="opacity-60 text-xs">
                    (assegnata <?= htmlspecialchars(date('d/m/Y', strtotime((string)$q['assegnata_il']))) ?>)
                </span>
                <div><?= $render_text($q['descrizione']) ?></div>
                <?php if (!empty($q['obiettivo'])): ?>
                    <div class="mt-1"><em>Obiettivo:</em> <?= $render_text($q['obiettivo']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="mt-8 pt-3 border-t border-gray-300 text-xs opacity-60 text-center">
        Esportato il <?= date('d/m/Y H:i') ?>
        <?php if (!empty($PARAMETERS['info']['site_name'])): ?>
            · <?= htmlspecialchars($PARAMETERS['info']['site_name']) ?>
        <?php endif; ?>
    </footer>
</div>

<script>
(function () {
    // Apri direttamente il dialog di stampa se la pagina e' aperta con
    // ?autoprint=1 (utile per workflow batch).
    var sp = new URLSearchParams(window.location.search);
    if (sp.get('autoprint') === '1') {
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    }
})();
</script>
