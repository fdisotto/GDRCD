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
