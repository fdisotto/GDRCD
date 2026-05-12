<?php
declare(strict_types=1);

/**
 * View helpers GDRCD.
 *
 * Componenti UI riusabili: alert, badge, header pagina, ecc. Tutti
 * ritornano stringa (chiamabili sia con `echo` che concatenati). Niente
 * side effect.
 *
 * Sostituiscono blocchi HTML duplicati (es. ~50 occorrenze di
 * `<div class="gdrcd-alert-*">...SVG...$msg</div>`).
 *
 * Convenzioni:
 *   - prefisso `gdrcd_view_` per UI primitives
 *   - ogni helper return string (mai echo internamente)
 *   - $msg passato gia' HTML-safe oppure raw a discrezione del chiamante:
 *     gli alert NON ri-escape (consenti markup come <strong> o link)
 */

require_once __DIR__ . '/icons.inc.php';

/**
 * Renderizza un alert.
 *
 *   $kind: 'error' | 'warning' | 'success' | 'info'
 *   $msg : contenuto (HTML safe gia' applicato dal caller, oppure plain text)
 *   $opts:
 *     - 'icon_cls' (default 'w-5 h-5 mt-0.5 shrink-0')
 *     - 'extra_cls' classi extra sul wrapper
 *     - 'raw' (bool, default false): se true non fa htmlspecialchars su $msg
 *
 * @param string $kind
 * @param string $msg
 * @param array{icon_cls?:string,extra_cls?:string,raw?:bool} $opts
 */
if (!function_exists('gdrcd_view_alert')) {
    function gdrcd_view_alert(string $kind, string $msg, array $opts = []): string
    {
        $kindToIcon = [
            'error'   => 'alert-warning',
            'warning' => 'alert-warning',
            'success' => 'check',
            'info'    => 'alert-info',
        ];
        $kindCls = [
            'error'   => 'gdrcd-alert-error',
            'warning' => 'gdrcd-alert-warning',
            'success' => 'gdrcd-alert-success',
            'info'    => 'gdrcd-alert-info',
        ];
        $cls   = $kindCls[$kind]    ?? 'gdrcd-alert-info';
        $icon  = $kindToIcon[$kind] ?? 'alert-info';
        $iconCls = $opts['icon_cls'] ?? 'w-5 h-5 mt-0.5 shrink-0';
        $raw   = (bool)($opts['raw'] ?? false);
        $extra = isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '';
        $body  = $raw ? $msg : htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');

        return '<div class="' . $cls . $extra . '">'
             . gdrcd_icon($icon, $iconCls)
             . '<div>' . $body . '</div>'
             . '</div>';
    }
}

/** Shortcut convenience: gdrcd_alert_error / _warning / _success / _info. */
if (!function_exists('gdrcd_alert_error')) {
    function gdrcd_alert_error(string $msg, array $opts = []): string   { return gdrcd_view_alert('error', $msg, $opts); }
}
if (!function_exists('gdrcd_alert_warning')) {
    function gdrcd_alert_warning(string $msg, array $opts = []): string { return gdrcd_view_alert('warning', $msg, $opts); }
}
if (!function_exists('gdrcd_alert_success')) {
    function gdrcd_alert_success(string $msg, array $opts = []): string { return gdrcd_view_alert('success', $msg, $opts); }
}
if (!function_exists('gdrcd_alert_info')) {
    function gdrcd_alert_info(string $msg, array $opts = []): string    { return gdrcd_view_alert('info', $msg, $opts); }
}

/* =====================================================================
 * Card / section / header helpers.
 * ===================================================================== */

/**
 * Renderizza una card (section con header opzionale + body).
 *
 *   gdrcd_view_card('Titolo', '<p>contenuto</p>');
 *   gdrcd_view_card('Titolo', function () { echo '...'; });
 *   gdrcd_view_card(null, $body, ['elev' => true, 'extra_cls' => 'mb-4']);
 *
 * Opzioni:
 *   - 'elev'        bool   -> usa .gdrcd-card-elev (ombra piu' marcata)
 *   - 'extra_cls'   string -> classi extra sul wrapper
 *   - 'header_cls'  string -> classi extra sull'header
 *   - 'body_cls'    string -> classi extra sul body (default 'gdrcd-card-body')
 *   - 'header_extra' string|callable -> markup extra a destra dell'header
 *                                       (es. badge contatore, link, bottone)
 *   - 'icon'        string -> nome icona da prefissare al titolo
 *
 * Il body puo' essere:
 *   - string (markup gia' pronto)
 *   - callable: viene chiamato senza argomenti, il suo output viene catturato
 *
 * Ritorna stringa.
 *
 * @param string|null         $title
 * @param string|callable     $body
 * @param array<string,mixed> $opts
 */
if (!function_exists('gdrcd_view_card')) {
    function gdrcd_view_card(?string $title, $body, array $opts = []): string
    {
        $elev      = !empty($opts['elev']);
        $extraCls  = isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '';
        $headerCls = isset($opts['header_cls']) ? ' ' . $opts['header_cls'] : '';
        $bodyCls   = $opts['body_cls'] ?? 'gdrcd-card-body';
        $headerExtra = $opts['header_extra'] ?? null;
        $icon      = $opts['icon'] ?? '';

        $bodyHtml = '';
        if (is_callable($body)) {
            ob_start();
            $body();
            $bodyHtml = (string)ob_get_clean();
        } else {
            $bodyHtml = (string)$body;
        }

        $headerHtml = '';
        if ($title !== null && $title !== '') {
            $iconHtml = $icon !== '' ? gdrcd_icon($icon, 'w-5 h-5 text-gdrcd-accent shrink-0') : '';
            $extraHtml = '';
            if (is_callable($headerExtra)) {
                ob_start();
                $headerExtra();
                $extraHtml = (string)ob_get_clean();
            } elseif (is_string($headerExtra)) {
                $extraHtml = $headerExtra;
            }
            $headerHtml = '<div class="gdrcd-card-header flex items-center justify-between gap-3' . $headerCls . '">'
                        . '<h3 class="gdrcd-h3 flex items-center gap-2">'
                        .   $iconHtml
                        .   '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>'
                        . '</h3>'
                        . ($extraHtml !== '' ? '<div class="shrink-0">' . $extraHtml . '</div>' : '')
                        . '</div>';
        }

        $rootCls = $elev ? 'gdrcd-card-elev' : 'gdrcd-card';
        return '<section class="' . $rootCls . $extraCls . '">'
             . $headerHtml
             . '<div class="' . $bodyCls . '">' . $bodyHtml . '</div>'
             . '</section>';
    }
}

/**
 * Header pagina standard (h1 + sottotitolo opzionale).
 *
 *   echo gdrcd_view_page_header('Gestione quest', 'Crea, modifica, assegna.');
 */
if (!function_exists('gdrcd_view_page_header')) {
    function gdrcd_view_page_header(string $title, string $subtitle = '', array $opts = []): string
    {
        $icon = $opts['icon'] ?? '';
        $iconHtml = $icon !== ''
            ? '<span class="gdrcd-icon-circle">' . gdrcd_icon($icon, 'w-6 h-6') . '</span>'
            : '';
        $sub = $subtitle !== ''
            ? '<p class="gdrcd-muted">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';
        return '<header class="space-y-2">'
             . '<h2 class="gdrcd-h1 flex items-center gap-3">'
             .   $iconHtml
             .   '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>'
             . '</h2>'
             . $sub
             . '</header>';
    }
}

/**
 * Badge inline.
 *   echo gdrcd_view_badge('Attiva', 'success');
 *   echo gdrcd_view_badge('5', 'neutral', ['size' => 'sm']);
 *
 * Variant: 'neutral' | 'accent' | 'success' | 'error' | 'warning'.
 */
if (!function_exists('gdrcd_view_badge')) {
    function gdrcd_view_badge(string $text, string $variant = 'neutral', array $opts = []): string
    {
        $map = [
            'neutral' => 'gdrcd-badge-neutral',
            'accent'  => 'gdrcd-badge-accent',
            'success' => 'gdrcd-badge-success',
            'error'   => 'gdrcd-badge-error',
            'warning' => 'gdrcd-badge-warning',
        ];
        $cls = $map[$variant] ?? 'gdrcd-badge-neutral';
        if (($opts['size'] ?? '') === 'sm') {
            $cls .= ' text-[10px]';
        }
        return '<span class="' . $cls . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
