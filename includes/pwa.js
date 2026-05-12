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

    /**
     * Mostra un avviso non bloccante quando è disponibile una nuova versione
     * del service worker. Se `window.gdrcd_toast` è presente (vedi toast.js)
     * la usa, altrimenti ricade su un banner inline minimale.
     */
    function notifyUpdate() {
        if (typeof window.gdrcd_toast === 'function') {
            window.gdrcd_toast('Nuova versione disponibile, ricarica per aggiornare.', 'info');
            return;
        }
        try {
            var banner = document.createElement('div');
            banner.setAttribute('role', 'status');
            banner.style.cssText =
                'position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);' +
                'background:#a47e3b;color:#fff;padding:.6rem 1rem;border-radius:.5rem;' +
                'font:14px/1.4 sans-serif;box-shadow:0 4px 16px rgba(0,0,0,.2);z-index:9999;';
            banner.textContent = 'Nuova versione disponibile — ricarica per aggiornare.';
            document.body.appendChild(banner);
            setTimeout(function () { banner.remove(); }, 8000);
        } catch (e) { /* no-op */ }
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .then(function (registration) {
                if (!registration) { return; }

                // Notifica utente quando un nuovo SW è in waiting.
                if (registration.waiting) {
                    notifyUpdate();
                }

                registration.addEventListener('updatefound', function () {
                    var newWorker = registration.installing;
                    if (!newWorker) { return; }
                    newWorker.addEventListener('statechange', function () {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            notifyUpdate();
                        }
                    });
                });
            })
            .catch(function (err) {
                if (window.console && console.warn) {
                    console.warn('[GDRCD PWA] Service Worker registration failed:', err);
                }
            });

        // Quando il controller cambia (nuova versione attivata), ricarica
        // automaticamente la pagina una sola volta per applicare gli asset.
        var refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (refreshing) { return; }
            refreshing = true;
            // Lasciamo all'utente il controllo del reload tramite il banner;
            // qui ci limitiamo a notificare un secondo evento.
            document.dispatchEvent(new CustomEvent('gdrcd:sw-controllerchange'));
        });
    });
})();
