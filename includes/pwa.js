/**
 * GDRCD PWA bootstrap
 *
 * Registra il service worker `/service-worker.js` (scope: tutta l'origine),
 * cattura l'evento `beforeinstallprompt` per consentire a UI custom di
 * mostrare un pulsante "Installa app", e notifica l'utente quando è stata
 * installata una nuova versione del SW.
 *
 * Tutto vanilla JS, nessuna dipendenza esterna. Tollerante ai browser che
 * non supportano le Service Worker API (Safari < 11.1, IE).
 */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    // Salviamo il prompt di install per consentire ad altre parti dell'UI
    // di mostrare un bottone "Installa" su misura.
    window.deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.deferredPrompt = event;
        // Espone un evento custom in modo che eventuali bottoni di install
        // possano reagire senza dover sapere come si chiama la variabile.
        document.dispatchEvent(new CustomEvent('gdrcd:installable'));
    });

    window.addEventListener('appinstalled', function () {
        window.deferredPrompt = null;
        document.dispatchEvent(new CustomEvent('gdrcd:installed'));
    });

    // SW fa skipWaiting() + clients.claim() in install/activate: l'update e'
    // silenzioso, gli asset vecchi restano nel runtime cache fino al prossimo
    // stale-while-revalidate. Nessun banner: la pagina corrente continua a
    // funzionare; eventuali asset nuovi entrano alla navigazione successiva.

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .catch(function (err) {
                if (window.console && console.warn) {
                    console.warn('[GDRCD PWA] Service Worker registration failed:', err);
                }
            });

        // Notifica custom su controllerchange (utile a chi vuole reagire
        // programmaticamente). Niente reload automatico per evitare loop.
        navigator.serviceWorker.addEventListener('controllerchange', function () {
            document.dispatchEvent(new CustomEvent('gdrcd:sw-controllerchange'));
        });
    });
})();
