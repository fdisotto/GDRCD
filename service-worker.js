/**
 * GDRCD Service Worker
 *
 * Strategie di cache:
 *  - Documenti HTML (navigation requests): network-first con fallback alla
 *    pagina /offline.html se la rete non è disponibile.
 *  - Asset statici (CSS, JS, immagini, font, manifest): stale-while-revalidate
 *    su una runtime cache, in modo che la app shell rimanga utilizzabile
 *    anche su connessioni instabili.
 *  - Tutte le altre richieste (API, POST, query con parametri sensibili)
 *    passano attraverso fetch() senza intercettazione.
 *
 * Per invalidare le cache aggiornare CACHE_VERSION e ridistribuire il file:
 * il nuovo SW entrerà in fase di "waiting" e attiverà la pulizia delle
 * versioni precedenti al primo controllerchange.
 */

'use strict';

var CACHE_VERSION   = 'gdrcd-v1';
var PRECACHE_NAME   = CACHE_VERSION + '-precache';
var RUNTIME_NAME    = CACHE_VERSION + '-runtime';
var OFFLINE_URL     = '/offline.html';

// App shell: file noti, indispensabili per offrire un'esperienza di base
// anche senza connessione. Vengono installati subito al primo avvio.
var PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/themes/tailwind/output.css',
    '/imgs/favicon.ico',
    '/includes/corefunctions.js',
    '/includes/modal.js',
    '/includes/toast.js',
    '/includes/theme-toggle.js',
    '/includes/cookie-consent.js',
    '/includes/notifications.js',
    '/includes/presenti.js',
    '/includes/chat.js'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(PRECACHE_NAME).then(function (cache) {
            // addAll fallisce in modo atomico se anche solo un asset
            // restituisce 404: usiamo Promise.all su singole add() in modo
            // che l'installazione resti tollerante ai file mancanti.
            return Promise.all(PRECACHE_URLS.map(function (url) {
                return cache.add(new Request(url, { cache: 'reload' }))
                    .catch(function () { /* ignora i file non disponibili */ });
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== PRECACHE_NAME && key !== RUNTIME_NAME) {
                    return caches.delete(key);
                }
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

/**
 * Stale-while-revalidate per asset statici.
 */
function staleWhileRevalidate(request) {
    return caches.open(RUNTIME_NAME).then(function (cache) {
        return cache.match(request).then(function (cached) {
            var networkFetch = fetch(request).then(function (response) {
                if (response && response.status === 200 && response.type === 'basic') {
                    cache.put(request, response.clone());
                }
                return response;
            }).catch(function () {
                return cached;
            });
            return cached || networkFetch;
        });
    });
}

/**
 * Network-first per richieste di navigazione, con fallback alla pagina offline.
 */
function networkFirstForNavigation(request) {
    return fetch(request).then(function (response) {
        return response;
    }).catch(function () {
        return caches.match(OFFLINE_URL).then(function (cached) {
            return cached || new Response(
                '<h1>Sei offline</h1>',
                { headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
            );
        });
    });
}

self.addEventListener('fetch', function (event) {
    var request = event.request;

    // Solo GET: lasciamo passare POST/PUT/DELETE (login, chat, mutazioni).
    if (request.method !== 'GET') {
        return;
    }

    var url = new URL(request.url);

    // Ignoriamo cross-origin (CDN font Google, ecc.): policy CORS può
    // rompere le cache opache.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Le richieste verso /api/ NON devono essere intercettate: dipendono
    // dallo stato sessione lato server e producono JSON dinamico.
    if (url.pathname.indexOf('/api/') === 0) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstForNavigation(request));
        return;
    }

    // Per richieste GET non-navigation verso script PHP (es. fetch dinamiche)
    // lasciamo il default del browser: stateful, non cacheabile.
    if (url.pathname.match(/\.php$/i)) {
        return;
    }

    // Asset statici: stale-while-revalidate.
    if (request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font' ||
        request.destination === 'manifest') {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }
});

// Hook per consentire al client di forzare l'attivazione del nuovo SW.
self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

/**
 * Web Push: gestisce i payload arrivati dal push service del browser.
 *
 * Il server (includes/push.inc.php) invia un payload JSON cifrato AES-128-GCM
 * con la chiave pubblica del client (p256dh). Il push service lo consegna
 * gia' decifrato al SW. Formato atteso:
 *
 *   {
 *     "title": "Titolo notifica",
 *     "body":  "Corpo testo",
 *     "url":   "/main.php?page=messages_center", // optional target on click
 *     "tag":   "gdrcd-pm"                          // optional dedup tag
 *   }
 *
 * Se il payload non e' parsable (es. ping di test o payload corrotto)
 * mostriamo comunque una notifica neutra, perche' alcuni browser revocano
 * il permesso push se il SW non chiama showNotification() su un push event.
 */
self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        // Payload non JSON: prova come testo, altrimenti fallback.
        try {
            data = { title: 'GDRCD', body: event.data ? event.data.text() : '' };
        } catch (e2) {
            data = { title: 'GDRCD', body: '' };
        }
    }

    var title = data.title || 'GDRCD';
    var options = {
        body:     data.body || '',
        icon:     '/imgs/icon-192.png',
        badge:    '/imgs/favicon.ico',
        tag:      data.tag || 'gdrcd-notification',
        data:     data.url || '/main.php',
        renotify: false
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

/**
 * Click sulla notifica: porta l'utente alla URL associata (data.url
 * salvata in showNotification.options.data). Se esiste gia' una finestra
 * GDRCD aperta la focalizziamo invece di aprirne una nuova.
 */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var targetUrl = event.notification.data || '/main.php';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                // Riusa una finestra esistente se possibile.
                if ('focus' in client) {
                    try {
                        if ('navigate' in client) {
                            client.navigate(targetUrl);
                        }
                        return client.focus();
                    } catch (e) {
                        // Alcuni browser bloccano navigate cross-origin; fallback ad openWindow.
                    }
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
            return undefined;
        })
    );
});
