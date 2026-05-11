/**
 * GDRCD BBCode Editor
 *
 * Drop-in editor with toolbar + live preview for any
 *     <textarea data-bbcode>...</textarea>
 *
 * Mirrors (a useful subset of) the server-side gdrcd_bbcoder() rules
 * defined in includes/functions.inc.php so authors get a faithful
 * approximation of the final rendering while typing.
 *
 * No dependencies. Vanilla JS. Safe DOM operations only:
 * text segments go through textContent, attribute values are escaped
 * and URLs in [url]/[img] must start with http://, https:// or /.
 */
(function () {
    'use strict';

    /* -------------------------------------------------- *
     *  BBCode -> HTML (preview only, never persisted)    *
     * -------------------------------------------------- */

    var URL_RE = /^(https?:\/\/|\/)[^\s"'<>]*$/i;

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function safeUrl(u) {
        u = String(u || '').trim();
        return URL_RE.test(u) ? escapeHtml(u) : '#';
    }

    function safeColor(c) {
        // allow names + hex
        return /^(#[0-9a-f]{3,8}|[a-zA-Z]{3,20})$/.test(String(c).trim())
            ? c.trim()
            : 'inherit';
    }

    function safeSize(n) {
        var v = parseFloat(n);
        if (!isFinite(v) || v <= 0) return '1';
        // clamp to a sensible range (rem)
        if (v < 0.6) v = 0.6;
        if (v > 3) v = 3;
        return v.toFixed(2);
    }

    /**
     * Convert a BBCode string to safe HTML for the preview pane.
     * Approach: escape everything first, then turn whitelisted bbcode
     * tokens back into HTML. This keeps user-injected HTML inert.
     */
    function bbToHtml(src) {
        var html = escapeHtml(src);

        // Newlines
        html = html.replace(/\r\n|\r|\n/g, '<br>');
        html = html.replace(/\[BR\]/gi, '<br>');

        // Inline formatting (run repeatedly until no more matches to support nesting)
        var rules = [
            [/\[b\]([\s\S]+?)\[\/b\]/gi, '<strong>$1</strong>'],
            [/\[i\]([\s\S]+?)\[\/i\]/gi, '<em>$1</em>'],
            [/\[u\]([\s\S]+?)\[\/u\]/gi, '<u>$1</u>'],
            [/\[center\]([\s\S]+?)\[\/center\]/gi, '<div style="text-align:center">$1</div>'],
            [/\[quote(?:=(?:&quot;|&#39;)?([^\]"']*)(?:&quot;|&#39;)?)?\]([\s\S]+?)\[\/quote\]/gi,
                function (_, who, body) {
                    var head = who
                        ? '<div class="bb-quote-name">' + who + ' ha scritto:</div>'
                        : '';
                    return '<div class="bb-quote">' + head +
                        '<blockquote class="bb-quote-body">' + body +
                        '</blockquote></div>';
                }],
            [/\[spoiler\]([\s\S]+?)\[\/spoiler\]/gi,
                '<span class="bbcode-spoiler" tabindex="0">$1</span>'],
            [/\[list\]([\s\S]+?)\[\/list\]/gi,
                function (_, body) {
                    var items = body.split(/<br>|\[\*\]/i)
                        .map(function (x) { return x.trim(); })
                        .filter(Boolean)
                        .map(function (x) { return '<li>' + x + '</li>'; })
                        .join('');
                    return '<ul class="bb-list">' + items + '</ul>';
                }],
            [/\[color=([^\]]+)\]([\s\S]+?)\[\/color\]/gi,
                function (_, c, body) {
                    return '<span style="color:' + safeColor(c) + '">' + body + '</span>';
                }],
            [/\[size=([^\]]+)\]([\s\S]+?)\[\/size\]/gi,
                function (_, n, body) {
                    return '<span style="font-size:' + safeSize(n) + 'rem">' + body + '</span>';
                }],
            [/\[url=([^\]]+)\]([\s\S]+?)\[\/url\]/gi,
                function (_, href, body) {
                    return '<a href="' + safeUrl(href) +
                        '" target="_blank" rel="noopener noreferrer">' + body + '</a>';
                }],
            [/\[url\]([\s\S]+?)\[\/url\]/gi,
                function (_, href) {
                    var u = safeUrl(href);
                    return '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + u + '</a>';
                }],
            [/\[img\]([\s\S]+?)\[\/img\]/gi,
                function (_, src) {
                    return '<img src="' + safeUrl(src) + '" alt="" class="bb-img">';
                }]
        ];

        // Iterate a few passes for nested tags
        var pass = 0, changed = true;
        while (changed && pass < 6) {
            changed = false;
            for (var i = 0; i < rules.length; i++) {
                var next = html.replace(rules[i][0], rules[i][1]);
                if (next !== html) { changed = true; html = next; }
            }
            pass++;
        }
        return html;
    }

    /* -------------------------------------------------- *
     *  Toolbar / insertion                               *
     * -------------------------------------------------- */

    function wrapSelection(ta, before, after, placeholder) {
        ta.focus();
        var start = ta.selectionStart, end = ta.selectionEnd;
        var sel = ta.value.substring(start, end) || placeholder || '';
        var out = ta.value.substring(0, start) + before + sel + after + ta.value.substring(end);
        ta.value = out;
        var caret = start + before.length + sel.length;
        ta.setSelectionRange(caret, caret);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    var TOOLBAR = [
        { label: 'B',       title: 'Grassetto',  action: function (ta) { wrapSelection(ta, '[b]', '[/b]', 'testo'); } },
        { label: 'I',       title: 'Corsivo',    action: function (ta) { wrapSelection(ta, '[i]', '[/i]', 'testo'); } },
        { label: 'U',       title: 'Sottolinea', action: function (ta) { wrapSelection(ta, '[u]', '[/u]', 'testo'); } },
        { label: 'Link',    title: 'Inserisci link', action: function (ta) {
            var url = prompt('URL (http://, https:// o /percorso):', 'https://');
            if (!url) return;
            wrapSelection(ta, '[url=' + url + ']', '[/url]', 'descrizione');
        }},
        { label: 'Img',     title: 'Immagine', action: function (ta) {
            var url = prompt('URL immagine:', 'https://');
            if (!url) return;
            wrapSelection(ta, '[img]' + url + '[/img]', '', '');
        }},
        { label: 'Quote',   title: 'Citazione',  action: function (ta) { wrapSelection(ta, '[quote]', '[/quote]', 'testo citato'); } },
        { label: 'Color',   title: 'Colore',     action: function (ta) {
            var c = prompt('Colore (nome o esadecimale, es. red oppure #336699):', '#336699');
            if (!c) return;
            wrapSelection(ta, '[color=' + c + ']', '[/color]', 'testo');
        }},
        { label: 'Size',    title: 'Dimensione (rem)', action: function (ta) {
            var s = prompt('Dimensione testo in rem (0.6 - 3):', '1.2');
            if (!s) return;
            wrapSelection(ta, '[size=' + s + ']', '[/size]', 'testo');
        }},
        { label: 'List',    title: 'Elenco', action: function (ta) {
            wrapSelection(ta, '[list]\n[*]', '\n[*]\n[/list]', 'voce');
        }},
        { label: 'Spoiler', title: 'Spoiler', action: function (ta) { wrapSelection(ta, '[spoiler]', '[/spoiler]', 'testo nascosto'); } }
    ];

    /* -------------------------------------------------- *
     *  Editor wiring                                     *
     * -------------------------------------------------- */

    function buildEditor(ta) {
        if (ta.dataset.bbcodeReady === '1') return;
        ta.dataset.bbcodeReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'gdrcd-bbcode-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'gdrcd-bbcode-toolbar';
        toolbar.setAttribute('role', 'toolbar');
        TOOLBAR.forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'gdrcd-bbcode-btn';
            b.title = t.title;
            b.textContent = t.label;
            b.addEventListener('click', function () { t.action(ta); });
            toolbar.appendChild(b);
        });

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'gdrcd-bbcode-btn gdrcd-bbcode-toggle';
        toggle.title = 'Mostra/Nascondi anteprima';
        toggle.textContent = 'Anteprima';
        toolbar.appendChild(toggle);

        var panes = document.createElement('div');
        panes.className = 'gdrcd-bbcode-panes';

        var taSlot = document.createElement('div');
        taSlot.className = 'gdrcd-bbcode-input';

        var preview = document.createElement('div');
        preview.className = 'gdrcd-bbcode-preview';
        preview.setAttribute('aria-live', 'polite');

        // Insert wrap, then move ta inside
        ta.parentNode.insertBefore(wrap, ta);
        taSlot.appendChild(ta);
        panes.appendChild(taSlot);
        panes.appendChild(preview);
        wrap.appendChild(toolbar);
        wrap.appendChild(panes);

        function render() {
            // bbToHtml returns sanitized HTML, safe to inject
            preview.innerHTML = bbToHtml(ta.value);
        }

        // Toggle preview pane (default hidden on small screens, visible on lg)
        function applyMode(forceOn) {
            var on = typeof forceOn === 'boolean'
                ? forceOn
                : !wrap.classList.contains('is-preview-on');
            wrap.classList.toggle('is-preview-on', on);
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) render();
        }
        toggle.addEventListener('click', function () { applyMode(); });

        ta.addEventListener('input', function () {
            if (wrap.classList.contains('is-preview-on')) render();
        });

        // Spoiler reveal in preview
        preview.addEventListener('click', function (e) {
            var t = e.target;
            if (t && t.classList && t.classList.contains('bbcode-spoiler')) {
                t.classList.toggle('is-revealed');
            }
        });

        // Default: on lg screens show side-by-side
        if (window.matchMedia('(min-width: 1024px)').matches) {
            applyMode(true);
        }
    }

    function init() {
        var nodes = document.querySelectorAll('textarea[data-bbcode]');
        for (var i = 0; i < nodes.length; i++) buildEditor(nodes[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
