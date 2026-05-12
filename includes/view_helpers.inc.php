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

/* =====================================================================
 * Form helpers.
 *
 * Tutti gli helper field accettano stessi opts comuni:
 *   - 'name'     (required)
 *   - 'label'    string visualizzato sopra il field
 *   - 'value'    valore corrente
 *   - 'id'       default derivato da name (slug + nome)
 *   - 'required' bool
 *   - 'help'     stringa hint sotto il field
 *   - 'extra_attrs' string raw appended (es. data-*)
 *   - 'extra_cls'   string aggiunta alla classe del field
 * ===================================================================== */

if (!function_exists('_gdrcd_field_id')) {
    function _gdrcd_field_id(array $opts): string
    {
        if (!empty($opts['id'])) return (string)$opts['id'];
        $n = (string)($opts['name'] ?? '');
        return 'fld_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $n);
    }
}

if (!function_exists('_gdrcd_field_wrap')) {
    function _gdrcd_field_wrap(string $id, string $label, string $control, string $help = ''): string
    {
        $labelHtml = $label !== ''
            ? '<label class="gdrcd-label" for="' . htmlspecialchars($id) . '">' . htmlspecialchars($label) . '</label>'
            : '';
        $helpHtml = $help !== ''
            ? '<p class="gdrcd-help">' . $help . '</p>'
            : '';
        return '<div>' . $labelHtml . $control . $helpHtml . '</div>';
    }
}

if (!function_exists('_gdrcd_attr')) {
    function _gdrcd_attr(string $name, $value): string
    {
        if ($value === null || $value === false || $value === '') return '';
        if ($value === true) return ' ' . $name;
        return ' ' . $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
    }
}

/** Input <text|email|url|date|datetime-local|number|...>. */
if (!function_exists('gdrcd_field_text')) {
    function gdrcd_field_text(array $opts): string
    {
        $type   = (string)($opts['type'] ?? 'text');
        $name   = (string)($opts['name'] ?? '');
        $id     = _gdrcd_field_id($opts);
        $label  = (string)($opts['label'] ?? '');
        $value  = (string)($opts['value'] ?? '');
        $cls    = 'gdrcd-input' . (isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '');

        $attrs  = _gdrcd_attr('type', $type)
                . _gdrcd_attr('class', $cls)
                . _gdrcd_attr('id', $id)
                . _gdrcd_attr('name', $name)
                . _gdrcd_attr('value', $value)
                . _gdrcd_attr('placeholder', $opts['placeholder'] ?? null)
                . _gdrcd_attr('maxlength', $opts['maxlength'] ?? null)
                . _gdrcd_attr('minlength', $opts['minlength'] ?? null)
                . _gdrcd_attr('min', $opts['min'] ?? null)
                . _gdrcd_attr('max', $opts['max'] ?? null)
                . _gdrcd_attr('step', $opts['step'] ?? null)
                . _gdrcd_attr('list', $opts['list'] ?? null)
                . _gdrcd_attr('autocomplete', $opts['autocomplete'] ?? null)
                . _gdrcd_attr('pattern', $opts['pattern'] ?? null)
                . (!empty($opts['required']) ? ' required' : '')
                . (!empty($opts['readonly']) ? ' readonly' : '')
                . (!empty($opts['disabled']) ? ' disabled' : '')
                . (isset($opts['extra_attrs']) ? ' ' . $opts['extra_attrs'] : '');

        $control = '<input' . $attrs . '/>';
        return _gdrcd_field_wrap($id, $label, $control, (string)($opts['help'] ?? ''));
    }
}

/** <textarea>. Opt 'bbcode' = true → data-bbcode (abilita editor anteprima). */
if (!function_exists('gdrcd_field_textarea')) {
    function gdrcd_field_textarea(array $opts): string
    {
        $name   = (string)($opts['name'] ?? '');
        $id     = _gdrcd_field_id($opts);
        $label  = (string)($opts['label'] ?? '');
        $value  = (string)($opts['value'] ?? '');
        $rows   = (int)($opts['rows'] ?? 5);
        $cls    = 'gdrcd-textarea' . (isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '');

        $attrs  = _gdrcd_attr('class', $cls)
                . _gdrcd_attr('id', $id)
                . _gdrcd_attr('name', $name)
                . _gdrcd_attr('rows', $rows)
                . _gdrcd_attr('placeholder', $opts['placeholder'] ?? null)
                . _gdrcd_attr('maxlength', $opts['maxlength'] ?? null)
                . (!empty($opts['required']) ? ' required' : '')
                . (!empty($opts['readonly']) ? ' readonly' : '')
                . (!empty($opts['disabled']) ? ' disabled' : '')
                . (!empty($opts['bbcode'])   ? ' data-bbcode' : '')
                . (isset($opts['extra_attrs']) ? ' ' . $opts['extra_attrs'] : '');

        // textarea value tra i tag (gia' HTML-escaped via gdrcd_filter dal caller);
        // qui ri-escapiamo per sicurezza, default behavior.
        $body = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        if (!empty($opts['raw_value'])) $body = $value;

        $control = '<textarea' . $attrs . '>' . $body . '</textarea>';
        return _gdrcd_field_wrap($id, $label, $control, (string)($opts['help'] ?? ''));
    }
}

/**
 * <select>.
 *   'options' formato:
 *     - array<string|int, string>            label semplice
 *     - array<int, ['value'=>...,'label'=>..., 'disabled'=>bool]>  esteso
 */
if (!function_exists('gdrcd_field_select')) {
    function gdrcd_field_select(array $opts): string
    {
        $name   = (string)($opts['name'] ?? '');
        $id     = _gdrcd_field_id($opts);
        $label  = (string)($opts['label'] ?? '');
        $opts_  = $opts['options'] ?? [];
        $sel    = $opts['selected'] ?? null;
        $cls    = 'gdrcd-select' . (isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '');

        $attrs  = _gdrcd_attr('class', $cls)
                . _gdrcd_attr('id', $id)
                . _gdrcd_attr('name', $name)
                . (!empty($opts['required']) ? ' required' : '')
                . (!empty($opts['disabled']) ? ' disabled' : '')
                . (isset($opts['extra_attrs']) ? ' ' . $opts['extra_attrs'] : '');

        $optsHtml = '';
        if (isset($opts['placeholder'])) {
            $optsHtml .= '<option value="">' . htmlspecialchars((string)$opts['placeholder']) . '</option>';
        }
        foreach ($opts_ as $k => $v) {
            if (is_array($v)) {
                $val = (string)($v['value'] ?? $k);
                $lbl = (string)($v['label'] ?? '');
                $dis = !empty($v['disabled']) ? ' disabled' : '';
            } else {
                $val = (string)$k;
                $lbl = (string)$v;
                $dis = '';
            }
            $isSel = ($sel !== null && (string)$sel === $val) ? ' selected' : '';
            $optsHtml .= '<option value="' . htmlspecialchars($val, ENT_QUOTES) . '"' . $isSel . $dis . '>'
                       . htmlspecialchars($lbl, ENT_QUOTES) . '</option>';
        }

        $control = '<select' . $attrs . '>' . $optsHtml . '</select>';
        return _gdrcd_field_wrap($id, $label, $control, (string)($opts['help'] ?? ''));
    }
}

/** Hidden input one-liner. */
if (!function_exists('gdrcd_field_hidden')) {
    function gdrcd_field_hidden(string $name, $value = ''): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES) . '"/>';
    }
}

/** Checkbox singolo con label inline. */
if (!function_exists('gdrcd_field_checkbox')) {
    function gdrcd_field_checkbox(array $opts): string
    {
        $name    = (string)($opts['name'] ?? '');
        $id      = _gdrcd_field_id($opts);
        $label   = (string)($opts['label'] ?? '');
        $value   = (string)($opts['value'] ?? '1');
        $checked = !empty($opts['checked']);
        $help    = (string)($opts['help'] ?? '');

        $attrs = ' type="checkbox" name="' . htmlspecialchars($name) . '"'
               . ' id="' . htmlspecialchars($id) . '"'
               . ' value="' . htmlspecialchars($value, ENT_QUOTES) . '"'
               . ($checked ? ' checked' : '')
               . ' class="rounded border-gdrcd-border text-gdrcd-accent focus:ring-gdrcd-accent-ring"';

        $wrap  = '<label class="inline-flex items-center gap-2 text-sm text-gdrcd-text-soft cursor-pointer">'
              .  '<input' . $attrs . '/>'
              .  '<span>' . htmlspecialchars($label) . '</span>'
              .  '</label>';

        if ($help !== '') {
            $wrap .= '<p class="gdrcd-help -mt-3">' . $help . '</p>';
        }
        return $wrap;
    }
}

/* =====================================================================
 * Form layout helpers.
 * ===================================================================== */

/** Apre <form> + injetta CSRF token field. */
if (!function_exists('gdrcd_form_open')) {
    function gdrcd_form_open(array $opts): string
    {
        $action = (string)($opts['action'] ?? '');
        $method = strtolower((string)($opts['method'] ?? 'post'));
        $cls    = 'space-y-4' . (isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '');
        $enctype = isset($opts['enctype']) ? ' enctype="' . htmlspecialchars($opts['enctype']) . '"' : '';
        $id      = isset($opts['id']) ? ' id="' . htmlspecialchars($opts['id']) . '"' : '';
        $target  = isset($opts['target']) ? ' target="' . htmlspecialchars($opts['target']) . '"' : '';
        $onsub   = isset($opts['onsubmit']) ? ' onsubmit="' . htmlspecialchars($opts['onsubmit'], ENT_QUOTES) . '"' : '';

        $html = '<form action="' . htmlspecialchars($action) . '" method="' . $method . '"'
              . ' class="' . $cls . '"' . $enctype . $id . $target . $onsub . '>';

        // CSRF su POST.
        if ($method === 'post' && function_exists('gdrcd_csrf_field')) {
            $html .= gdrcd_csrf_field();
        }
        return $html;
    }
}

/** Chiude </form>. */
if (!function_exists('gdrcd_form_close')) {
    function gdrcd_form_close(): string
    {
        return '</form>';
    }
}

/** Barra azioni form (allineata a destra di default su >=sm). */
if (!function_exists('gdrcd_form_actions')) {
    function gdrcd_form_actions(array $items, array $opts = []): string
    {
        $cls = $opts['cls'] ?? 'flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border';
        return '<div class="' . $cls . '">' . implode("\n", $items) . '</div>';
    }
}

/* =====================================================================
 * Button / link helpers.
 * ===================================================================== */

if (!function_exists('_gdrcd_btn_class')) {
    function _gdrcd_btn_class(string $variant): string
    {
        switch ($variant) {
            case 'ghost':     return 'gdrcd-btn-ghost';
            case 'secondary': return 'gdrcd-btn-secondary';
            case 'danger':    return 'gdrcd-btn-ghost text-red-600';
            case 'primary':
            default:          return 'gdrcd-btn-primary';
        }
    }
}

/**
 * Button HTML.
 *   gdrcd_view_button('Salva', ['icon' => 'check', 'variant' => 'primary']);
 *   gdrcd_view_button('Annulla', ['type' => 'button', 'variant' => 'ghost']);
 */
if (!function_exists('gdrcd_view_button')) {
    function gdrcd_view_button(string $label, array $opts = []): string
    {
        $type    = (string)($opts['type'] ?? 'submit');
        $variant = (string)($opts['variant'] ?? 'primary');
        $cls     = _gdrcd_btn_class($variant) . (isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '');
        $icon    = (string)($opts['icon'] ?? '');
        $iconHtml = $icon !== '' ? gdrcd_icon($icon, 'w-4 h-4') : '';
        $name    = isset($opts['name']) ? ' name="' . htmlspecialchars($opts['name']) . '"' : '';
        $value   = isset($opts['value']) ? ' value="' . htmlspecialchars($opts['value']) . '"' : '';
        $onclick = isset($opts['onclick']) ? ' onclick="' . htmlspecialchars($opts['onclick'], ENT_QUOTES) . '"' : '';
        $extra   = isset($opts['extra_attrs']) ? ' ' . $opts['extra_attrs'] : '';

        return '<button type="' . htmlspecialchars($type) . '" class="' . $cls . '"' . $name . $value . $onclick . $extra . '>'
             . $iconHtml
             . htmlspecialchars($label)
             . '</button>';
    }
}

/**
 * Link stilizzato come button.
 *   gdrcd_view_link('Indietro', $url, ['icon' => 'arrow-left', 'variant' => 'ghost']);
 */
if (!function_exists('gdrcd_view_link')) {
    function gdrcd_view_link(string $label, string $href, array $opts = []): string
    {
        $variant = (string)($opts['variant'] ?? 'primary');
        $cls     = _gdrcd_btn_class($variant) . (isset($opts['extra_cls']) ? ' ' . $opts['extra_cls'] : '');
        $icon    = (string)($opts['icon'] ?? '');
        $iconHtml = $icon !== '' ? gdrcd_icon($icon, 'w-4 h-4') : '';
        $target  = isset($opts['target']) ? ' target="' . htmlspecialchars($opts['target']) . '"' : '';
        $rel     = isset($opts['rel'])    ? ' rel="' . htmlspecialchars($opts['rel']) . '"' : '';

        return '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '"' . $target . $rel . '>'
             . $iconHtml
             . htmlspecialchars($label)
             . '</a>';
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
