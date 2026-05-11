/**
 * GDRCD — Luoghi preferiti (client-side, localStorage only).
 *
 * Tailwind content scanner hint (literal class names referenced from JS):
 *   gdrcd-favorite-toggle is-favorite
 *
 * Stores up to 5 chat locations the user marked as favorites.
 * Pure client-side: per-browser scope, no server/DB writes.
 *
 * Storage:
 *   key: 'gdrcd_favorites'
 *   shape: Array<{ id:number, nome:string, mappa:number }>
 *
 * Public API on window.GdrcdFavorites:
 *   list()
 *   add(id, nome, mappaClickId)
 *   remove(id)
 *   toggle(id, nome, mappaClickId)
 *   isFav(id)
 *   renderInto(container)
 *
 * Behavior:
 *   - On DOMContentLoaded: render pill bar into #gdrcd-favorites (if present);
 *     wire any [data-favorite-id] star toggle button (info_location widget).
 *   - Max 5 favorites; oldest entry dropped on overflow.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'gdrcd_favorites';
    var MAX_FAVORITES = 5;

    function safeStorage() {
        try {
            if (typeof window === 'undefined' || !window.localStorage) return null;
            // probe
            var t = '__gdrcd_fav_probe__';
            window.localStorage.setItem(t, '1');
            window.localStorage.removeItem(t);
            return window.localStorage;
        } catch (_) {
            return null;
        }
    }

    function read() {
        var ls = safeStorage();
        if (!ls) return [];
        try {
            var raw = ls.getItem(STORAGE_KEY);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) return [];
            // sanitize entries
            var out = [];
            for (var i = 0; i < parsed.length; i++) {
                var f = parsed[i];
                if (!f || typeof f !== 'object') continue;
                var id = parseInt(f.id, 10);
                if (!id || id <= 0) continue;
                var nome = typeof f.nome === 'string' ? f.nome : '';
                var mappa = parseInt(f.mappa, 10) || 0;
                out.push({ id: id, nome: nome, mappa: mappa });
            }
            return out;
        } catch (_) {
            return [];
        }
    }

    function write(list) {
        var ls = safeStorage();
        if (!ls) return false;
        try {
            ls.setItem(STORAGE_KEY, JSON.stringify(list));
            return true;
        } catch (_) {
            return false;
        }
    }

    function isFav(id) {
        id = parseInt(id, 10);
        var list = read();
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) return true;
        }
        return false;
    }

    function add(id, nome, mappaClickId) {
        id = parseInt(id, 10);
        if (!id || id <= 0) return false;
        mappaClickId = parseInt(mappaClickId, 10) || 0;
        nome = (nome == null) ? '' : String(nome);

        var list = read();
        // dedupe
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) {
                // refresh name/map if changed
                list[i].nome = nome || list[i].nome;
                list[i].mappa = mappaClickId || list[i].mappa;
                write(list);
                renderAll();
                return true;
            }
        }
        list.push({ id: id, nome: nome, mappa: mappaClickId });
        while (list.length > MAX_FAVORITES) list.shift();
        write(list);
        renderAll();
        return true;
    }

    function remove(id) {
        id = parseInt(id, 10);
        var list = read();
        var out = [];
        for (var i = 0; i < list.length; i++) {
            if (list[i].id !== id) out.push(list[i]);
        }
        write(out);
        renderAll();
        return true;
    }

    function toggle(id, nome, mappaClickId) {
        if (isFav(id)) {
            remove(id);
            return false;
        }
        add(id, nome, mappaClickId);
        return true;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildUrl(fav) {
        // chat rooms always have chat=1; URL pattern mirrors link_menu.inc.php
        var mappa = fav.mappa || 0;
        return 'main.php?dir=' + encodeURIComponent(fav.id) + '&map_id=' + encodeURIComponent(mappa);
    }

    function renderInto(container) {
        if (!container) return;
        var list = read();
        if (list.length === 0) {
            container.innerHTML = '';
            container.style.display = 'none';
            return;
        }
        container.style.display = '';
        var html = '<div class="text-[11px] uppercase tracking-wide text-gdrcd-text-soft font-display flex items-center gap-1 mb-1">' +
            '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">' +
            '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.784.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.05 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/>' +
            '</svg>' +
            'Preferiti' +
            '</div>' +
            '<div class="flex flex-wrap gap-1.5 mb-3">';
        for (var i = 0; i < list.length; i++) {
            var f = list[i];
            html += '<a href="' + escapeHtml(buildUrl(f)) + '" ' +
                'class="gdrcd-favorite-pill inline-flex items-center gap-1 px-2 py-1 rounded-full ' +
                'border border-gdrcd-border bg-gdrcd-panel-alt text-[11px] text-gdrcd-text ' +
                'hover:border-gdrcd-accent hover:bg-gdrcd-accent-soft transition" ' +
                'title="' + escapeHtml(f.nome) + '">' +
                '<svg class="w-3 h-3 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">' +
                '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.784.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.05 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/>' +
                '</svg>' +
                '<span class="truncate max-w-[7rem]">' + escapeHtml(f.nome || ('#' + f.id)) + '</span>' +
                '</a>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function renderAll() {
        var container = document.getElementById('gdrcd-favorites');
        if (container) renderInto(container);
        syncSelectOptions();
        syncStarButtons();
    }

    /**
     * Inject favorites as a <optgroup> at the top of #gotomap select,
     * so they show up as quick default options in "Vai a".
     */
    function syncSelectOptions() {
        var select = document.getElementById('gotomap');
        if (!select) return;
        // remove existing favorites optgroup if present
        var existing = select.querySelector('optgroup[data-favorites="1"]');
        if (existing) existing.parentNode.removeChild(existing);

        var list = read();
        if (list.length === 0) return;

        var og = document.createElement('optgroup');
        og.setAttribute('label', 'Preferiti');
        og.setAttribute('data-favorites', '1');
        for (var i = 0; i < list.length; i++) {
            var f = list[i];
            var opt = document.createElement('option');
            opt.value = buildUrl(f);
            opt.textContent = '★ ' + (f.nome || ('#' + f.id));
            og.appendChild(opt);
        }
        // insert as first child so favorites appear on top
        if (select.firstChild) {
            select.insertBefore(og, select.firstChild);
        } else {
            select.appendChild(og);
        }
    }

    function syncStarButtons() {
        var buttons = document.querySelectorAll('[data-favorite-id]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var id = parseInt(btn.getAttribute('data-favorite-id'), 10);
            if (!id) continue;
            var fav = isFav(id);
            btn.classList.toggle('is-favorite', fav);
            btn.setAttribute('aria-pressed', fav ? 'true' : 'false');
            btn.setAttribute('aria-label', fav ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti');
            btn.setAttribute('title', fav ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti');
        }
    }

    function wireStarButtons() {
        var buttons = document.querySelectorAll('[data-favorite-id]');
        for (var i = 0; i < buttons.length; i++) {
            (function (btn) {
                if (btn.__gdrcdFavBound) return;
                btn.__gdrcdFavBound = true;
                btn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var id = parseInt(btn.getAttribute('data-favorite-id'), 10);
                    var nome = btn.getAttribute('data-favorite-name') || '';
                    var mappa = parseInt(btn.getAttribute('data-favorite-map'), 10) || 0;
                    if (!id) return;
                    var nowFav = toggle(id, nome, mappa);
                    if (window.gdrcdToast) {
                        try {
                            window.gdrcdToast(
                                nowFav ? 'success' : 'info',
                                nowFav ? 'Aggiunto ai preferiti' : 'Rimosso dai preferiti'
                            );
                        } catch (_) { /* noop */ }
                    }
                });
            })(buttons[i]);
        }
    }

    // Public API
    window.GdrcdFavorites = {
        list: read,
        add: add,
        remove: remove,
        toggle: toggle,
        isFav: isFav,
        renderInto: renderInto
    };

    // Re-render across tabs when localStorage changes
    window.addEventListener('storage', function (ev) {
        if (ev && ev.key && ev.key !== STORAGE_KEY) return;
        renderAll();
    });

    function init() {
        renderAll();
        wireStarButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
