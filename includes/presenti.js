/**
 * GDRCD — Widget "presenti" (sidebar).
 *
 * Sostituisce il vecchio iframe auto-refresh (pages/presenti.inc.php in
 * pages/frame_presenti.inc.php) con un polling fetch dell'endpoint JSON
 * /api/presenti.inc.php. Tutto il rendering avviene client side via DOM
 * manipulation: niente reload CSS, niente flicker.
 *
 * - Polling: ogni POLL_INTERVAL_MS (30s).
 * - Errori di rete: log silenzioso e ritenta al giro successivo, lascia
 *   visibile l'ultimo snapshot valido (no blanking).
 * - HTTP 401: la sessione e' scaduta, redirect a index.php.
 * - Sicurezza: testo da server inserito sempre via textContent / attributi
 *   safe. innerHTML solo per markup SVG statico definito qui.
 *
 * @see api/presenti.inc.php
 * @see pages/frame_presenti.inc.php
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    var POLL_INTERVAL_MS = 30000;
    var CONTAINER_ID = 'gdrcd-presenti-list';

    // --- SVG icons (minimal set) ---------------------------------------
    // Pallino disponibilita' (0 verde, 1 giallo, 2 rosso).
    var DISP_COLORS = ['bg-green-500', 'bg-yellow-500', 'bg-red-500'];
    var DISP_LABELS = ['Disponibile', 'Assente', 'Non disponibile'];

    // Marker permessi: piccola pastiglia colorata in base al ruolo.
    // I valori numerici riflettono le costanti PHP (SUPERUSER, MODERATOR,
    // GAMEMASTER, GUILDMODERATOR). Usiamo soglie >= per essere tolleranti
    // a installazioni che ridefiniscono le costanti.
    function permClass(level) {
        if (level >= 80) return 'text-purple-600'; // SUPERUSER
        if (level >= 60) return 'text-red-600';    // MODERATOR
        if (level >= 40) return 'text-gdrcd-accent'; // GAMEMASTER
        if (level >= 20) return 'text-amber-500';  // GUILDMODERATOR
        return '';
    }
    function permLabel(level) {
        if (level >= 80) return 'Admin';
        if (level >= 60) return 'Moderatore';
        if (level >= 40) return 'Master';
        if (level >= 20) return 'Capo gilda';
        return '';
    }

    function genderClass(sex) {
        if (sex === 'm') return 'text-sky-600';
        if (sex === 'f') return 'text-pink-600';
        return '';
    }
    function genderSymbol(sex) {
        if (sex === 'm') return '♂'; // ♂
        if (sex === 'f') return '♀'; // ♀
        return '';
    }

    // --- DOM helpers ---------------------------------------------------
    function el(tag, opts) {
        var n = document.createElement(tag);
        if (!opts) return n;
        if (opts.cls) n.className = opts.cls;
        if (opts.text !== undefined && opts.text !== null) n.textContent = String(opts.text);
        if (opts.title) n.setAttribute('title', String(opts.title));
        if (opts.aria) n.setAttribute('aria-label', String(opts.aria));
        if (opts.href) n.setAttribute('href', String(opts.href));
        if (opts.target) n.setAttribute('target', String(opts.target));
        return n;
    }

    function clear(node) {
        while (node.firstChild) node.removeChild(node.firstChild);
    }

    // --- Renderers -----------------------------------------------------
    function renderSectionTitle(text) {
        return el('div', {
            cls: 'text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display px-2 pt-2 pb-1 border-b border-gdrcd-border',
            text: text
        });
    }

    function renderSectionTitleLink(text, href) {
        var wrap = el('div', {
            cls: 'text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display px-2 pt-2 pb-1 border-b border-gdrcd-border'
        });
        var a = el('a', {
            cls: 'hover:text-gdrcd-accent transition',
            href: href,
            target: '_top',
            text: text
        });
        wrap.appendChild(a);
        return wrap;
    }

    function renderDispDot(state, label) {
        var s = (typeof state === 'number') ? state : 0;
        var color = DISP_COLORS[s] || 'bg-gray-400';
        var lbl = label || DISP_LABELS[s] || '';
        var dot = el('span', { cls: 'inline-block w-2.5 h-2.5 rounded-full shrink-0 ' + color });
        if (lbl) {
            dot.setAttribute('title', lbl);
            dot.setAttribute('aria-label', lbl);
        }
        return dot;
    }

    function renderPermBadge(level) {
        var lbl = permLabel(level);
        if (!lbl) return null;
        var cls = permClass(level);
        var badge = el('span', {
            cls: 'inline-block w-1.5 h-1.5 rounded-full shrink-0 ' + (cls ? cls.replace('text-', 'bg-') : 'bg-gray-400'),
            title: lbl,
            aria: lbl
        });
        return badge;
    }

    function renderGenderBadge(sex) {
        var sym = genderSymbol(sex);
        if (!sym) return null;
        return el('span', {
            cls: 'shrink-0 text-xs ' + genderClass(sex),
            text: sym,
            aria: sex === 'm' ? 'Maschio' : (sex === 'f' ? 'Femmina' : '')
        });
    }

    function renderPgRow(pg) {
        var row = el('div', { cls: 'flex items-center gap-1.5 px-2 py-1 text-xs hover:bg-gdrcd-accent-soft/40 rounded transition' });

        var perm = renderPermBadge(pg.permessi);
        if (perm) row.appendChild(perm);

        row.appendChild(renderDispDot(pg.disponibile, ''));

        var gender = renderGenderBadge(pg.sesso);
        if (gender) row.appendChild(gender);

        // Link a scheda PG.
        var name = pg.nome || '';
        var link = el('a', {
            cls: 'flex-1 min-w-0 truncate text-gdrcd-text hover:text-gdrcd-accent transition gender_' + (pg.sesso || ''),
            href: 'main.php?page=scheda&pg=' + encodeURIComponent(name),
            target: '_top',
            text: name
        });
        if (pg.is_me) {
            link.classList.add('font-semibold');
        }
        row.appendChild(link);

        // Pulsante MP rapido (solo se non sono io).
        if (!pg.is_me && name) {
            var mp = el('a', {
                cls: 'shrink-0 text-gdrcd-text-soft hover:text-gdrcd-accent transition',
                href: 'main.php?page=messages_new&to=' + encodeURIComponent(name),
                target: '_top',
                title: 'Invia MP a ' + name,
                aria: 'Invia messaggio privato a ' + name
            });
            // Inline SVG: paper-plane.
            mp.innerHTML = '<svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.105 2.288a.75.75 0 00-.826.95l1.414 4.926A.75.75 0 004.42 8.69h6.83a.75.75 0 010 1.5H4.42a.75.75 0 00-.728.546L2.28 15.66a.75.75 0 00.95.948 28.97 28.97 0 0014.347-9.255.75.75 0 000-.726A28.97 28.97 0 003.105 2.287z"/></svg>';
            row.appendChild(mp);
        }

        if (pg.just_entered) {
            var arrow = el('span', {
                cls: 'shrink-0 text-green-600 text-xs',
                title: 'Appena entrato',
                aria: 'Appena entrato',
                text: '←'
            });
            row.appendChild(arrow);
        }

        return row;
    }

    function renderGroup(group) {
        var frag = document.createDocumentFragment();
        var label = group.luogo || group.mappa || '';
        if (group.luogo_link) {
            frag.appendChild(renderSectionTitleLink(label, group.luogo_link));
        } else {
            frag.appendChild(renderSectionTitle(label));
        }
        var pgs = group.pgs || [];
        for (var i = 0; i < pgs.length; i++) {
            frag.appendChild(renderPgRow(pgs[i]));
        }
        return frag;
    }

    function renderFooter(total, extendedUrl) {
        var wrap = el('div', { cls: 'mt-3 pt-2 border-t border-gdrcd-border' });
        var a = el('a', {
            cls: 'block px-2 py-1.5 rounded text-center text-sm font-display text-gdrcd-accent hover:bg-gdrcd-accent-soft transition',
            href: extendedUrl,
            target: '_top'
        });
        var strong = el('strong', { cls: 'tabular-nums', text: String(total) });
        a.appendChild(strong);
        a.appendChild(document.createTextNode(' ' + (total === 1 ? 'Presente' : 'Presenti')));
        wrap.appendChild(a);
        return wrap;
    }

    function renderEmpty() {
        return el('div', {
            cls: 'text-xs text-gdrcd-muted px-2 py-2 text-center',
            text: 'Nessun personaggio online.'
        });
    }

    function render(container, data) {
        var extendedUrl = container.getAttribute('data-extended-url') || 'main.php?page=presenti_estesi';
        var groups = (data && Array.isArray(data.groups)) ? data.groups : [];
        var total = (data && typeof data.total === 'number') ? data.total : 0;

        var next = document.createDocumentFragment();
        if (groups.length === 0) {
            next.appendChild(renderEmpty());
        } else {
            for (var i = 0; i < groups.length; i++) {
                next.appendChild(renderGroup(groups[i]));
            }
        }
        next.appendChild(renderFooter(total, extendedUrl));

        // Swap atomico: prima costruito off-DOM, poi sostituito in un colpo
        // solo -> nessun flicker visibile.
        clear(container);
        container.appendChild(next);
    }

    // --- is_me normalization -------------------------------------------
    // Il payload WS non setta is_me (calcolato qui dal data-me del container);
    // l'endpoint HTTP fallback lo setta lato server. Allineiamo sempre.
    function applyIsMe(container, data) {
        var me = container.getAttribute('data-me') || '';
        if (!me || !data || !Array.isArray(data.groups)) return data;
        for (var i = 0; i < data.groups.length; i++) {
            var pgs = data.groups[i].pgs || [];
            for (var j = 0; j < pgs.length; j++) {
                pgs[j].is_me = (pgs[j].nome === me);
            }
        }
        return data;
    }

    // --- Polling -------------------------------------------------------
    var firstLoadDone = false;
    var pollTimer = null;
    var wsActive = false;

    function fetchAndRender(container) {
        if (wsActive) return;
        var url = container.getAttribute('data-poll-url');
        if (!url) return;

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function (res) {
            if (res.status === 401) {
                var loginUrl = container.getAttribute('data-login-url') || 'index.php';
                window.location.href = loginUrl;
                return null;
            }
            if (!res.ok) return null;
            return res.json();
        }).then(function (data) {
            if (!data) return;
            render(container, applyIsMe(container, data));
            firstLoadDone = true;
        }).catch(function () { /* retry next tick */ });
    }

    function startPolling(container) {
        if (pollTimer !== null) return;
        fetchAndRender(container);
        pollTimer = setInterval(function () { fetchAndRender(container); }, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // --- WebSocket via singleton ---------------------------------------
    function subscribeWs(container) {
        if (!window.GDRCDSocket) return false;
        window.GDRCDSocket.subscribe('presenti', {}, function (payload) {
            if (payload.type === 'error' && payload.error === 'unauthenticated') {
                wsActive = false;
                startPolling(container);
                return;
            }
            if (payload.type !== 'presenti') return;
            wsActive = true;
            stopPolling();
            render(container, applyIsMe(container, payload));
            firstLoadDone = true;
        });
        window.GDRCDSocket.onStateChange(function (state) {
            if (state === 'open') {
                wsActive = true;
                stopPolling();
            } else if (state === 'close' || state === 'auth-fail') {
                wsActive = false;
                startPolling(container);
            }
        });
        return true;
    }

    // --- Bootstrap -----------------------------------------------------
    function bootstrap() {
        var container = document.getElementById(CONTAINER_ID);
        if (!container) return;

        if (!subscribeWs(container)) {
            startPolling(container);
        } else {
            setTimeout(function () {
                if (!wsActive) startPolling(container);
            }, 3000);
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && firstLoadDone && !wsActive) {
                fetchAndRender(container);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
