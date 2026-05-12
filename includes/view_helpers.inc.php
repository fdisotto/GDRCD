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
