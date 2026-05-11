<?php
/**
 * Scheda PG — equipaggiamento (oggetti posizione > 0).
 * Vista paper-doll + lista oggetti indossati/in zaino.
 */

if (!isset($_REQUEST['pg'])) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}

$pg = $_REQUEST['pg'];
$check = gdrcd_query("SELECT sesso FROM personaggio WHERE nome = '" . gdrcd_filter('in', $pg) . "'", 'result');
if (gdrcd_query($check, 'num_rows') === 0) {
    echo '<div class="gdrcd-alert-error">' . gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet']) . '</div>';
    return;
}
$pg_data = gdrcd_query($check, 'fetch');
gdrcd_query($check, 'free');
$sesso = $pg_data['sesso'];

$can_modify = ($_SESSION['login'] === $pg) || ((int)$_SESSION['permessi'] >= GAMEMASTER);
$op         = $_POST['op'] ?? null;
$alerts     = [];

if ($can_modify) {
    if ($op === 'abbandona') {
        if ((int)$_POST['numero'] <= 1) {
            gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        } else {
            gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
        }
        gdrcd_query(
            "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES ("
            . "'" . gdrcd_filter('in', $pg) . "',"
            . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
            . "NOW(), " . BONIFICO . ","
            . "' -" . gdrcd_filter('in', $_POST['checosa'] ?? '') . "')"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];

    } elseif ($op === 'cedi') {
        $id_obj  = gdrcd_filter('num', $_POST['id_oggetto']);
        $num     = (int)$_POST['numero'];
        $dest    = gdrcd_filter('in', $_POST['give_item']);
        $cariche = gdrcd_filter('num', $_POST['cariche']);

        $exists = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj, 'result');
        if ((int)gdrcd_query($exists, 'num_rows') > 0) {
            gdrcd_query($exists, 'free');
            if ($num <= 1) {
                gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
            } else {
                gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero - 1 WHERE id_oggetto = " . $id_obj . " AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1");
            }
            $dest_check = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj . " AND nome = '" . $dest . "'", 'result');
            if ((int)gdrcd_query($dest_check, 'num_rows') > 0) {
                gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero + 1 WHERE id_oggetto = " . $id_obj . " AND nome = '" . $dest . "'");
            } else {
                gdrcd_query("INSERT INTO clgpersonaggiooggetto (nome, id_oggetto, cariche, numero) VALUES ('" . $dest . "', " . $id_obj . ", " . $cariche . ", 1)");
            }
            gdrcd_query($dest_check, 'free');
            gdrcd_query(
                "INSERT INTO log (nome_interessato, autore, data_evento, codice_evento, descrizione_evento) VALUES ("
                . "'" . $dest . "',"
                . "'" . gdrcd_filter('in', $_SESSION['login']) . "',"
                . "NOW(), " . BONIFICO . ","
                . "'" . gdrcd_filter('in', $_POST['checosa'] ?? '') . "')"
            );
            $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
        } else {
            $alerts[] = ['error', gdrcd_filter('out', $MESSAGE['warning']['cant_do'])];
        }

    } elseif ($op === 'indossa') {
        gdrcd_query(
            "UPDATE clgpersonaggiooggetto SET posizione = " . gdrcd_filter('num', $_POST['posizione']) .
            " WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) .
            "   AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];

    } elseif ($op === 'in_zaino') {
        gdrcd_query(
            "UPDATE clgpersonaggiooggetto SET posizione = 1
             WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto']) .
            "   AND nome = '" . gdrcd_filter('in', $pg) . "' LIMIT 1"
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['done'])];
    }
}

/* Carico oggetti indossati (posizione > 1, slot specifici) */
$oggetti = [];
$result = gdrcd_query(
    "SELECT oggetto.id_oggetto, oggetto.nome, oggetto.urlimg AS immagine, clgpersonaggiooggetto.posizione
     FROM clgpersonaggiooggetto JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
     WHERE clgpersonaggiooggetto.posizione > 1
       AND clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $pg) . "'",
    'result'
);
while ($r = gdrcd_query($result, 'fetch')) {
    $oggetti[(int)$r['posizione']] = $r;
}
gdrcd_query($result, 'free');

$theme = $PARAMETERS['themes']['current_theme'];
$lbl_i = $MESSAGE['interface']['sheet']['items']['list'];
$lbl_m = $MESSAGE['interface']['sheet']['menu'];

/** Helper: render slot oggetto posizionato in assoluto sopra il corpo SVG. */
$render_slot = function (?array $item, string $label, string $icon_svg, string $pos_class) use ($theme) {
    $has_item = $item !== null && !empty($item['immagine']);
    $title    = $has_item ? gdrcd_filter('out', $item['nome']) : htmlspecialchars($label);
    $cls      = $has_item
        ? 'border-gdrcd-accent ring-2 ring-gdrcd-accent/30 shadow-lg shadow-gdrcd-accent/10'
        : 'border-gdrcd-border border-dashed';
    ob_start(); ?>
    <div class="absolute <?= $pos_class ?> -translate-x-1/2 -translate-y-1/2 group z-10">
        <div class="flex flex-col items-center gap-1">
            <div class="w-16 h-16 rounded-lg border-2 <?= $cls ?> bg-gdrcd-panel/95 backdrop-blur-sm flex items-center justify-center overflow-hidden transition-all duration-200 group-hover:scale-110 group-hover:z-20"
                 title="<?= $title ?>">
                <?php if ($has_item): ?>
                    <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/items/<?= htmlspecialchars($item['immagine']) ?>"
                         alt="<?= $title ?>" class="w-full h-full object-cover"/>
                <?php else: ?>
                    <svg class="w-7 h-7 text-gdrcd-subtle" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><?= $icon_svg ?></svg>
                <?php endif; ?>
            </div>
            <div class="text-[10px] font-display uppercase tracking-wider <?= $has_item ? 'text-gdrcd-accent' : 'text-gdrcd-text-soft' ?> whitespace-nowrap">
                <?= htmlspecialchars($label) ?>
            </div>
        </div>
        <?php if ($has_item): ?>
            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 px-2 py-1 rounded bg-gdrcd-panel border border-gdrcd-accent/40 text-[11px] text-gdrcd-text whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-md z-30">
                <?= gdrcd_filter('out', $item['nome']) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
};

/* Personaggi disponibili per cedi */
$chars_list = [];
if ($can_modify) {
    if (($PARAMETERS['mode']['give_only_if_online'] ?? 'OFF') === 'ON') {
        $q = "SELECT nome FROM personaggio
              WHERE ultimo_luogo = " . (int)($_SESSION['luogo'] ?? 0) . "
                AND ultimo_luogo <> -1
                AND nome <> '" . gdrcd_filter('in', $_SESSION['login']) . "'
                AND DATE_ADD(ultimo_refresh, INTERVAL 2 MINUTE) > NOW()
              ORDER BY nome";
    } else {
        $q = "SELECT nome FROM personaggio ORDER BY nome";
    }
    $cres = gdrcd_query($q, 'result');
    while ($c = gdrcd_query($cres, 'fetch')) $chars_list[] = $c['nome'];
    gdrcd_query($cres, 'free');
}
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            <?= gdrcd_filter('out', $lbl_m['equipment']) ?>
            <span class="text-gdrcd-accent">·</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $pg) ?></span>
        </h2>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <!-- Paper doll -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header flex items-center justify-between">
            <h3 class="gdrcd-h3">Equipaggiato</h3>
            <span class="text-xs text-gdrcd-text-soft tabular-nums">
                <?= count($oggetti) ?> / 8 slot
            </span>
        </div>
        <div class="gdrcd-card-body">
            <div class="relative mx-auto" style="width:100%; max-width:34rem; aspect-ratio:1/1.3;">
                <!-- Sfondo decorativo -->
                <div class="absolute inset-0 rounded-2xl bg-gradient-to-b from-gdrcd-accent/5 to-transparent pointer-events-none"></div>

                <!--
                  Omino SVG — silhouette simmetrica, asse centrale x=110.
                  viewBox 220x360, ogni coppia di punti specchiata rispetto a x=110.
                -->
                <svg viewBox="0 0 220 360" preserveAspectRatio="xMidYMid meet"
                     class="absolute inset-0 w-full h-full text-gdrcd-accent"
                     fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true">
                    <defs>
                        <linearGradient id="bodyGrad" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%"   stop-color="currentColor" stop-opacity="0.28"/>
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0.06"/>
                        </linearGradient>
                        <radialGradient id="headGrad" cx="0.5" cy="0.4" r="0.6">
                            <stop offset="0%"   stop-color="currentColor" stop-opacity="0.34"/>
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0.10"/>
                        </radialGradient>
                    </defs>

                    <!-- Linee connettori (sotto al corpo) -->
                    <g stroke="currentColor" stroke-opacity="0.18" stroke-dasharray="2 3" stroke-width="1">
                        <line x1="92"  y1="36"  x2="40"  y2="36"/>
                        <line x1="128" y1="68"  x2="194" y2="58"/>
                        <line x1="138" y1="138" x2="194" y2="137"/>
                        <line x1="58"  y1="200" x2="22"  y2="180"/>
                        <line x1="42"  y1="228" x2="22"  y2="227"/>
                        <line x1="178" y1="228" x2="198" y2="227"/>
                        <line x1="138" y1="280" x2="194" y2="288"/>
                        <line x1="78"  y1="352" x2="26"  y2="335"/>
                    </g>

                    <!-- TESTA -->
                    <ellipse cx="110" cy="36" rx="20" ry="24"
                             fill="url(#headGrad)" stroke-opacity="0.65"/>

                    <!-- COLLO -->
                    <path d="M100 58 L100 74 Q110 80 120 74 L120 58 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.55"/>

                    <!-- BUSTO (simmetrico) -->
                    <path d="M70 82
                             Q60 84 58 102
                             L62 162
                             Q66 178 80 184
                             L140 184
                             Q154 178 158 162
                             L162 102
                             Q160 84 150 82
                             Q130 74 110 74
                             Q90 74 70 82 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.6"/>

                    <!-- Linea sterno -->
                    <path d="M110 78 L110 182" stroke-opacity="0.18" stroke-width="1"/>

                    <!-- BRACCIO destro (lato sinistro nello schermo) -->
                    <path d="M58 92
                             Q40 100 36 132
                             Q34 168 42 200
                             Q46 216 54 218
                             L66 218
                             Q70 200 68 168
                             Q66 134 70 108
                             L70 96 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.55"/>

                    <!-- BRACCIO sinistro (lato destro nello schermo) -->
                    <path d="M162 92
                             Q180 100 184 132
                             Q186 168 178 200
                             Q174 216 166 218
                             L154 218
                             Q150 200 152 168
                             Q154 134 150 108
                             L150 96 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.55"/>

                    <!-- MANO destra -->
                    <circle cx="50" cy="228" r="10"
                            fill="url(#bodyGrad)" stroke-opacity="0.65"/>
                    <!-- MANO sinistra -->
                    <circle cx="170" cy="228" r="10"
                            fill="url(#bodyGrad)" stroke-opacity="0.65"/>

                    <!-- BACINO -->
                    <path d="M62 184
                             Q64 200 78 210
                             L142 210
                             Q156 200 158 184 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.55"/>

                    <!-- GAMBA destra -->
                    <path d="M78 210
                             L84 280
                             Q86 320 92 344
                             L108 344
                             L108 210 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.55"/>
                    <!-- GAMBA sinistra -->
                    <path d="M142 210
                             L136 280
                             Q134 320 128 344
                             L112 344
                             L112 210 Z"
                          fill="url(#bodyGrad)" stroke-opacity="0.55"/>

                    <!-- PIEDI -->
                    <ellipse cx="98"  cy="352" rx="14" ry="6"
                             fill="url(#bodyGrad)" stroke-opacity="0.6"/>
                    <ellipse cx="122" cy="352" rx="14" ry="6"
                             fill="url(#bodyGrad)" stroke-opacity="0.6"/>
                </svg>

                <!-- Slot equipaggiamento -->
                <?= $render_slot($oggetti[TESTA]  ?? null, 'Testa',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13a7 7 0 0114 0v3a2 2 0 01-2 2H7a2 2 0 01-2-2v-3zM7 8l5-4 5 4"/>',
                    'top-[10%] left-[12%]') ?>
                <?= $render_slot($oggetti[COLLO]  ?? null, 'Collo',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M6 4l6 9 6-9M9 12l3 8 3-8"/>',
                    'top-[16%] right-[12%] translate-x-1/2') ?>
                <?= $render_slot($oggetti[TORSO]  ?? null, 'Torso',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M5 6l4-2 3 2 3-2 4 2v5a8 8 0 11-14 0V6z"/>',
                    'top-[38%] right-[12%] translate-x-1/2') ?>
                <?= $render_slot($oggetti[ANELLO] ?? null, 'Anello',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9a4 4 0 100 8 4 4 0 000-8zm-5-4l3 3m9-3l-3 3"/>',
                    'top-[50%] left-[10%]') ?>
                <?= $render_slot($oggetti[MANODX] ?? null, 'Mano DX',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M9 11V5a2 2 0 014 0v6h2a2 2 0 012 2v4a4 4 0 01-4 4h-3a4 4 0 01-4-4v-3l-2-3a1.5 1.5 0 012-2l1 1z"/>',
                    'top-[63%] left-[10%]') ?>
                <?= $render_slot($oggetti[MANOSX] ?? null, 'Mano SX',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M15 11V5a2 2 0 10-4 0v6H9a2 2 0 00-2 2v4a4 4 0 004 4h3a4 4 0 004-4v-3l2-3a1.5 1.5 0 00-2-2l-1 1z"/>',
                    'top-[63%] right-[10%] translate-x-1/2') ?>
                <?= $render_slot($oggetti[GAMBE]  ?? null, 'Gambe',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l-1 9-1 9h-2l-1-9L9 3z"/>',
                    'top-[80%] right-[12%] translate-x-1/2') ?>
                <?= $render_slot($oggetti[PIEDI]  ?? null, 'Piedi',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M4 17h12a3 3 0 003-3V8H7v6l-3 3z"/>',
                    'top-[93%] left-[12%]') ?>
            </div>

            <!-- Legenda -->
            <div class="mt-4 pt-3 border-t border-gdrcd-border flex flex-wrap gap-4 justify-center text-[11px] text-gdrcd-text-soft">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded border-2 border-gdrcd-accent bg-gdrcd-panel ring-2 ring-gdrcd-accent/25"></span>
                    Slot occupato
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded border-2 border-dashed border-gdrcd-border bg-gdrcd-panel"></span>
                    Slot libero
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-3 h-px border-t border-dashed border-gdrcd-accent/50"></span>
                    Connessione al corpo
                </span>
            </div>
        </div>
    </section>

    <!-- Lista oggetti indossati e in zaino -->
    <?php
    $list = gdrcd_query(
        "SELECT oggetto.id_oggetto, oggetto.nome AS nome_oggetto, oggetto.descrizione, oggetto.urlimg,
                oggetto.ubicabile, oggetto.difesa, oggetto.attacco,
                oggetto.bonus_car0, oggetto.bonus_car1, oggetto.bonus_car2, oggetto.bonus_car3, oggetto.bonus_car4, oggetto.bonus_car5,
                clgpersonaggiooggetto.*
         FROM clgpersonaggiooggetto LEFT JOIN oggetto ON clgpersonaggiooggetto.id_oggetto = oggetto.id_oggetto
         WHERE clgpersonaggiooggetto.nome = '" . gdrcd_filter('in', $pg) . "'
           AND clgpersonaggiooggetto.posizione > 0
         ORDER BY oggetto.nome DESC",
        'result'
    );
    $listcount = (int)gdrcd_query($list, 'num_rows');
    ?>
    <section>
        <header class="flex items-baseline justify-between gap-2 mb-3">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['items']['zaino']) ?></h3>
        </header>

        <?php if ($listcount === 0): ?>
            <div class="gdrcd-alert-info">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>Nessun oggetto indossato o nello zaino equipaggiato.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php while ($r = gdrcd_query($list, 'fetch')):
                    $bonuses = [];
                    if ((int)$r['attacco'] !== 0) $bonuses['ATK'] = (int)$r['attacco'];
                    if ((int)$r['difesa']  !== 0) $bonuses['DEF'] = (int)$r['difesa'];
                    for ($i = 0; $i < 6; $i++) {
                        $v = (int)$r['bonus_car'.$i];
                        if ($v !== 0) {
                            $bonuses[gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i])] = $v;
                        }
                    }
                    $is_zaino = ((int)$r['posizione'] === 1);
                    ?>
                    <article class="gdrcd-card overflow-hidden">
                        <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3 p-4">
                            <div class="shrink-0">
                                <?php if (!empty($r['urlimg'])): ?>
                                    <img src="themes/<?= htmlspecialchars($theme) ?>/imgs/items/<?= htmlspecialchars($r['urlimg']) ?>"
                                         alt="" class="w-20 h-20 rounded-md object-cover border border-gdrcd-border bg-gdrcd-panel-alt"/>
                                <?php else: ?>
                                    <div class="w-20 h-20 rounded-md bg-gdrcd-panel-alt"></div>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <h4 class="font-display font-semibold text-gdrcd-text">
                                        <?= gdrcd_filter('out', $r['nome_oggetto']) ?>
                                    </h4>
                                    <span class="gdrcd-badge-accent text-[10px]">× <?= (int)$r['numero'] ?></span>
                                    <?php if ($is_zaino): ?>
                                        <span class="gdrcd-badge-neutral text-[10px]">In zaino</span>
                                    <?php else: ?>
                                        <span class="gdrcd-badge-success text-[10px]">Indossato</span>
                                    <?php endif; ?>
                                    <?php if ((int)$r['cariche'] > 0): ?>
                                        <span class="gdrcd-badge-neutral text-[10px]">
                                            <?= gdrcd_filter('out', $lbl_i['charges']) ?>: <?= (int)$r['cariche'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($r['descrizione'])): ?>
                                    <p class="text-xs text-gdrcd-muted leading-relaxed">
                                        <?= gdrcd_filter('out', $r['descrizione']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($bonuses)): ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($bonuses as $lbl => $v):
                                            $cls = $v > 0 ? 'gdrcd-badge-success' : 'gdrcd-badge-error';
                                            $sign = $v > 0 ? '+' : '';
                                            ?>
                                            <span class="<?= $cls ?> text-[10px]">
                                                <?= htmlspecialchars($lbl) ?>
                                                <strong class="ml-0.5"><?= $sign . $v ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($r['commento'])): ?>
                                    <div class="text-xs italic text-gdrcd-muted border-l-2 border-gdrcd-border pl-2">
                                        <?= gdrcd_filter('out', $r['commento']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($can_modify): ?>
                            <div class="border-t border-gdrcd-border bg-gdrcd-panel-alt/30 px-4 py-3 flex flex-wrap gap-2">

                                <form action="main.php?page=scheda_oggetti" method="post" class="inline">
                                    <input type="hidden" name="op" value="togli"/>
                                    <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                    <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                    <button type="submit" class="gdrcd-btn-ghost text-xs">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                        <?= gdrcd_filter('out', $lbl_i['put_away']) ?>
                                    </button>
                                </form>

                                <?php if ((int)$r['ubicabile'] > ZAINO):
                                    if ($is_zaino):
                                        // Mostra azioni per indossare/impugnare
                                        if (!isset($oggetti[(int)$r['ubicabile']])): ?>
                                            <form action="main.php?page=scheda_equip" method="post" class="inline">
                                                <input type="hidden" name="op" value="indossa"/>
                                                <input type="hidden" name="posizione" value="<?= (int)$r['ubicabile'] ?>"/>
                                                <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                                <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                                <button type="submit" class="gdrcd-btn-primary text-xs">
                                                    <?= gdrcd_filter('out', $lbl_i['wear']) ?>
                                                </button>
                                            </form>
                                        <?php endif;
                                        if (!isset($oggetti[MANODX])): ?>
                                            <form action="main.php?page=scheda_equip" method="post" class="inline">
                                                <input type="hidden" name="op" value="indossa"/>
                                                <input type="hidden" name="posizione" value="<?= MANODX ?>"/>
                                                <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                                <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                                <button type="submit" class="gdrcd-btn-secondary text-xs">
                                                    <?= gdrcd_filter('out', $lbl_i['wield']) ?> (DX)
                                                </button>
                                            </form>
                                        <?php endif;
                                        if (!isset($oggetti[MANOSX])): ?>
                                            <form action="main.php?page=scheda_equip" method="post" class="inline">
                                                <input type="hidden" name="op" value="indossa"/>
                                                <input type="hidden" name="posizione" value="<?= MANOSX ?>"/>
                                                <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                                <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                                <button type="submit" class="gdrcd-btn-secondary text-xs">
                                                    <?= gdrcd_filter('out', $lbl_i['wield']) ?> (SX)
                                                </button>
                                            </form>
                                        <?php endif;
                                    else: ?>
                                        <form action="main.php?page=scheda_equip" method="post" class="inline">
                                            <input type="hidden" name="op" value="indossa"/>
                                            <input type="hidden" name="posizione" value="1"/>
                                            <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                            <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                            <button type="submit" class="gdrcd-btn-secondary text-xs">
                                                <?= gdrcd_filter('out', $lbl_i['unwear']) ?>
                                            </button>
                                        </form>
                                    <?php endif;
                                endif; ?>

                                <form action="main.php?page=scheda_equip" method="post" class="inline"
                                      onsubmit="return confirm('Abbandonare un esemplare?');">
                                    <input type="hidden" name="op" value="abbandona"/>
                                    <input type="hidden" name="numero" value="<?= (int)$r['numero'] ?>"/>
                                    <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                    <input type="hidden" name="checosa" value="<?= gdrcd_filter('out', $r['nome_oggetto']) ?>"/>
                                    <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                    <button type="submit" class="gdrcd-btn-ghost text-xs">
                                        <?= gdrcd_filter('out', $lbl_i['drop']) ?>
                                    </button>
                                </form>

                                <?php if (count($chars_list) > 0): ?>
                                    <form action="main.php?page=scheda_equip" method="post" class="inline-flex items-center gap-1">
                                        <input type="hidden" name="op" value="cedi"/>
                                        <input type="hidden" name="pg" value="<?= htmlspecialchars($pg) ?>"/>
                                        <input type="hidden" name="id_oggetto" value="<?= (int)$r['id_oggetto'] ?>"/>
                                        <input type="hidden" name="cariche" value="<?= (int)$r['cariche'] ?>"/>
                                        <input type="hidden" name="numero" value="<?= (int)$r['numero'] ?>"/>
                                        <input type="hidden" name="checosa" value="<?= gdrcd_filter('out', $r['nome_oggetto']) ?>"/>
                                        <select class="gdrcd-select text-xs py-1" name="give_item">
                                            <?php foreach ($chars_list as $n): ?>
                                                <option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="gdrcd-btn-secondary text-xs">
                                            <?= gdrcd_filter('out', $lbl_i['give']) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endwhile;
                gdrcd_query($list, 'free');
                ?>
            </div>
        <?php endif; ?>
    </section>

    <div>
        <a href="main.php?page=scheda_oggetti&pg=<?= urlencode($pg) ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $lbl_m['inventory']) ?>
        </a>
    </div>
</div>
