/**
 * GDRCD — Cookie consent banner (GDPR friendly).
 *
 * Mostra un banner alla prima visita per informare l'utente sui cookie
 * tecnici e localStorage utilizzati dal sito. Il sito non usa cookie
 * di profilazione o tracciamento di terze parti: la scelta serve solo
 * a documentare il consenso informato.
 *
 * Storage:
 *   - localStorage 'gdrcd_consent'  (oggetto JSON: { v, ts, choice, functional })
 *   - cookie       'gdrcd_consent_v1=1' (durata 1 anno, SameSite=Lax)
 *
 * API esposta su window.GdrcdConsent:
 *   has(category)  — true se l'utente ha accettato la categoria
 *                    ('essential' sempre true, 'functional' opt-in)
 *   get()          — ritorna lo stato salvato o null
 *   reset()        — rimuove la scelta e ri-mostra il banner (debug)
 *
 * Tailwind content scanner hint (literal class names referenced from JS):
 *   gdrcd-cookie-banner gdrcd-cookie-banner-inner
 *   gdrcd-cookie-modal gdrcd-cookie-modal-card
 *
 * @author GDRCD core
 */
(function () {
    'use strict';

    var LS_KEY = 'gdrcd_consent';
    var COOKIE_NAME = 'gdrcd_consent_v1';
    var VERSION = 1;

    function safeStorage() {
        try {
            if (typeof window === 'undefined' || !window.localStorage) return null;
            var t = '__gdrcd_consent_probe__';
            window.localStorage.setItem(t, '1');
            window.localStorage.removeItem(t);
            return window.localStorage;
        } catch (e) {
            return null;
        }
    }

    function readCookie(name) {
        var parts = (document.cookie || '').split(';');
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].replace(/^\s+/, '');
            if (p.indexOf(name + '=') === 0) {
                return decodeURIComponent(p.substring(name.length + 1));
            }
        }
        return null;
    }

    function writeCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        var secure = (location.protocol === 'https:') ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; expires=' + d.toUTCString() +
            '; path=/; SameSite=Lax' + secure;
    }

    function readState() {
        var ls = safeStorage();
        if (!ls) return null;
        try {
            var raw = ls.getItem(LS_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || parsed.v !== VERSION) return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writeState(choice, functional) {
        var state = {
            v: VERSION,
            ts: Date.now(),
            choice: choice,            // 'all' | 'essential'
            functional: !!functional
        };
        var ls = safeStorage();
        if (ls) {
            try { ls.setItem(LS_KEY, JSON.stringify(state)); } catch (e) { /* ignore */ }
        }
        writeCookie(COOKIE_NAME, '1', 365);
        return state;
    }

    function hasConsent() {
        return !!readState() || readCookie(COOKIE_NAME) === '1';
    }

    /* ---------- Modale dettaglio ---------- */
    var lastFocused = null;
    var modalEl = null;

    function trapFocus(e) {
        if (!modalEl || e.key !== 'Tab') return;
        var focusables = modalEl.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            last.focus();
            e.preventDefault();
        } else if (!e.shiftKey && document.activeElement === last) {
            first.focus();
            e.preventDefault();
        }
    }

    function onModalKey(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeModal();
        } else {
            trapFocus(e);
        }
    }

    function closeModal() {
        if (!modalEl) return;
        modalEl.parentNode && modalEl.parentNode.removeChild(modalEl);
        modalEl = null;
        document.removeEventListener('keydown', onModalKey);
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function openModal() {
        lastFocused = document.activeElement;
        modalEl = document.createElement('div');
        modalEl.className = 'gdrcd-cookie-modal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('aria-labelledby', 'gdrcd-cookie-modal-title');

        modalEl.innerHTML =
            '<div class="gdrcd-cookie-modal-card" role="document">' +
              '<div class="gdrcd-card-header">' +
                '<h2 id="gdrcd-cookie-modal-title" class="gdrcd-h3">Dettaglio cookie e archiviazione locale</h2>' +
              '</div>' +
              '<div class="gdrcd-card-body space-y-4">' +
                '<p class="gdrcd-prose">' +
                  'GDRCD utilizza esclusivamente cookie tecnici e voci di archiviazione locale ' +
                  '(localStorage) necessarie al funzionamento del sito o a memorizzare le tue ' +
                  'preferenze. Non sono presenti cookie di profilazione, pubblicità o tracciamento ' +
                  'di terze parti.' +
                '</p>' +
                '<table class="gdrcd-table">' +
                  '<thead><tr>' +
                    '<th>Nome</th><th>Tipo</th><th>Scopo</th><th>Durata</th>' +
                  '</tr></thead>' +
                  '<tbody>' +
                    '<tr><td>PHPSESSID</td><td>Cookie</td>' +
                      '<td>Identifica la sessione PHP dell\'utente loggato. Essenziale.</td>' +
                      '<td>Sessione</td></tr>' +
                    '<tr><td>lastlogin</td><td>Cookie</td>' +
                      '<td>Tracciamento accessi multipli sullo stesso account. Essenziale.</td>' +
                      '<td>30 giorni</td></tr>' +
                    '<tr><td>gdrcd_theme</td><td>localStorage</td>' +
                      '<td>Tema preferito (chiaro / scuro). Funzionale.</td>' +
                      '<td>Permanente</td></tr>' +
                    '<tr><td>gdrcd_favorites</td><td>localStorage</td>' +
                      '<td>Elenco dei luoghi marcati come preferiti. Funzionale.</td>' +
                      '<td>Permanente</td></tr>' +
                    '<tr><td>gdrcd_notif_*</td><td>localStorage</td>' +
                      '<td>Stato locale delle notifiche desktop. Funzionale.</td>' +
                      '<td>Permanente</td></tr>' +
                    '<tr><td>gdrcd_consent_v1</td><td>Cookie + localStorage</td>' +
                      '<td>Memorizzazione della scelta sui cookie. Essenziale.</td>' +
                      '<td>1 anno</td></tr>' +
                  '</tbody>' +
                '</table>' +
                '<p class="gdrcd-muted">' +
                  'I cookie e i dati funzionali rimangono sul tuo dispositivo: puoi rimuoverli ' +
                  'in qualunque momento dalle impostazioni del browser.' +
                '</p>' +
                '<div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">' +
                  '<button type="button" class="gdrcd-btn-ghost" data-gdrcd-consent-close>Chiudi</button>' +
                  '<button type="button" class="gdrcd-btn-secondary" data-gdrcd-consent-essential>Solo essenziali</button>' +
                  '<button type="button" class="gdrcd-btn-primary" data-gdrcd-consent-all>Accetta tutto</button>' +
                '</div>' +
              '</div>' +
            '</div>';

        document.body.appendChild(modalEl);

        modalEl.addEventListener('mousedown', function (e) {
            if (e.target === modalEl) closeModal();
        });
        modalEl.querySelector('[data-gdrcd-consent-close]').addEventListener('click', closeModal);
        modalEl.querySelector('[data-gdrcd-consent-all]').addEventListener('click', function () {
            acceptAll(); closeModal();
        });
        modalEl.querySelector('[data-gdrcd-consent-essential]').addEventListener('click', function () {
            acceptEssential(); closeModal();
        });
        document.addEventListener('keydown', onModalKey);

        var firstBtn = modalEl.querySelector('button');
        if (firstBtn) firstBtn.focus();
    }

    /* ---------- Banner ---------- */
    var bannerEl = null;

    function removeBanner() {
        if (!bannerEl) return;
        bannerEl.parentNode && bannerEl.parentNode.removeChild(bannerEl);
        bannerEl = null;
    }

    function acceptAll() {
        writeState('all', true);
        removeBanner();
    }

    function acceptEssential() {
        writeState('essential', false);
        removeBanner();
    }

    function showBanner() {
        if (bannerEl) return;
        bannerEl = document.createElement('div');
        bannerEl.className = 'gdrcd-cookie-banner';
        bannerEl.setAttribute('role', 'region');
        bannerEl.setAttribute('aria-label', 'Informativa cookie');

        bannerEl.innerHTML =
            '<div class="gdrcd-cookie-banner-inner">' +
              '<p class="gdrcd-prose flex-1">' +
                '<strong>Cookie tecnici.</strong> ' +
                'Questo sito usa cookie tecnici essenziali per il funzionamento. ' +
                'Nessun cookie di profilazione o tracciamento. ' +
                '<button type="button" class="gdrcd-link" data-gdrcd-consent-details>Dettagli</button>' +
              '</p>' +
              '<div class="flex flex-col sm:flex-row gap-2 shrink-0">' +
                '<button type="button" class="gdrcd-btn-ghost" data-gdrcd-consent-essential>Solo essenziali</button>' +
                '<button type="button" class="gdrcd-btn-primary" data-gdrcd-consent-all>Accetta tutto</button>' +
              '</div>' +
            '</div>';

        document.body.appendChild(bannerEl);

        bannerEl.querySelector('[data-gdrcd-consent-all]').addEventListener('click', acceptAll);
        bannerEl.querySelector('[data-gdrcd-consent-essential]').addEventListener('click', acceptEssential);
        bannerEl.querySelector('[data-gdrcd-consent-details]').addEventListener('click', openModal);
    }

    /* ---------- API pubblica ---------- */
    window.GdrcdConsent = {
        has: function (category) {
            if (category === 'essential') return true;
            var s = readState();
            if (!s) return false;
            if (category === 'functional') return !!s.functional;
            return false;
        },
        get: function () { return readState(); },
        reset: function () {
            var ls = safeStorage();
            if (ls) { try { ls.removeItem(LS_KEY); } catch (e) {} }
            writeCookie(COOKIE_NAME, '', -1);
            showBanner();
        },
        openDetails: openModal
    };

    function init() {
        if (hasConsent()) return;
        showBanner();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
