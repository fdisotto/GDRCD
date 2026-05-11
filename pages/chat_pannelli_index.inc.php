<?php
/**
 * Dispatcher pannelli chat (popup.php?page=chat_pannelli_index&pannello=<nome>).
 */

$pannello = gdrcd_filter('include', $_GET['pannello'] ?? '');
$file     = __DIR__ . '/chat/pannelli/' . $pannello . '.php';

if ($pannello !== '' && file_exists($file)) {
    include $file;
} else {
    ?>
    <div class="gdrcd-alert-error">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div>Pannello non trovato.</div>
    </div>
    <?php
}
