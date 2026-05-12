/**
 * GDRCD WebSocket singleton.
 *
 * Una sola conn ws://host:8082 condivisa fra chat / notifications / presenti.
 * I client (chat.js, notifications.js, presenti.js) si registrano via
 * `GDRCDSocket.subscribe(channel, params, handler)` invece di aprire WS
 * propri. La conn viene aperta lazily al primo subscribe; in close re-subscribe
 * automatico dopo reconnect con backoff esponenziale.
 *
 * API:
 *   GDRCDSocket.subscribe(channel, params, onMessage) -> unsubscribe()
 *   GDRCDSocket.isActive()         -> bool
 *   GDRCDSocket.onStateChange(cb)  -> dispatch su open/close/auth-fail
 *
 * Dispatch in ingresso:
 *   - 'bootstrap'/'messages' -> handler chat con stesso room
 *   - 'notifications'        -> handler notifications
 *   - 'presenti'             -> handler presenti
 *   - 'error' auth-fail      -> tutti gli handler + listener stato
 *
 * URL: meta[name="gdrcd-ws-url"] (autodetect 8082 se host non risolvibile).
 */
(function () {
    'use strict';

    if (typeof window === 'undefined') return;
    if (window.GDRCDSocket) return; // gia' inizializzato

    var ws = null;
    var wsActive = false;
    var backoff = 1000;
    var reconnectTimer = null;
    var authFailed = false;

    // subscribers: { id: { channel, params, onMessage } }
    var subs = Object.create(null);
    var nextSubId = 1;

    var stateListeners = [];

    function notifyState(state) {
        for (var i = 0; i < stateListeners.length; i++) {
            try { stateListeners[i](state); } catch (e) { /* no-op */ }
        }
    }

    function wsUrl() {
        var meta = document.querySelector('meta[name="gdrcd-ws-url"]');
        var configured = meta ? (meta.getAttribute('content') || '').trim() : '';
        if (!window.location) return configured;
        var loc = window.location;
        var scheme = (loc.protocol === 'https:') ? 'wss' : 'ws';
        var needsRewrite = !configured;
        if (configured) {
            try {
                var u = new URL(configured);
                if (u.hostname !== loc.hostname && u.hostname.indexOf('.') === -1) {
                    needsRewrite = true;
                }
            } catch (e) { needsRewrite = true; }
        }
        return needsRewrite ? (scheme + '://' + loc.hostname + ':8082') : configured;
    }

    function send(obj) {
        if (!ws || ws.readyState !== 1) return false;
        try { ws.send(JSON.stringify(obj)); return true; }
        catch (e) { return false; }
    }

    function buildSubscribeMessage(sub) {
        var msg = { action: 'subscribe', channel: sub.channel };
        if (sub.channel === 'chat' && sub.params && sub.params.room) {
            msg.room = sub.params.room | 0;
        }
        return msg;
    }

    function sendAllSubscribes() {
        for (var id in subs) {
            send(buildSubscribeMessage(subs[id]));
        }
    }

    function dispatch(payload) {
        if (!payload || !payload.type) return;

        if (payload.type === 'error' && payload.error === 'unauthenticated') {
            authFailed = true;
            for (var id in subs) {
                try { subs[id].onMessage(payload); } catch (e) { /* no-op */ }
            }
            notifyState('auth-fail');
            return;
        }

        for (var sid in subs) {
            var sub = subs[sid];
            var match = false;

            if (sub.channel === 'chat') {
                if (payload.type === 'bootstrap' || payload.type === 'subscribed' || payload.type === 'messages') {
                    var subRoom = sub.params && sub.params.room ? sub.params.room | 0 : 0;
                    var msgRoom = payload.room | 0;
                    match = (subRoom === msgRoom);
                }
            } else if (sub.channel === 'notifications') {
                match = (payload.type === 'notifications');
            } else if (sub.channel === 'presenti') {
                match = (payload.type === 'presenti');
            }

            if (match) {
                try { sub.onMessage(payload); } catch (e) { /* no-op */ }
            }
        }
    }

    function connect() {
        if (ws || authFailed) return;
        var url = wsUrl();
        if (!url || typeof window.WebSocket === 'undefined') return;

        try { ws = new window.WebSocket(url); }
        catch (e) { ws = null; scheduleReconnect(); return; }

        ws.addEventListener('open', function () {
            wsActive = true;
            backoff = 1000;
            sendAllSubscribes();
            notifyState('open');
        });

        ws.addEventListener('message', function (ev) {
            var payload;
            try { payload = JSON.parse(ev.data); }
            catch (e) { return; }
            dispatch(payload);
        });

        ws.addEventListener('close', function () {
            wsActive = false;
            ws = null;
            notifyState('close');
            if (!authFailed) scheduleReconnect();
        });

        ws.addEventListener('error', function () { /* close handles cleanup */ });
    }

    function scheduleReconnect() {
        if (reconnectTimer !== null) return;
        if (Object.keys(subs).length === 0) return; // nessuno subscribato
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null;
            connect();
        }, backoff);
        backoff = Math.min(backoff * 2, 30000);
    }

    function subscribe(channel, params, onMessage) {
        var id = String(nextSubId++);
        subs[id] = {
            channel: String(channel || ''),
            params:  params || {},
            onMessage: typeof onMessage === 'function' ? onMessage : function () {}
        };

        if (wsActive) {
            send(buildSubscribeMessage(subs[id]));
        } else if (!ws && !authFailed) {
            connect();
        }

        return function unsubscribe() {
            if (!subs[id]) return;
            var ch = subs[id].channel;
            var rm = subs[id].params && subs[id].params.room ? subs[id].params.room | 0 : 0;
            delete subs[id];
            if (wsActive) {
                var msg = { action: 'unsubscribe', channel: ch };
                if (ch === 'chat' && rm) msg.room = rm;
                send(msg);
            }
        };
    }

    function onStateChange(cb) {
        if (typeof cb === 'function') stateListeners.push(cb);
    }

    // Chiusura ordinata al unload.
    window.addEventListener('beforeunload', function () {
        if (ws) { try { ws.close(); } catch (e) { /* no-op */ } }
    });

    window.GDRCDSocket = {
        subscribe: subscribe,
        isActive:  function () { return wsActive; },
        onStateChange: onStateChange
    };
})();
