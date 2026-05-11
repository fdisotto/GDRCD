/**
 * GDRCD desktop notifications.
 *
 * Polla l'endpoint /api/notifications.inc.php ogni POLL_INTERVAL ms,
 * confronta i contatori con lo snapshot precedente in localStorage e,
 * se il numero di PM (o segnalazioni GM) e' aumentato, mostra una
 * notifica del browser. La notifica viene saltata se la tab e' gia'
 * a fuoco oppure se l'utente ha disattivato le notifiche.
 *
 * Vanilla JS, niente jQuery. Il permesso viene richiesto solo su
 * interazione utente (Chrome blocca le richieste a page-load).
 *
 * @see api/notifications.inc.php
 */
(function () {
    'use strict';

    if (typeof window === 'undefined') return;

    var POLL_INTERVAL_MS = 30000;
    var ENDPOINT = '/api/notifications.inc.php';
    var ICON_URL = '/imgs/favicon.ico';
    var TARGET_URL = '/main.php?page=messages_center';

    var LS_KEYS = {
        lastPm: 'gdrcd_notif_last_pm',
        lastSeg: 'gdrcd_notif_last_segnalazioni',
        lastPmId: 'gdrcd_notif_last_pm_id',
        enabled: 'gdrcd_notifications_enabled',
        promptDismissed: 'gdrcd_notif_prompt_dismissed'
    };

    // --- Notification API support check --------------------------------
    if (typeof window.Notification === 'undefined') {
        // Niente API: esci silenziosamente.
        return;
    }

    // --- localStorage helpers (tolleranti a quota / privacy mode) ------
    function lsGet(key, def) {
        try {
            var v = localStorage.getItem(key);
            return v === null ? def : v;
        } catch (e) {
            return def;
        }
    }
    function lsSet(key, value) {
        try { localStorage.setItem(key, String(value)); } catch (e) { /* no-op */ }
    }

    function isEnabled() {
        // Default true: disattivate solo se l'utente lo dice esplicitamente.
        return lsGet(LS_KEYS.enabled, '1') === '1';
    }

    // --- Permission prompt UI ------------------------------------------
    var promptEl = null;

    function createPromptUi() {
        if (promptEl) return promptEl;
        var wrap = document.createElement('div');
        wrap.id = 'gdrcd-notif-prompt';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-label', 'Abilita notifiche desktop');
        wrap.style.cssText = [
            'position:fixed',
            'bottom:1rem',
            'right:1rem',
            'z-index:9999',
            'max-width:22rem',
            'padding:0.75rem 1rem',
            'border-radius:0.5rem',
            'background:rgba(20,20,28,0.95)',
            'color:#fff',
            'font-family:system-ui,-apple-system,Segoe UI,sans-serif',
            'font-size:0.875rem',
            'box-shadow:0 8px 24px rgba(0,0,0,0.35)',
            'display:flex',
            'gap:0.75rem',
            'align-items:center'
        ].join(';');

        var msg = document.createElement('span');
        msg.style.cssText = 'flex:1;line-height:1.3';
        msg.textContent = 'Vuoi ricevere una notifica desktop per i nuovi messaggi?';
        wrap.appendChild(msg);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Abilita';
        btn.style.cssText = [
            'background:#7c5cff',
            'color:#fff',
            'border:0',
            'padding:0.4rem 0.7rem',
            'border-radius:0.375rem',
            'cursor:pointer',
            'font-weight:600'
        ].join(';');
        btn.addEventListener('click', requestPermission);
        wrap.appendChild(btn);

        var close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Chiudi');
        close.textContent = 'x';
        close.style.cssText = [
            'background:transparent',
            'color:#aaa',
            'border:0',
            'padding:0.25rem 0.5rem',
            'cursor:pointer',
            'font-size:1rem'
        ].join(';');
        close.addEventListener('click', function () {
            lsSet(LS_KEYS.promptDismissed, '1');
            hidePromptUi();
        });
        wrap.appendChild(close);

        document.body.appendChild(wrap);
        promptEl = wrap;
        return wrap;
    }

    function hidePromptUi() {
        if (promptEl && promptEl.parentNode) {
            promptEl.parentNode.removeChild(promptEl);
        }
        promptEl = null;
    }

    function maybeShowPromptUi() {
        if (!isEnabled()) return;
        if (Notification.permission !== 'default') return;
        if (lsGet(LS_KEYS.promptDismissed, '0') === '1') return;
        // Mostra solo dopo la prima interazione (gestito da bootstrap).
        createPromptUi();
    }

    function requestPermission() {
        if (Notification.permission !== 'default') {
            hidePromptUi();
            return;
        }
        try {
            var p = Notification.requestPermission(function (result) {
                // Callback legacy (Safari).
                hidePromptUi();
            });
            // Promise-based (Chrome, FF moderni).
            if (p && typeof p.then === 'function') {
                p.then(function () { hidePromptUi(); }).catch(function () { hidePromptUi(); });
            }
        } catch (e) {
            hidePromptUi();
        }
    }

    // --- Fire a desktop notification -----------------------------------
    function fireNotification(title, body) {
        if (!isEnabled()) return;
        if (Notification.permission !== 'granted') return;
        // Tab gia' a fuoco: aggiorna il badge ma niente popup invadente.
        if (document.hasFocus()) return;

        try {
            var n = new Notification(title, {
                body: body || '',
                icon: ICON_URL,
                tag: 'gdrcd-pm'
            });
            n.onclick = function () {
                try { window.focus(); } catch (e) { /* no-op */ }
                try { n.close(); } catch (e) { /* no-op */ }
                window.location.href = TARGET_URL;
            };
        } catch (e) {
            // Qualche browser tira eccezioni in contesti non sicuri (http).
        }
    }

    // --- Polling -------------------------------------------------------
    function diffAndNotify(data) {
        var unreadPm = parseInt(data && data.unread_pm, 10) || 0;
        var unreadSeg = parseInt(data && data.unread_segnalazioni, 10) || 0;
        var latest = (data && data.latest_pm) || null;

        var prevPm = parseInt(lsGet(LS_KEYS.lastPm, '0'), 10) || 0;
        var prevSeg = parseInt(lsGet(LS_KEYS.lastSeg, '0'), 10) || 0;
        var prevId = parseInt(lsGet(LS_KEYS.lastPmId, '0'), 10) || 0;

        // PM: solo se il contatore e' aumentato E l'id e' nuovo (evita doppi
        // popup se l'utente apre un messaggio e poi ne arriva un altro).
        if (unreadPm > prevPm && latest && latest.id && latest.id !== prevId) {
            var sender = (latest.from || '').toString();
            var subject = (latest.subject || '').toString();
            fireNotification(
                'Nuovo messaggio da ' + sender,
                subject || '(senza oggetto)'
            );
        }
        if (latest && latest.id) lsSet(LS_KEYS.lastPmId, latest.id);
        lsSet(LS_KEYS.lastPm, unreadPm);

        // Segnalazioni GM.
        if (unreadSeg > prevSeg) {
            var delta = unreadSeg - prevSeg;
            fireNotification(
                'Nuova segnalazione GM',
                delta > 1
                    ? (delta + ' nuove segnalazioni in coda')
                    : 'Una nuova segnalazione da gestire'
            );
        }
        lsSet(LS_KEYS.lastSeg, unreadSeg);
    }

    function poll() {
        if (!isEnabled()) return;
        try {
            fetch(ENDPOINT, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            }).then(function (r) {
                if (!r.ok) return null;
                return r.json();
            }).then(function (data) {
                if (data) diffAndNotify(data);
            }).catch(function () { /* errori di rete: ritenta al prossimo giro */ });
        } catch (e) { /* no-op */ }
    }

    // --- Bootstrap -----------------------------------------------------
    function bootstrap() {
        // Prompt solo dopo la prima interazione utente (Chrome richiede gesture).
        var armed = false;
        function arm() {
            if (armed) return;
            armed = true;
            maybeShowPromptUi();
            window.removeEventListener('click', arm, true);
            window.removeEventListener('keydown', arm, true);
            window.removeEventListener('touchstart', arm, true);
        }
        window.addEventListener('click', arm, true);
        window.addEventListener('keydown', arm, true);
        window.addEventListener('touchstart', arm, true);

        // Primo polling a stretto giro (ma fuori dal critical path) per
        // inizializzare lo snapshot; poi a intervalli regolari.
        setTimeout(poll, 2000);
        setInterval(poll, POLL_INTERVAL_MS);

        // API pubblica minima: utile per un eventuale toggle in settings.
        window.GDRCDNotifications = {
            isEnabled: isEnabled,
            enable: function () { lsSet(LS_KEYS.enabled, '1'); },
            disable: function () { lsSet(LS_KEYS.enabled, '0'); },
            requestPermission: requestPermission,
            permission: function () { return Notification.permission; }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
