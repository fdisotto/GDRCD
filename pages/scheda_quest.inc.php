<?php
/**
 * Scheda PG — pannello quest del personaggio.
 *   main.php?page=scheda_quest&pg=<nome>
 *
 * Mostra:
 *   - Quest attive (clgquestpg.status = 'attiva')
 *   - Quest concluse (status IN ('completata','fallita')) collassabili
 *
 * La pagina e' pubblica (visibile sia all'owner che a un altro PG): le
 * quest sono considerate parte della "biografia" del personaggio.
 *
 * Lo schema vive nelle tabelle `quest` (definizione) e `clgquestpg`
 * (legame PG <-> quest + stato). Vedi migrazione
 * db_versions/2026051119_GDRCDQuests.php.
 */

if (!isset($_REQUEST['pg']) || $_REQUEST['pg'] === '') {
    echo gdrcd_alert_error(
        gdrcd_filter('out', $MESSAGE['error']['unknown_character_sheet'] ?? 'Personaggio sconosciuto'),
        ['raw' => true]
    );
    return;
}

use GDRCD\Models\Quest;

$pg_q = (string)$_REQUEST['pg'];
$quests = Quest::forPg($pg_q);
$active = $quests['active'];
$done   = $quests['done'];

$active_n = count($active);
$done_n   = count($done);

/**
 * Helper rendering BBCode-safe per i campi descrittivi.
 */
$render_text = function (?string $txt): string {
    if ($txt === null || trim($txt) === '') {
        return '<span class="text-gdrcd-subtle italic">&mdash;</span>';
    }
    return gdrcd_bbcoder(gdrcd_filter('out', $txt));
};

$status_badge = [
    'attiva'     => ['label' => 'Attiva',     'class' => 'gdrcd-badge-accent'],
    'completata' => ['label' => 'Completata', 'class' => 'gdrcd-badge-success'],
    'fallita'    => ['label' => 'Fallita',    'class' => 'gdrcd-badge-neutral'],
];
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h2 class="gdrcd-h1">
            Quest
            <span class="text-gdrcd-accent">&middot;</span>
            <span class="text-gdrcd-text-soft text-2xl"><?= gdrcd_filter('out', $_REQUEST['pg']) ?></span>
        </h2>
        <p class="gdrcd-muted text-sm">
            Quest assegnate al personaggio.
            <span class="text-gdrcd-text">Attive: <strong><?= (int)$active_n ?></strong></span>
            &middot;
            <span>Concluse: <strong><?= (int)$done_n ?></strong></span>
        </p>
    </header>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border pb-3" aria-label="Sezioni scheda">
        <?php include 'scheda/menu.inc.php'; ?>
    </nav>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header flex items-center justify-between gap-3">
            <h3 class="gdrcd-h3">Quest attive</h3>
            <span class="gdrcd-badge-accent"><?= (int)$active_n ?></span>
        </div>
        <div class="gdrcd-card-body space-y-4">
            <?php if ((int)$active_n === 0): ?>
                <p class="text-gdrcd-muted italic">Nessuna quest attiva per questo personaggio.</p>
            <?php else: ?>
                <?php foreach ($active as $q): ?>
                    <article class="rounded-md border border-gdrcd-border bg-gdrcd-panel-alt/60 p-4 space-y-2">
                        <header class="flex flex-wrap items-baseline justify-between gap-2">
                            <h4 class="font-semibold text-gdrcd-text text-base">
                                <?= gdrcd_filter('out', (string)$q['titolo']) ?>
                            </h4>
                            <div class="text-xs text-gdrcd-muted">
                                Assegnata il
                                <span class="tabular-nums text-gdrcd-text-soft">
                                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$q['assegnata_il']))) ?>
                                </span>
                            </div>
                        </header>
                        <div class="gdrcd-prose text-sm">
                            <?= $render_text($q['descrizione']) ?>
                        </div>
                        <?php if (!empty($q['obiettivo'])): ?>
                            <div class="mt-2">
                                <div class="text-xs uppercase tracking-wide text-gdrcd-muted mb-1">Obiettivo</div>
                                <div class="gdrcd-prose text-sm"><?= $render_text($q['obiettivo']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($q['ricompensa'])): ?>
                            <div class="mt-2">
                                <div class="text-xs uppercase tracking-wide text-gdrcd-muted mb-1">Ricompensa</div>
                                <div class="gdrcd-prose text-sm"><?= $render_text($q['ricompensa']) ?></div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="gdrcd-card">
        <details<?= ((int)$done_n > 0 && (int)$active_n === 0) ? ' open' : '' ?>>
            <summary class="gdrcd-card-header flex items-center justify-between gap-3 cursor-pointer select-none">
                <h3 class="gdrcd-h3">Quest concluse</h3>
                <span class="gdrcd-badge-neutral"><?= (int)$done_n ?></span>
            </summary>
            <div class="gdrcd-card-body space-y-4">
                <?php if ((int)$done_n === 0): ?>
                    <p class="text-gdrcd-muted italic">Nessuna quest conclusa.</p>
                <?php else: ?>
                    <?php foreach ($done as $q):
                        $st = (string)$q['status'];
                        $badge = $status_badge[$st] ?? ['label' => $st, 'class' => 'gdrcd-badge-neutral'];
                    ?>
                        <article class="rounded-md border border-gdrcd-border bg-gdrcd-panel-alt/60 p-4 space-y-2 opacity-95">
                            <header class="flex flex-wrap items-baseline justify-between gap-2">
                                <h4 class="font-semibold text-gdrcd-text text-base flex items-center gap-2">
                                    <?= gdrcd_filter('out', (string)$q['titolo']) ?>
                                    <span class="<?= htmlspecialchars($badge['class']) ?>"><?= htmlspecialchars($badge['label']) ?></span>
                                </h4>
                                <div class="text-xs text-gdrcd-muted">
                                    <?php if (!empty($q['conclusa_il'])): ?>
                                        Conclusa il
                                        <span class="tabular-nums text-gdrcd-text-soft">
                                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$q['conclusa_il']))) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </header>
                            <div class="gdrcd-prose text-sm">
                                <?= $render_text($q['descrizione']) ?>
                            </div>
                            <?php if (!empty($q['obiettivo'])): ?>
                                <div class="mt-2">
                                    <div class="text-xs uppercase tracking-wide text-gdrcd-muted mb-1">Obiettivo</div>
                                    <div class="gdrcd-prose text-sm"><?= $render_text($q['obiettivo']) ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($q['ricompensa'])): ?>
                                <div class="mt-2">
                                    <div class="text-xs uppercase tracking-wide text-gdrcd-muted mb-1">Ricompensa</div>
                                    <div class="gdrcd-prose text-sm"><?= $render_text($q['ricompensa']) ?></div>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </details>
    </section>
</div>
