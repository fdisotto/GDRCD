<?php
/**
 * Widget sidebar — mini-map: luoghi adiacenti ed eventuali uscite
 * verso altre mappe collegate.
 *
 * Mostra:
 *  - "Luoghi vicini": altre stanze (mappa.chat=1) della stessa mappa_click,
 *    escluso il luogo corrente.
 *  - "Uscite": link verso una mappa_click collegata, se la stanza corrente
 *    ha id_mappa_collegata != 0.
 *
 * Edge case: quando $_SESSION['luogo'] == -1 (utente nella overview della
 * mappa, non in una stanza) elenchiamo tutte le stanze della mappa corrente
 * come quick-access.
 */

$current_luogo  = (int)($_SESSION['luogo'] ?? 0);
$current_mappa  = (int)($_SESSION['mappa'] ?? 0);

$neighbors = [];
$exit_link = null;

if ($current_mappa > 0) {
    // Determina la mappa_click a cui appartiene la stanza corrente.
    // Se siamo dentro una stanza (luogo > 0) usiamo mappa.id_mappa,
    // altrimenti ripieghiamo su $_SESSION['mappa'].
    $id_mappa_click = $current_mappa;
    if ($current_luogo > 0) {
        $cur = gdrcd_query(
            "SELECT id_mappa, id_mappa_collegata
             FROM mappa
             WHERE id = " . $current_luogo,
            'fetch'
        );
        if (!empty($cur['id_mappa'])) {
            $id_mappa_click = (int)$cur['id_mappa'];
        }
        // Uscita verso un'altra mappa_click (link diretto dalla stanza).
        if (!empty($cur['id_mappa_collegata']) && (int)$cur['id_mappa_collegata'] !== 0) {
            $target_click = (int)$cur['id_mappa_collegata'];
            $exit_row = gdrcd_query(
                "SELECT nome FROM mappa_click WHERE id_click = " . $target_click,
                'fetch'
            );
            if (!empty($exit_row['nome'])) {
                $exit_link = [
                    'nome' => $exit_row['nome'],
                    'url'  => 'main.php?page=mappaclick&map_id=' . $target_click,
                ];
            }
        }
    }

    // Stanze adiacenti: stesso id_mappa_click, chat=1, escluso il luogo corrente.
    $where_exclude = ($current_luogo > 0) ? " AND id <> " . $current_luogo : "";
    $res = gdrcd_query(
        "SELECT id, nome
         FROM mappa
         WHERE id_mappa = " . (int)$id_mappa_click . "
           AND chat = 1" . $where_exclude . "
         ORDER BY nome ASC",
        'result'
    );
    if (gdrcd_query($res, 'num_rows') > 0) {
        while ($row = gdrcd_query($res, 'fetch')) {
            if (empty($row['nome'])) continue;
            $neighbors[] = [
                'id'   => (int)$row['id'],
                'nome' => $row['nome'],
                'url'  => 'main.php?dir=' . (int)$row['id'] . '&map_id=' . (int)$id_mappa_click,
            ];
        }
        gdrcd_query($res, 'free');
    }
}

$has_content = !empty($neighbors) || $exit_link !== null;
?>

<div class="gdrcd-widget-title flex items-center gap-2 -m-4 mb-3 px-4 py-2">
    <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.553 2.776A1 1 0 0021 18.882V8.118a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
    </svg>
    <span>Luoghi vicini</span>
</div>

<?php if (!$has_content): ?>
    <div class="text-xs text-gdrcd-text-soft italic px-1 py-2">
        Luogo isolato.
    </div>
<?php else: ?>
    <?php if (!empty($neighbors)): ?>
        <ul class="space-y-1">
            <?php foreach ($neighbors as $n): ?>
                <li>
                    <a href="<?= htmlspecialchars($n['url'], ENT_QUOTES, 'UTF-8') ?>"
                       class="block px-2 py-1.5 rounded text-sm hover:bg-gdrcd-accent-soft text-gdrcd-text transition-colors"
                       title="<?= gdrcd_filter('out', $n['nome']) ?>">
                        &rarr; <?= gdrcd_filter('out', $n['nome']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($exit_link !== null): ?>
        <div class="mt-3 pt-3 border-t border-gdrcd-border">
            <div class="text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display mb-1 px-1">
                Uscite
            </div>
            <ul class="space-y-1">
                <li>
                    <a href="<?= htmlspecialchars($exit_link['url'], ENT_QUOTES, 'UTF-8') ?>"
                       class="block px-2 py-1.5 rounded text-sm hover:bg-gdrcd-accent-soft text-gdrcd-accent transition-colors"
                       title="Esci verso <?= gdrcd_filter('out', $exit_link['nome']) ?>">
                        &#8617; Esci verso <?= gdrcd_filter('out', $exit_link['nome']) ?>
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>
