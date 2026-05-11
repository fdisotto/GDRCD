<?php
/**
 * Gestione pagine legali (privacy policy + termini di servizio)
 *   main.php?page=gestione/legal
 *
 * Permette agli amministratori (SUPERUSER) di modificare titolo e corpo
 * BBCode delle pagine pubbliche /main.php?page=privacy_policy e
 * /main.php?page=tos. Persistenza nella tabella `legal_pages`.
 *
 * Il CSRF e' gia' validato centralmente in main.php su ogni POST.
 */

if (($_SESSION['permessi'] ?? 0) < SUPERUSER) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

/* ------------------------------------------------------------------
 * Slug supportati e metadati di presentazione.
 * ------------------------------------------------------------------ */
$pages = [
    'privacy_policy' => [
        'label'      => 'Informativa privacy',
        'public_url' => 'main.php?page=privacy_policy',
    ],
    'tos' => [
        'label'      => 'Termini di servizio',
        'public_url' => 'main.php?page=tos',
    ],
];

/* ------------------------------------------------------------------
 * Tab attivo (privacy_policy di default).
 * ------------------------------------------------------------------ */
$active = $_REQUEST['tab'] ?? ($_POST['slug'] ?? 'privacy_policy');
if (!isset($pages[$active])) {
    $active = 'privacy_policy';
}

/* ------------------------------------------------------------------
 * Handler POST.
 * ------------------------------------------------------------------ */
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'save') {
    $slug_in = $_POST['slug'] ?? '';
    if (!isset($pages[$slug_in])) {
        $flash = ['kind' => 'warning', 'message' => 'Slug non valido.'];
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $body  = (string)($_POST['body'] ?? '');

        if ($title === '') {
            $flash = ['kind' => 'warning', 'message' => 'Il titolo non puo\' essere vuoto.'];
        } else {
            $slug_q  = gdrcd_filter('in', $slug_in);
            $title_q = gdrcd_filter('in', $title);
            $body_q  = gdrcd_filter('in', $body);
            $user_q  = gdrcd_filter('in', (string)($_SESSION['login'] ?? ''));

            // Upsert: garantisce la riga anche se la migrazione/seed non hanno girato.
            gdrcd_query(
                "INSERT INTO legal_pages (slug, title, body, updated_by) VALUES ("
                . "'" . $slug_q . "', '" . $title_q . "', '" . $body_q . "', '" . $user_q . "')"
                . " ON DUPLICATE KEY UPDATE "
                . "title = VALUES(title), body = VALUES(body), updated_by = VALUES(updated_by)"
            );

            $flash  = ['kind' => 'success', 'message' => 'Pagina aggiornata correttamente.'];
            $active = $slug_in;
        }
    }
}

/* ------------------------------------------------------------------
 * Carica i contenuti correnti di tutte le pagine.
 * ------------------------------------------------------------------ */
$loaded = [];
foreach ($pages as $slug => $_meta) {
    $slug_q = gdrcd_filter('in', $slug);
    $row = gdrcd_query(
        "SELECT slug, title, body, updated_at, updated_by FROM legal_pages WHERE slug = '" . $slug_q . "' LIMIT 1"
    );
    $loaded[$slug] = $row ?: [
        'slug'       => $slug,
        'title'      => $pages[$slug]['label'],
        'body'       => '',
        'updated_at' => null,
        'updated_by' => null,
    ];
}
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1">Pagine legali</h2>
        <p class="gdrcd-muted">
            Modifica titolo e corpo (BBCode supportato) delle pagine pubbliche di privacy e termini di servizio.
            I contenuti sono memorizzati nella tabella
            <code class="text-xs px-1 py-0.5 rounded bg-gdrcd-muted-bg">legal_pages</code>.
        </p>
    </header>

    <?php if ($flash !== null): ?>
        <?php if ($flash['kind'] === 'success'): ?>
            <div class="gdrcd-alert-success">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php else: ?>
            <div class="gdrcd-alert-warning">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div><?= gdrcd_filter('out', $flash['message']) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 border-b border-gdrcd-border" aria-label="Pagine legali">
        <?php foreach ($pages as $slug => $meta):
            $is_active = ($slug === $active);
            $tab_url   = 'main.php?page=gestione/legal&tab=' . urlencode($slug);
        ?>
            <a href="<?= htmlspecialchars($tab_url) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 -mb-px border-b-2 text-sm font-medium transition-colors
                      <?= $is_active
                            ? 'border-gdrcd-accent text-gdrcd-accent'
                            : 'border-transparent text-gdrcd-muted hover:text-gdrcd-text hover:border-gdrcd-border' ?>">
                <?= htmlspecialchars($meta['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php $row = $loaded[$active]; $meta = $pages[$active]; ?>

    <section class="gdrcd-card">
        <div class="gdrcd-card-header flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h3 class="gdrcd-h3"><?= htmlspecialchars($meta['label']) ?></h3>
                <p class="gdrcd-muted text-xs">
                    Slug: <code><?= htmlspecialchars($active) ?></code>
                    &middot;
                    Anteprima pubblica:
                    <a class="gdrcd-link" href="<?= htmlspecialchars($meta['public_url']) ?>" target="_blank" rel="noopener">
                        /<?= htmlspecialchars($meta['public_url']) ?>
                    </a>
                </p>
            </div>
            <?php if (!empty($row['updated_at'])): ?>
                <div class="text-right text-xs text-gdrcd-muted">
                    <div>Ultimo aggiornamento:</div>
                    <div class="text-gdrcd-text tabular-nums">
                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['updated_at']))) ?>
                    </div>
                    <?php if (!empty($row['updated_by'])): ?>
                        <div>da <strong class="text-gdrcd-text"><?= gdrcd_filter('out', $row['updated_by']) ?></strong></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <form action="main.php?page=gestione/legal" method="post" class="gdrcd-card-body space-y-5">
            <?= gdrcd_csrf_field() ?>
            <input type="hidden" name="op"   value="save">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($active) ?>">

            <div>
                <label class="gdrcd-label" for="legal_title">Titolo</label>
                <input class="gdrcd-input w-full" type="text" id="legal_title" name="title"
                       value="<?= gdrcd_filter('out', $row['title']) ?>" maxlength="255" required>
            </div>

            <div>
                <label class="gdrcd-label" for="legal_body">Contenuto</label>
                <textarea class="gdrcd-textarea" id="legal_body" name="body" rows="22" data-bbcode><?= gdrcd_filter('out', $row['body']) ?></textarea>
                <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['interface']['help']['bbcode'] ?? 'BBCode supportato.') ?></p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                <a href="<?= htmlspecialchars($meta['public_url']) ?>" target="_blank" rel="noopener" class="gdrcd-btn-ghost">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Anteprima pubblica
                </a>
                <button type="submit" class="gdrcd-btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['ui']['actions']['save'] ?? 'Salva') ?>
                </button>
            </div>
        </form>
    </section>

</div>
