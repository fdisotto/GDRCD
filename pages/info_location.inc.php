<?php
/**
 * Widget sidebar — info luogo: immagine, descrizione, meteo, fase lunare.
 */

if (!function_exists('gdrcd_lunar_phase')) {
    function gdrcd_lunar_phase()
    {
        $year  = (int)date('Y');
        $month = (int)date('n');
        $days  = (int)date('j');
        if ($month < 4) { $year--; $month += 12; }
        $plenilunio = (365.25 * $year + 30.42 * $month + $days - 694039.09) / 29.53;
        $phase = (int)$plenilunio;
        $phase = round(($plenilunio - $phase) * 8 + 0.5);
        if ($phase == 8) $phase = 0;
        $names = ['nuova', 'crescente', 'primo-quarto', 'gibbosa-crescente', 'piena', 'gibbosa-calante', 'ultimo-quarto', 'calante'];
        $titles = ['Nuova', 'Crescente', 'Primo Quarto', 'Gibbosa crescente', 'Piena', 'Gibbosa calante', 'Ultimo quarto', 'Calante'];
        return ['phase' => $names[$phase], 'title' => $titles[$phase]];
    }
}

$result = gdrcd_query(
    "SELECT mappa.nome, mappa.descrizione, mappa.stato, mappa.immagine, mappa.stanza_apparente, mappa.scadenza, mappa_click.meteo
     FROM mappa_click LEFT JOIN mappa ON mappa_click.id_click = mappa.id_mappa
     WHERE id = " . (int)($_SESSION['luogo'] ?? 0),
    'result'
);
$record_exists = gdrcd_query($result, 'num_rows');
$record = gdrcd_query($result, 'fetch');
gdrcd_query($result, 'free');

if (empty($record['nome'])) {
    $nome_mappa = gdrcd_query("SELECT nome FROM mappa_click WHERE id_click = " . (int)$_SESSION['mappa']);
    $nome_luogo = $nome_mappa['nome'] ?? '';
} else {
    $nome_luogo = $record['nome'];
}

$theme = gdrcd_filter('out', $PARAMETERS['themes']['current_theme']);
$immagine_luogo = !empty($record['immagine']) ? $record['immagine'] : 'standard_luogo.png';

$meteo = '';
if ($record_exists > 0 || ($_SESSION['luogo'] ?? 0) == -1) {
    if (($PARAMETERS['mode']['auto_meteo'] ?? 'OFF') === 'ON') {
        $ore = (int)date('H');
        $mese = (int)date('m');
        $giorno = (int)date('z') + 1;
        $caso = ((floor($giorno / 3)) % 2) + 1;

        $offsets = [1 => 0, 2 => 4, 3 => 8, 4 => 14, 5 => 20, 6 => 28,
                    7 => 30, 8 => 27, 9 => 21, 10 => 15, 11 => 5, 12 => 1];
        $minima = $PARAMETERS['date']['base_temperature'] + ($offsets[$mese] ?? 0);

        if ($ore < 14) {
            $gradi = $minima + (floor($ore / 3) * $caso);
        } else {
            $gradi = $minima + (4 * $caso) - (floor($ore / 3) * $caso) + (3 * $caso);
        }

        $caso = ($giorno + ($ore / 4)) % 12;
        $cond_map = [
            0 => 0, 6 => 0, 10 => 0, 11 => 0, 1 => 0,
            7 => 1, 5 => 1, 2 => 1,
            9 => 2, 3 => 2,
            8 => 3, 4 => 3,
        ];
        $idx = $cond_map[$caso] ?? 0;
        if ($idx === 3 && $minima < 4) $idx = 4;
        $meteo_cond = $MESSAGE['interface']['meteo']['status'][$idx] ?? '';
        $meteo = $meteo_cond . ' · ' . $gradi . '°C';
    } else {
        $meteo = gdrcd_filter('out', $record['meteo'] ?? '');
    }
}

$data_gioco = date('d/m/') . (date('Y') + ($PARAMETERS['date']['offset'] ?? 0));
$moon = (defined('MOON') && MOON) ? gdrcd_lunar_phase() : null;
?>

<div class="gdrcd-widget-title flex items-center gap-2 -m-4 mb-3 px-4 py-2">
    <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    <span class="truncate"><?= gdrcd_filter('out', $nome_luogo) ?></span>
</div>

<?php if (!($record_exists > 0 || ($_SESSION['luogo'] ?? 0) == -1)): ?>
    <div class="gdrcd-alert-error text-xs">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01"/></svg>
        <div><?= gdrcd_filter('out', $MESSAGE['error']['location_doesnt_exist']) ?></div>
    </div>
<?php else: ?>
    <!-- Immagine luogo -->
    <div class="rounded-md overflow-hidden border border-gdrcd-border bg-gdrcd-panel-alt">
        <img src="themes/<?= $theme ?>/imgs/locations/<?= htmlspecialchars($immagine_luogo) ?>"
             alt="<?= gdrcd_filter('out', $nome_luogo) ?>"
             title="<?= gdrcd_filter('out', $record['descrizione'] ?? '') ?>"
             class="w-full h-32 object-cover">
    </div>

    <?php if (!empty($record['stato']) || !empty($record['descrizione'])): ?>
        <div class="text-xs text-gdrcd-text-soft border-l-2 border-gdrcd-accent/40 pl-2 leading-relaxed">
            <?php if (!empty($record['stato'])): ?>
                <div class="font-display text-gdrcd-text mb-1">
                    <?= gdrcd_filter('out', $MESSAGE['interface']['maps']['Status']) ?>: <?= gdrcd_filter('out', $record['stato']) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($record['descrizione'])): ?>
                <div><?= gdrcd_filter('out', $record['descrizione']) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($meteo)): ?>
        <div class="rounded-md border border-gdrcd-border bg-gdrcd-panel-alt/40 p-3 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    <?= gdrcd_filter('out', $MESSAGE['interface']['meteo']['title']) ?>
                </span>
                <span class="text-[10px] text-gdrcd-text-soft tabular-nums"><?= $data_gioco ?></span>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($moon !== null): ?>
                    <img title="Luna <?= htmlspecialchars($moon['title']) ?>"
                         alt="Luna <?= htmlspecialchars($moon['title']) ?>"
                         src="themes/<?= $theme ?>/imgs/luna/<?= htmlspecialchars($moon['phase']) ?>.png"
                         class="w-8 h-8 object-contain shrink-0">
                <?php endif; ?>
                <div class="text-sm text-gdrcd-text font-display">
                    <?= $meteo ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
