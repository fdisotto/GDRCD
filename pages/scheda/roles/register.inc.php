<?php
/**
 * Scheda PG — form registrazione manuale giocata.
 */

$pg_url = gdrcd_filter('url', $_REQUEST['pg']);

$render_back = function () use ($pg_url, $MESSAGE) { ?>
    <div>
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= gdrcd_filter('out', $MESSAGE['interface']['sheet']['link']['back_roles']) ?>
        </a>
    </div>
<?php };

if ($_REQUEST['pg'] != $_SESSION['login']) {
    echo '<div class="gdrcd-alert-error">Non puoi inserire registrazioni nella scheda altrui.</div>';
    $render_back();
    return;
}

$mese  = (int)($_POST['mese'] ?? date('m'));
$anno  = (int)($_POST['anno'] ?? date('Y'));

// Range datetime ammessi: dal primo del mese precedente (se non gennaio) all'ultimo del mese successivo (se non dicembre)
$first_mese = $mese === 1 ? $mese : $mese - 1;
$last_mese  = $mese === 12 ? $mese : $mese + 1;
$min_dt = sprintf('%04d-%02d-01T00:00', $anno, $first_mese);
$max_dt = date('Y-m-t\T23:59', strtotime(sprintf('%04d-%02d-01', $anno, $last_mese)));

// Default: oggi alle 20:00 se nel mese corrente, altrimenti primo del mese a 20:00
$default_inizio = sprintf('%04d-%02d-%02dT20:00', $anno, $mese, min((int)date('d'), 28));
$default_fine   = sprintf('%04d-%02d-%02dT22:00', $anno, $mese, min((int)date('d'), 28));

$chat_res = gdrcd_query("SELECT nome, id FROM mappa WHERE chat=1 ORDER BY nome", 'result');
$chats = [];
while ($r = gdrcd_query($chat_res, 'fetch')) $chats[] = $r;
gdrcd_query($chat_res, 'free');

$mesi_label = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
];
?>

<div class="gdrcd-card p-4 flex items-center gap-3">
    <span class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-gdrcd-accent-soft text-gdrcd-accent shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    </span>
    <div class="flex-1">
        <div class="font-display text-base text-gdrcd-text">Registra nuova giocata</div>
        <p class="text-xs text-gdrcd-text-soft">Mese di riferimento: <strong><?= $mesi_label[$mese] ?> <?= $anno ?></strong></p>
    </div>
</div>

<form action="main.php?page=scheda_roles&pg=<?= $pg_url ?>" method="post" class="space-y-4">

    <div class="gdrcd-alert-info">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>
        <div>
            <p>Registra le giocate dimenticate in chat. Specifica chat, intervallo orario e dettagli.</p>
            <p class="text-sm mt-1">L'orario sarà verificato: servono almeno <strong><?= REG_MIN_AZIONI ?> azioni</strong>. In caso di margini troppo larghi, il sistema salverà fra la prima e l'ultima azione del personaggio. Durata massima: <strong>12 ore</strong>.</p>
        </div>
    </div>

    <article class="gdrcd-card p-4 space-y-3">
        <h3 class="font-display text-base text-gdrcd-accent flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Chat di gioco
        </h3>
        <label class="block">
            <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">Seleziona luogo</span>
            <select name="luogo" class="gdrcd-select mt-1 w-full" required>
                <option value="" disabled selected>— Scegli una chat —</option>
                <?php foreach ($chats as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= gdrcd_filter('out', $c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </article>

    <div class="grid md:grid-cols-2 gap-4">
        <article class="gdrcd-card p-4 space-y-3">
            <h3 class="font-display text-base text-gdrcd-accent flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Inizio giocata
            </h3>
            <label class="block">
                <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">Data e ora</span>
                <input type="datetime-local" name="dt_a" class="gdrcd-input mt-1 w-full"
                       value="<?= htmlspecialchars($default_inizio) ?>"
                       min="<?= htmlspecialchars($min_dt) ?>"
                       max="<?= htmlspecialchars($max_dt) ?>"
                       step="300" required>
            </label>
        </article>

        <article class="gdrcd-card p-4 space-y-3">
            <h3 class="font-display text-base text-gdrcd-accent flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Fine giocata
            </h3>
            <label class="block">
                <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">Data e ora</span>
                <input type="datetime-local" name="dt_b" class="gdrcd-input mt-1 w-full"
                       value="<?= htmlspecialchars($default_fine) ?>"
                       min="<?= htmlspecialchars($min_dt) ?>"
                       max="<?= htmlspecialchars($max_dt) ?>"
                       step="300" required>
            </label>
        </article>
    </div>

    <article class="gdrcd-card p-4 space-y-3">
        <h3 class="font-display text-base text-gdrcd-accent flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            Dettagli giocata
        </h3>
        <label class="block">
            <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">Tag</span>
            <input name="ab" type="text" value="" class="gdrcd-input mt-1 w-full" placeholder="es. duello, esplorazione, taverna…" maxlength="255">
            <span class="text-xs text-gdrcd-text-soft mt-1 block">Brevi parole-chiave per ritrovare la role.</span>
        </label>
        <label class="block">
            <span class="text-xs uppercase tracking-wide text-gdrcd-text-soft font-display">Note di trama</span>
            <input name="quest" type="text" value="" class="gdrcd-input mt-1 w-full" placeholder="Brevissimo riassunto interazioni di trama" maxlength="500">
            <span class="text-xs text-gdrcd-text-soft mt-1 block">Senza segnalazione il GM non riceve notifica.</span>
        </label>
    </article>

    <div class="flex justify-between items-center gap-2">
        <a href="main.php?page=scheda_roles&pg=<?= $pg_url ?>" class="gdrcd-btn-ghost">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            Annulla
        </a>
        <input type="hidden" name="op" value="send_segn">
        <input type="hidden" name="mese" value="<?= $mese ?>">
        <input type="hidden" name="anno" value="<?= $anno ?>">
        <button type="submit" class="gdrcd-btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Registra giocata
        </button>
    </div>
</form>
