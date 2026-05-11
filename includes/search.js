/**
 * GDRCD — Ricerca globale dalla topbar.
 *
 * Collega il form #gdrcd-global-search all'endpoint JSON
 * /api/search.inc.php e renderizza i risultati raggruppati per tipo
 * (pg, oggetti, gilde, ambientazione, regolamento) nel dropdown
 * #gdrcd-global-search-results.
 *
 * - Debounce 300ms su input.
 * - Query < 2 char: dropdown nascosto.
 * - Tasti: ArrowUp/ArrowDown navigano i link, Enter segue il link
 *   evidenziato, Escape chiude il dropdown.
 * - Click esterno: chiude il dropdown.
 * - HTTP 401: redirect a index.php (sessione scaduta).
 *
 * Sicurezza: tutto il testo proveniente dal server entra nel DOM via
 * textContent. L'evidenziazione del match avviene costruendo nodi
 * <mark> separati (mai innerHTML su stringhe server).
 *
 * @see api/search.inc.php
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    var DEBOUNCE_MS = 300;
    var MIN_CHARS   = 2;
    var ENDPOINT    = '/api/search.inc.php';

    // Etichette sezioni nel dropdown.
    var SECTION_LABELS = {
        pg:            'Personaggi',
        oggetti:       'Oggetti',
        gilde:         'Gilde',
        ambientazione: 'Ambientazione',
        regolamento:   'Regolamento'
    };
    // Ordine di rendering delle sezioni.
    var SECTION_ORDER = ['pg', 'oggetti', 'gilde', 'ambientazione', 'regolamento'];

    // --- DOM helpers ---------------------------------------------------
    function el(tag, opts) {
        var n = document.createElement(tag);
        if (!opts) return n;
        if (opts.cls)  n.className = opts.cls;
        if (opts.text !== undefined && opts.text !== null) n.textContent = String(opts.text);
        if (opts.href) n.setAttribute('href', String(opts.href));
        if (opts.role) n.setAttribute('role', String(opts.role));
        return n;
    }

    function clear(node) {
        while (node.firstChild) node.removeChild(node.firstChild);
    }

    /**
     * Costruisce un set di nodi (Text/<mark>) che rappresentano `text`
     * con tutte le occorrenze (case-insensitive) di `q` evidenziate.
     * Restituisce un DocumentFragment.
     */
    function highlight(text, q) {
        var frag = document.createDocumentFragment();
        var str = String(text == null ? '' : text);
        if (!q) {
            frag.appendChild(document.createTextNode(str));
            return frag;
        }
        var lcStr = str.toLowerCase();
        var lcQ   = q.toLowerCase();
        var qLen  = lcQ.length;
        var i = 0;
        while (i < str.length) {
            var idx = lcStr.indexOf(lcQ, i);
            if (idx === -1) {
                frag.appendChild(document.createTextNode(str.slice(i)));
                break;
            }
            if (idx > i) {
                frag.appendChild(document.createTextNode(str.slice(i, idx)));
            }
            var mark = document.createElement('mark');
            mark.className = 'bg-gdrcd-accent-soft text-gdrcd-accent rounded px-0.5';
            mark.appendChild(document.createTextNode(str.slice(idx, idx + qLen)));
            frag.appendChild(mark);
            i = idx + qLen;
        }
        return frag;
    }

    // --- Renderers -----------------------------------------------------

    function renderSectionTitle(text) {
        return el('div', {
            cls: 'text-[10px] uppercase tracking-wide text-gdrcd-text-soft font-display px-3 pt-2 pb-1 border-b border-gdrcd-border'
        });
    }

    function renderResultLink(label, url, q) {
        var a = el('a', {
            cls: 'gdrcd-search-item block px-3 py-1.5 text-sm text-gdrcd-text hover:bg-gdrcd-accent-soft hover:text-gdrcd-accent transition truncate',
            href: url,
            role: 'option'
        });
        a.appendChild(highlight(label, q));
        return a;
    }

    function renderEmpty(q) {
        var div = el('div', {
            cls: 'px-3 py-3 text-sm text-gdrcd-text-soft'
        });
        div.appendChild(document.createTextNode('Nessun risultato per “'));
        var strong = el('span', { cls: 'text-gdrcd-text font-medium', text: q });
        div.appendChild(strong);
        div.appendChild(document.createTextNode('”.'));
        return div;
    }

    function labelForItem(section, item) {
        switch (section) {
            case 'pg': {
                var nome = String(item.nome || '');
                var cogn = String(item.cognome || '');
                return (cogn && cogn !== '-') ? (nome + ' ' + cogn) : nome;
            }
            case 'oggetti':
            case 'gilde':
                return String(item.nome || '');
            case 'ambientazione':
                return 'Cap. ' + (item.capitolo != null ? item.capitolo : '?') + ' — ' + String(item.titolo || '');
            case 'regolamento':
                return 'Art. ' + (item.articolo != null ? item.articolo : '?') + ' — ' + String(item.titolo || '');
            default:
                return '';
        }
    }

    // --- Component -----------------------------------------------------

    function init() {
        var form    = document.getElementById('gdrcd-global-search');
        var input   = document.getElementById('gdrcd-global-search-input');
        var dropdown= document.getElementById('gdrcd-global-search-results');
        if (!form || !input || !dropdown) return;

        var debounceTimer = null;
        var lastQuery = '';
        var currentReq = 0;
        var highlightedIndex = -1;

        function items() {
            return dropdown.querySelectorAll('.gdrcd-search-item');
        }

        function updateHighlight() {
            var nodes = items();
            for (var i = 0; i < nodes.length; i++) {
                if (i === highlightedIndex) {
                    nodes[i].classList.add('bg-gdrcd-accent-soft', 'text-gdrcd-accent');
                    nodes[i].setAttribute('aria-selected', 'true');
                } else {
                    nodes[i].classList.remove('bg-gdrcd-accent-soft', 'text-gdrcd-accent');
                    nodes[i].removeAttribute('aria-selected');
                }
            }
        }

        function open() {
            dropdown.classList.remove('hidden');
            dropdown.setAttribute('role', 'listbox');
        }

        function close() {
            dropdown.classList.add('hidden');
            highlightedIndex = -1;
        }

        function render(query, data) {
            clear(dropdown);
            highlightedIndex = -1;

            var results = (data && data.results) || {};
            var total   = (data && typeof data.total === 'number') ? data.total : 0;

            if (total === 0) {
                dropdown.appendChild(renderEmpty(query));
                open();
                return;
            }

            for (var si = 0; si < SECTION_ORDER.length; si++) {
                var section = SECTION_ORDER[si];
                var list = results[section];
                if (!list || !list.length) continue;

                var title = renderSectionTitle();
                title.textContent = SECTION_LABELS[section] || section;
                dropdown.appendChild(title);

                for (var i = 0; i < list.length; i++) {
                    var item = list[i];
                    var label = labelForItem(section, item);
                    var url   = String(item.url || '#');
                    dropdown.appendChild(renderResultLink(label, url, query));
                }
            }
            open();
        }

        function renderError(msg) {
            clear(dropdown);
            var div = el('div', {
                cls: 'px-3 py-3 text-sm text-red-600',
                text: msg
            });
            dropdown.appendChild(div);
            open();
        }

        function fetchResults(query) {
            var reqId = ++currentReq;
            var url = ENDPOINT + '?q=' + encodeURIComponent(query);
            fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (resp) {
                if (resp.status === 401) {
                    window.location.href = 'index.php';
                    return null;
                }
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                return resp.json();
            }).then(function (data) {
                if (data == null) return;
                if (reqId !== currentReq) return; // risposta vecchia
                render(query, data);
            }).catch(function (err) {
                if (reqId !== currentReq) return;
                renderError('Errore nella ricerca.');
                if (window.console && console.warn) console.warn('[gdrcd-search]', err);
            });
        }

        function onInput() {
            var q = input.value.trim();
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }
            if (q.length < MIN_CHARS) {
                lastQuery = '';
                close();
                clear(dropdown);
                return;
            }
            if (q === lastQuery) return;
            lastQuery = q;
            debounceTimer = setTimeout(function () {
                fetchResults(q);
            }, DEBOUNCE_MS);
        }

        function onKeyDown(ev) {
            var key = ev.key;
            if (key === 'Escape') {
                close();
                input.blur();
                return;
            }
            var nodes = items();
            if (!nodes.length) return;

            if (key === 'ArrowDown') {
                ev.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % nodes.length;
                updateHighlight();
                nodes[highlightedIndex].scrollIntoView({ block: 'nearest' });
            } else if (key === 'ArrowUp') {
                ev.preventDefault();
                highlightedIndex = (highlightedIndex - 1 + nodes.length) % nodes.length;
                updateHighlight();
                nodes[highlightedIndex].scrollIntoView({ block: 'nearest' });
            } else if (key === 'Enter') {
                if (highlightedIndex >= 0 && nodes[highlightedIndex]) {
                    ev.preventDefault();
                    var href = nodes[highlightedIndex].getAttribute('href');
                    if (href) window.location.href = href;
                }
            }
        }

        function onSubmit(ev) {
            ev.preventDefault();
            var q = input.value.trim();
            if (q.length < MIN_CHARS) return;
            // Forza un fetch immediato (ignora debounce).
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }
            lastQuery = q;
            fetchResults(q);
        }

        function onDocClick(ev) {
            if (form.contains(ev.target)) return;
            close();
        }

        function onFocus() {
            if (dropdown.firstChild && input.value.trim().length >= MIN_CHARS) {
                open();
            }
        }

        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeyDown);
        input.addEventListener('focus', onFocus);
        form.addEventListener('submit', onSubmit);
        document.addEventListener('click', onDocClick);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
