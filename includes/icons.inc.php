<?php
declare(strict_types=1);

/**
 * Libreria icone SVG inline (Heroicons mini 20x20).
 * Tutte le funzioni restituiscono stringa HTML. Includono <title> per tooltip
 * nativo + role/aria-label per accessibilità.
 *
 * Convenzioni:
 *   - dimensione di default w-4 h-4 (16px)
 *   - colore via Tailwind text-*
 *   - $label opzionale → diventa tooltip e aria-label
 *
 * Uso:
 *   include_once __DIR__ . '/../includes/icons.inc.php';
 *   echo gdrcd_icon_disp(0, 'Disponibile');
 *   echo gdrcd_icon_perm(GAMEMASTER, 'Master');
 */

if (!function_exists('gdrcd_svg_title')) {
    function gdrcd_svg_title(string $label): string
    {
        return $label !== '' ? '<title>' . htmlspecialchars($label) . '</title>' : '';
    }
}

if (!function_exists('gdrcd_svg_attrs')) {
    function gdrcd_svg_attrs(string $cls, string $label): string
    {
        $aria = $label !== '' ? ' role="img" aria-label="' . htmlspecialchars($label) . '"' : ' aria-hidden="true"';
        return 'class="' . $cls . '" viewBox="0 0 20 20" fill="currentColor"' . $aria;
    }
}

/** Pallino disponibilità (0=verde 1=giallo 2=rosso). */
if (!function_exists('gdrcd_icon_disp')) {
    function gdrcd_icon_disp(int $state, string $label = ''): string
    {
        $colors = [0 => 'bg-green-500', 1 => 'bg-yellow-500', 2 => 'bg-red-500'];
        $cls = $colors[$state] ?? 'bg-gray-400';
        $aria = $label !== '' ? ' title="' . htmlspecialchars($label) . '" aria-label="' . htmlspecialchars($label) . '"' : '';
        return '<span class="inline-block w-2.5 h-2.5 rounded-full shrink-0 ' . $cls . '"' . $aria . '></span>';
    }
}

/** Icona ruolo/permessi. */
if (!function_exists('gdrcd_icon_perm')) {
    function gdrcd_icon_perm(int $level, string $label = '', string $size = 'w-4 h-4'): string
    {
        $t = gdrcd_svg_title($label);
        switch ($level) {
            case SUPERUSER:
                return '<svg ' . gdrcd_svg_attrs($size . ' text-purple-600 shrink-0', $label) . '>' . $t
                    . '<path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813A3.75 3.75 0 007.466 7.89l.813-2.846A.75.75 0 019 4.5z" clip-rule="evenodd"/></svg>';
            case MODERATOR:
                return '<svg ' . gdrcd_svg_attrs($size . ' text-red-600 shrink-0', $label) . '>' . $t
                    . '<path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zm3.78 7.625a.75.75 0 00-1.06-1.06L9.22 12.07 7.28 10.13a.75.75 0 00-1.06 1.06l2.47 2.47a.75.75 0 001.06 0l4.03-4.03z" clip-rule="evenodd"/></svg>';
            case GAMEMASTER:
                return '<svg ' . gdrcd_svg_attrs($size . ' text-gdrcd-accent shrink-0', $label) . '>' . $t
                    . '<path d="M9.504 1.132a1 1 0 01.992 0l8 4.571A1 1 0 0119 6.57v6.857a1 1 0 01-1.504.867l-7-4-7 4A1 1 0 011 13.428V6.571a1 1 0 01.504-.867l8-4.572z"/></svg>';
            case GUILDMODERATOR:
                return '<svg ' . gdrcd_svg_attrs($size . ' text-amber-500 shrink-0', $label) . '>' . $t
                    . '<path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd"/></svg>';
            default:
                return '';
        }
    }
}

/** Simbolo genere (m/f). */
if (!function_exists('gdrcd_icon_gender')) {
    function gdrcd_icon_gender(string $sex, string $label = '', string $size = 'w-4 h-4'): string
    {
        $t = gdrcd_svg_title($label);
        if ($sex === 'm') {
            return '<svg ' . gdrcd_svg_attrs($size . ' text-sky-600 shrink-0', $label) . '>' . $t
                . '<path fill-rule="evenodd" d="M12 2a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 11-2 0V4.414l-2.86 2.86A6 6 0 1110.726 6.14l2.86-2.86H12a1 1 0 01-1-1zM8 9a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd"/></svg>';
        }
        if ($sex === 'f') {
            return '<svg ' . gdrcd_svg_attrs($size . ' text-pink-600 shrink-0', $label) . '>' . $t
                . '<path fill-rule="evenodd" d="M10 2a5 5 0 100 10 5 5 0 000-10zm-1 11.93A7 7 0 1110 0a7 7 0 011 13.93V16h2a1 1 0 110 2h-2v1a1 1 0 11-2 0v-1H7a1 1 0 110-2h2v-2.07z" clip-rule="evenodd"/></svg>';
        }
        return '';
    }
}

/** Indicatore "appena entrato" (freccia in rettangolo). */
if (!function_exists('gdrcd_icon_enter')) {
    function gdrcd_icon_enter(string $label = '', string $size = 'w-4 h-4'): string
    {
        $t = gdrcd_svg_title($label);
        return '<svg ' . gdrcd_svg_attrs($size . ' text-green-600 shrink-0', $label) . '>' . $t
            . '<path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/>'
            . '<path fill-rule="evenodd" d="M6 10a.75.75 0 01.75-.75h9.546l-1.048-.943a.75.75 0 111.004-1.114l2.5 2.25a.75.75 0 010 1.114l-2.5 2.25a.75.75 0 11-1.004-1.114l1.048-.943H6.75A.75.75 0 016 10z" clip-rule="evenodd"/></svg>';
    }
}

/** Toggle visibilità (occhio aperto / barrato). */
if (!function_exists('gdrcd_icon_visible')) {
    function gdrcd_icon_visible(bool $invisible, string $label = '', string $size = 'w-4 h-4'): string
    {
        $t = gdrcd_svg_title($label);
        if ($invisible) {
            return '<svg ' . gdrcd_svg_attrs($size . ' text-gdrcd-text-soft shrink-0', $label) . '>' . $t
                . '<path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/>'
                . '<path d="M10.748 13.93l2.523 2.523a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>';
        }
        return '<svg ' . gdrcd_svg_attrs($size . ' text-gdrcd-text-soft shrink-0', $label) . '>' . $t
            . '<path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>'
            . '<path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>';
    }
}

/** Avatar razza (img se non default, altrimenti vuoto). */
if (!function_exists('gdrcd_icon_race')) {
    function gdrcd_icon_race(string $icon, string $theme, string $label = '', string $prefix = 'themes', string $size = 'w-4 h-4'): string
    {
        if (empty($icon) || $icon === 'standard_razza.png') {
            return '';
        }
        $alt = htmlspecialchars($label);
        return '<img src="' . $prefix . '/' . htmlspecialchars($theme) . '/imgs/races/' . htmlspecialchars($icon) . '"
                     alt="' . $alt . '" title="' . $alt . '" class="' . $size . ' object-contain shrink-0">';
    }
}

/** Mappa $_SESSION['permessi'] → label leggibile. */
if (!function_exists('gdrcd_perm_label')) {
    function gdrcd_perm_label(int $level): string
    {
        global $PARAMETERS;
        switch ($level) {
            case SUPERUSER:      return $PARAMETERS['names']['administrator']['sing'] ?? 'Admin';
            case MODERATOR:      return $PARAMETERS['names']['moderators']['sing']    ?? 'Moderatore';
            case GAMEMASTER:     return $PARAMETERS['names']['master']['sing']        ?? 'Master';
            case GUILDMODERATOR: return $PARAMETERS['names']['guild_name']['lead']    ?? 'Capo gilda';
            default:             return '';
        }
    }
}

/* =====================================================================
 * Registry icone generiche (Heroicons 24x24 outline).
 *
 * Una sola fonte di verita' per tutti gli SVG ricorrenti nel codebase.
 * Sostituisce ~100 occorrenze di SVG inline.
 *
 * Uso:
 *   echo gdrcd_icon('alert-warning');                 // w-5 h-5 default
 *   echo gdrcd_icon('check', 'w-4 h-4');
 *   echo gdrcd_icon('search', 'w-4 h-4 text-gdrcd-accent');
 *   echo gdrcd_icon('plus', 'w-4 h-4', ['title' => 'Aggiungi']);
 *
 * Aggiungere un'icona: una entry nel ritorno di _gdrcd_icon_paths().
 * Il valore puo' essere stringa singola (un solo <path>) o array di stringhe
 * (path multipli concatenati).
 * ===================================================================== */

if (!function_exists('_gdrcd_icon_paths')) {
    /**
     * @return array<string, string|array<int,string>>
     */
    function _gdrcd_icon_paths(): array
    {
        static $paths = null;
        if ($paths !== null) return $paths;

        $paths = [
            // Feedback / alert
            'alert-warning' => 'M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z',
            'alert-info'    => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'check'         => 'M5 13l4 4L19 7',
            'check-circle'  => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'x'             => 'M6 18L18 6M6 6l12 12',
            'x-circle'      => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',

            // Actions
            'plus'          => 'M12 4v16m8-8H4',
            'minus'         => 'M20 12H4',
            'search'        => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
            'pencil'        => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'pencil-square' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
            'trash'         => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22m-9 0V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3',
            'save'          => 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2',
            'send'          => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
            'printer'       => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
            'refresh'       => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
            'upload'        => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12',
            'download'      => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
            'filter'        => 'M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z',
            'cog'           => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
            'cog-dot'       => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z',

            // Navigation
            'arrow-left'    => 'M10 19l-7-7m0 0l7-7m-7 7h18',
            'arrow-right'   => 'M14 5l7 7-7 7M3 12h18',
            'arrow-up'      => 'M5 10l7-7m0 0l7 7m-7-7v18',
            'arrow-down'    => 'M19 14l-7 7m0 0l-7-7m7 7V3',
            'chevron-left'  => 'M15 19l-7-7 7-7',
            'chevron-right' => 'M9 5l7 7-7 7',
            'home'          => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',

            // People
            'user'          => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            'users'         => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-3a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z',
            'user-group'    => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87a4 4 0 100-8 4 4 0 000 8zm0-8a4 4 0 100-8 4 4 0 000 8z',
            'user-circle'   => 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z',

            // Misc
            'mail'          => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'chat-bubble'   => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'clock'         => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'calendar'      => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'eye'           => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
            'eye-off'       => 'M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88',
            'shield'        => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            'bolt'          => 'M13 10V3L4 14h7v7l9-11h-7z',
            'star'          => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.715 5.276a1 1 0 00.951.69h5.547c.969 0 1.371 1.24.588 1.81l-4.488 3.26a1 1 0 00-.364 1.118l1.716 5.276c.3.921-.755 1.688-1.539 1.118l-4.488-3.26a1 1 0 00-1.176 0l-4.488 3.26c-.783.57-1.838-.197-1.538-1.118l1.715-5.276a1 1 0 00-.364-1.118l-4.488-3.26c-.783-.57-.38-1.81.588-1.81h5.547a1 1 0 00.95-.69l1.716-5.276z',
            'sparkles'      => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
            'bell'          => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0',
            'lock'          => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
            'key'           => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
            'ban'           => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636',
            'book'          => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
            'map'           => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m-6 3l6-3',
            'tag'           => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
            'cart'          => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            'chart-bar'     => 'M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01',
            'document'      => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'menu'          => 'M4 6h16M4 12h16M4 18h16',

            // Game-themed
            'flag'          => 'M3 21v-4a4 4 0 014-4h10a4 4 0 014 4v4M16 7a4 4 0 11-8 0 4 4 0 018 0z',
            'puzzle'        => 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
            'beaker'        => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
            'compass'       => 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243A3 3 0 009.12 14.88z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ];
        return $paths;
    }
}

/**
 * Renderizza un'icona SVG generica (Heroicons 24x24 outline).
 *
 * @param string $name Nome registrato in _gdrcd_icon_paths().
 * @param string $cls  Classi Tailwind (default w-5 h-5).
 * @param array{title?:string,aria?:string,stroke_width?:int} $opts
 */
if (!function_exists('gdrcd_icon')) {
    function gdrcd_icon(string $name, string $cls = 'w-5 h-5', array $opts = []): string
    {
        $paths = _gdrcd_icon_paths();
        if (!isset($paths[$name])) {
            // Fallback silenzioso: ritorna un quadrato vuoto delle stesse dimensioni
            // per non rompere il layout. Logghiamo solo in console-style commento.
            return '<svg class="' . htmlspecialchars($cls) . '" viewBox="0 0 24 24" fill="none" aria-hidden="true"></svg>';
        }
        $stroke = $opts['stroke_width'] ?? 2;
        $title  = $opts['title'] ?? '';
        $aria   = $opts['aria']  ?? $title;

        $ariaAttr = $aria !== ''
            ? ' role="img" aria-label="' . htmlspecialchars($aria) . '"'
            : ' aria-hidden="true"';
        $titleEl = $title !== '' ? '<title>' . htmlspecialchars($title) . '</title>' : '';

        $path = $paths[$name];
        $body = '';
        if (is_array($path)) {
            foreach ($path as $d) {
                $body .= '<path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/>';
            }
        } else {
            $body = '<path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '"/>';
        }

        return '<svg class="' . htmlspecialchars($cls) . '" fill="none" viewBox="0 0 24 24"'
             . ' stroke="currentColor" stroke-width="' . (int)$stroke . '"' . $ariaAttr . '>'
             . $titleEl . $body
             . '</svg>';
    }
}
