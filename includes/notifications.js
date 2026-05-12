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
            // Se gia' concesso prima, prova comunque a registrare la
            // subscription push (idempotente lato server).
            maybeSubscribePush();
            return;
        }
        try {
            var p = Notification.requestPermission(function (result) {
                // Callback legacy (Safari).
                hidePromptUi();
                if (result === 'granted') maybeSubscribePush();
            });
            // Promise-based (Chrome, FF moderni).
            if (p && typeof p.then === 'function') {
                p.then(function (result) {
                    hidePromptUi();
                    if (result === 'granted') maybeSubscribePush();
                }).catch(function () { hidePromptUi(); });
            }
        } catch (e) {
            hidePromptUi();
        }
    }

    // --- Web Push subscription -----------------------------------------
    /**
     * Converte una stringa VAPID public key in base64url (URL-safe, no padding)
     * nell'Uint8Array atteso da PushManager.subscribe().applicationServerKey.
     */
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw     = window.atob(base64);
        var output  = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
        return output;
    }

    /**
     * Converte un ArrayBuffer in stringa base64 standard (con padding).
     * Usata per serializzare le chiavi p256dh/auth del browser verso il server.
     */
    function arrayBufferToBase64(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function getVapidPublicKey() {
        var el = document.querySelector('meta[name="gdrcd-vapid-public"]');
        if (!el) return '';
        var v = el.getAttribute('content') || '';
        return v.trim();
    }

    /**
     * Iscrive (o riusa) la PushSubscription corrente e la invia all'endpoint
     * /api/push-subscribe.inc.php. No-op silenzioso se:
     *   - Service Worker / PushManager non supportati
     *   - chiave VAPID non configurata lato server
     *   - permesso notifiche non concesso
     */
    function maybeSubscribePush() {
        if (!isEnabled()) return;
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;

        var vapid = getVapidPublicKey();
        if (!vapid) return; // server non ha configurato VAPID

        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                if (existing) return existing;
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapid)
                });
            });
        }).then(function (sub) {
            if (!sub) return;
            var keys = sub.toJSON && sub.toJSON().keys ? sub.toJSON().keys : null;
            var p256dh = keys && keys.p256dh ? keys.p256dh : '';
            var auth   = keys && keys.auth ? keys.auth : '';
            // Fallback (vecchi browser senza toJSON()): leggi i raw buffer.
            if ((!p256dh || !auth) && sub.getKey) {
                try {
                    p256dh = p256dh || arrayBufferToBase64(sub.getKey('p256dh'));
                    auth   = auth   || arrayBufferToBase64(sub.getKey('auth'));
                } catch (e) { /* no-op */ }
            }
            if (!p256dh || !auth) return;

            return fetch('/api/push-subscribe.inc.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: sub.endpoint,
                    keys: { p256dh: p256dh, auth: auth }
                })
            });
        }).catch(function (err) {
            if (window.console && console.warn) {
                console.warn('[GDRCD push] subscribe failed:', err);
            }
        });
    }

    /**
     * Disiscrive la subscription corrente e informa il server.
     * Esposta via window.GDRCDNotifications.unsubscribePush().
     */
    function unsubscribePush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            if (!sub) return null;
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () { return endpoint; });
        }).then(function (endpoint) {
            if (!endpoint) return;
            return fetch('/api/push-unsubscribe.inc.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: endpoint })
            });
        }).catch(function (err) {
            if (window.console && console.warn) {
                console.warn('[GDRCD push] unsubscribe failed:', err);
            }
        });
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

        // Web Push: se l'utente ha gia' concesso permessi e c'e' un SW
        // attivo, registra (o riusa) la subscription. Idempotente.
        setTimeout(maybeSubscribePush, 3000);

        // API pubblica minima: utile per un eventuale toggle in settings.
        window.GDRCDNotifications = {
            isEnabled: isEnabled,
            enable: function () { lsSet(LS_KEYS.enabled, '1'); },
            disable: function () { lsSet(LS_KEYS.enabled, '0'); },
            requestPermission: requestPermission,
            permission: function () { return Notification.permission; },
            subscribePush: maybeSubscribePush,
            unsubscribePush: unsubscribePush
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
