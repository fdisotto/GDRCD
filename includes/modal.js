/**
 * Finestra modale (vanilla JS, ex jQuery UI dialog)
 *
 * Mantiene la stessa API pubblica:
 *     modalWindow(name, title, url, width, height)
 *
 * Le modali vengono sempre create nel documento "top" in modo che chi le
 * apre da dentro un iframe (es. popup.php) finisca per mostrarle al di
 * sopra del layout principale, come accadeva con il vecchio top.$().dialog().
 *
 * @author Blancks (versione originale jQuery UI)
 * @author refactor jquery-removal
 */
(function (root) {
    'use strict';

    // Inietta una sola volta nel documento corrente i pochi stili necessari
    // per il backdrop, l'animazione e il drag della finestra.
    function ensureStyles(doc) {
        if (doc.getElementById('gdrcd-modal-styles')) {
            return;
        }
        var style = doc.createElement('style');
        style.id = 'gdrcd-modal-styles';
        style.textContent =
            '.gdrcd-modal-overlay{position:fixed;inset:0;background:rgba(15,15,15,.55);' +
            'display:flex;align-items:center;justify-content:center;z-index:9999;}' +
            '.gdrcd-modal-window{background:#ffffff;border:1px solid #cfc8b6;' +
            'border-radius:.75rem;box-shadow:0 10px 30px -5px rgba(0,0,0,.25),' +
            '0 8px 20px -8px rgba(0,0,0,.2);display:flex;flex-direction:column;' +
            'max-width:95vw;max-height:95vh;overflow:hidden;}' +
            '.gdrcd-modal-titlebar{display:flex;align-items:center;justify-content:space-between;' +
            'padding:.5rem .75rem;background:#f1ede3;border-bottom:1px solid #e5e0d4;' +
            'font-family:Cinzel,serif;font-weight:600;color:#1f2937;cursor:move;user-select:none;}' +
            '.gdrcd-modal-title{font-size:1rem;line-height:1.25rem;padding-right:.5rem;' +
            'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}' +
            '.gdrcd-modal-close{background:transparent;border:0;color:#374151;font-size:1.25rem;' +
            'line-height:1;cursor:pointer;padding:.25rem .5rem;border-radius:.25rem;}' +
            '.gdrcd-modal-close:hover{background:rgba(0,0,0,.06);}' +
            '.gdrcd-modal-body{flex:1 1 auto;position:relative;background:#ffffff;}' +
            '.gdrcd-modal-body iframe{position:absolute;inset:0;width:100%;height:100%;border:0;}';
        doc.head.appendChild(style);
    }

    // Costruisce un nuovo overlay+finestra e lo aggiunge al body del documento dato.
    function buildModal(doc, name, title, url, width, height) {
        ensureStyles(doc);

        var overlay = doc.createElement('div');
        overlay.className = 'gdrcd-modal-overlay';
        overlay.id = 'dialog-' + name;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var win = doc.createElement('div');
        win.className = 'gdrcd-modal-window';
        win.style.width = (typeof width === 'number' ? width + 'px' : width);
        win.style.height = (typeof height === 'number' ? height + 'px' : height);

        var titlebar = doc.createElement('div');
        titlebar.className = 'gdrcd-modal-titlebar';

        var titleEl = doc.createElement('span');
        titleEl.className = 'gdrcd-modal-title';
        titleEl.textContent = title || '';

        var closeBtn = doc.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'gdrcd-modal-close';
        closeBtn.setAttribute('aria-label', 'Chiudi');
        closeBtn.innerHTML = '&times;';

        titlebar.appendChild(titleEl);
        titlebar.appendChild(closeBtn);

        var body = doc.createElement('div');
        body.className = 'gdrcd-modal-body';

        var iframe = doc.createElement('iframe');
        iframe.src = url;
        iframe.setAttribute('scrolling', 'yes');
        body.appendChild(iframe);

        win.appendChild(titlebar);
        win.appendChild(body);
        overlay.appendChild(win);
        doc.body.appendChild(overlay);

        function closeModal() {
            // Liberiamo l'iframe per fermare eventuali timer/audio della pagina ospitata
            // (stesso comportamento del callback close della vecchia dialog).
            iframe.src = '';
            overlay.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeModal);

        // Click sul backdrop (fuori dalla finestra) = chiusura, come jQuery UI modal.
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === overlay) {
                closeModal();
            }
        });

        // Tasto ESC = chiusura.
        doc.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && overlay.style.display !== 'none') {
                closeModal();
            }
        });

        // Drag della finestra tramite la title bar (sostituto del draggable di jQuery UI).
        (function makeDraggable() {
            var dragging = false;
            var startX = 0, startY = 0;
            var offsetX = 0, offsetY = 0;

            titlebar.addEventListener('mousedown', function (e) {
                if (e.target === closeBtn) {
                    return;
                }
                dragging = true;
                var rect = win.getBoundingClientRect();
                offsetX = rect.left;
                offsetY = rect.top;
                startX = e.clientX;
                startY = e.clientY;
                // Passiamo da flex-centered ad assoluto per poter posizionare a piacere.
                overlay.style.alignItems = 'flex-start';
                overlay.style.justifyContent = 'flex-start';
                win.style.position = 'absolute';
                win.style.left = offsetX + 'px';
                win.style.top = offsetY + 'px';
                e.preventDefault();
            });

            doc.addEventListener('mousemove', function (e) {
                if (!dragging) {
                    return;
                }
                var nx = offsetX + (e.clientX - startX);
                var ny = offsetY + (e.clientY - startY);
                win.style.left = nx + 'px';
                win.style.top = ny + 'px';
            });

            doc.addEventListener('mouseup', function () {
                dragging = false;
            });
        })();

        return {
            overlay: overlay,
            iframe: iframe,
            titleEl: titleEl,
            open: function (newTitle, newUrl, w, h) {
                if (typeof newTitle === 'string') {
                    titleEl.textContent = newTitle;
                }
                if (typeof newUrl === 'string') {
                    iframe.src = newUrl;
                }
                if (typeof w !== 'undefined') {
                    win.style.width = (typeof w === 'number' ? w + 'px' : w);
                }
                if (typeof h !== 'undefined') {
                    win.style.height = (typeof h === 'number' ? h + 'px' : h);
                }
                overlay.style.display = 'flex';
            },
            close: closeModal
        };
    }

    function modalWindow(name, title, url, width, height) {
        width  = (typeof width  === 'undefined') ? 800 : width;
        height = (typeof height === 'undefined') ? 600 : height;

        // Risolviamo il documento "top". Se il top non è accessibile (cross-origin)
        // ripieghiamo sul documento corrente.
        var topWin;
        try {
            topWin = root.top || root;
            // Forza l'accesso a document per innescare l'eventuale SecurityError.
            // eslint-disable-next-line no-unused-expressions
            topWin.document;
        } catch (e) {
            topWin = root;
        }
        var doc = topWin.document;

        // Recuperiamo o creiamo il registro delle modali sul top.
        if (!topWin.__gdrcdModals) {
            topWin.__gdrcdModals = {};
        }
        var registry = topWin.__gdrcdModals;

        var existing = registry[name];
        // Verifichiamo anche che l'elemento sia ancora nel DOM (può sparire dopo un reload).
        if (existing && doc.getElementById('dialog-' + name)) {
            existing.open(title, url, width, height);
            return existing;
        }

        var modal = buildModal(doc, name, title, url, width, height);
        registry[name] = modal;
        return modal;
    }

    root.modalWindow = modalWindow;
})(window);
