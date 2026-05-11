/**
 * GDRCD — Toast notification system.
 *
 * Tailwind content scanner hint (literal class names must appear in source
 * for the JIT to keep their rules in output.css):
 *   gdrcd-toast-success gdrcd-toast-error gdrcd-toast-warning gdrcd-toast-info
 *
 * Vanilla JS, no dependencies. Exposes a global function:
 *   window.gdrcdToast(kind, message, opts);
 *
 * - kind: 'success' | 'error' | 'warning' | 'info'
 * - message: string (will be inserted as text, HTML-escaped)
 * - opts: { duration?: number }   default 5000ms; 0 = sticky (no auto-dismiss)
 *
 * Behavior:
 * - Container created lazily, fixed bottom-right, stacks vertically.
 * - Max 5 visible: the oldest is auto-dismissed when a 6th is shown.
 * - Hover pauses the auto-dismiss timer; mouseleave resumes (remaining time).
 * - Click the × button to dismiss immediately.
 * - Slide-in/fade-out animations via Tailwind transition classes.
 */
(function () {
    'use strict';

    var CONTAINER_ID = 'gdrcd-toast-container';
    var MAX_TOASTS = 5;
    var DEFAULT_DURATION = 5000;

    var ICONS = {
        success: '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
        error:   '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        warning: '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        info:    '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>'
    };

    function ensureContainer() {
        var el = document.getElementById(CONTAINER_ID);
        if (el) return el;
        el = document.createElement('div');
        el.id = CONTAINER_ID;
        el.className = 'gdrcd-toast-container';
        el.setAttribute('role', 'region');
        el.setAttribute('aria-label', 'Notifiche');
        document.body.appendChild(el);
        return el;
    }

    function dismiss(toast) {
        if (!toast || toast.__dismissed) return;
        toast.__dismissed = true;
        if (toast.__timer) {
            clearTimeout(toast.__timer);
            toast.__timer = null;
        }
        toast.classList.add('is-leaving');
        // Remove after CSS transition finishes (200ms).
        setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 220);
    }

    function scheduleDismiss(toast, remaining) {
        if (remaining <= 0) return;
        toast.__remaining = remaining;
        toast.__startedAt = Date.now();
        toast.__timer = setTimeout(function () {
            dismiss(toast);
        }, remaining);
    }

    function pauseDismiss(toast) {
        if (!toast.__timer) return;
        clearTimeout(toast.__timer);
        toast.__timer = null;
        var elapsed = Date.now() - toast.__startedAt;
        toast.__remaining = Math.max(0, (toast.__remaining || 0) - elapsed);
    }

    function resumeDismiss(toast) {
        if (toast.__timer || !toast.__remaining) return;
        scheduleDismiss(toast, toast.__remaining);
    }

    function enforceMax(container) {
        var children = container.querySelectorAll('.gdrcd-toast:not(.is-leaving)');
        while (children.length >= MAX_TOASTS) {
            dismiss(children[0]);
            children = container.querySelectorAll('.gdrcd-toast:not(.is-leaving)');
        }
    }

    function buildToast(kind, message) {
        var safeKind = ICONS.hasOwnProperty(kind) ? kind : 'info';

        var toast = document.createElement('div');
        toast.className = 'gdrcd-toast gdrcd-toast-' + safeKind + ' is-entering';
        toast.setAttribute('role', safeKind === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', safeKind === 'error' ? 'assertive' : 'polite');

        var iconWrap = document.createElement('span');
        iconWrap.className = 'gdrcd-toast-icon';
        iconWrap.innerHTML = ICONS[safeKind];

        var msg = document.createElement('div');
        msg.className = 'gdrcd-toast-msg';
        msg.textContent = String(message == null ? '' : message);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'gdrcd-toast-close';
        close.setAttribute('aria-label', 'Chiudi notifica');
        close.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
        close.addEventListener('click', function () { dismiss(toast); });

        toast.appendChild(iconWrap);
        toast.appendChild(msg);
        toast.appendChild(close);

        return toast;
    }

    function show(kind, message, opts) {
        opts = opts || {};
        var duration = (typeof opts.duration === 'number') ? opts.duration : DEFAULT_DURATION;

        var container = ensureContainer();
        enforceMax(container);

        var toast = buildToast(kind, message);
        container.appendChild(toast);

        // Trigger slide-in: remove is-entering on next frame.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.remove('is-entering');
            });
        });

        if (duration > 0) {
            scheduleDismiss(toast, duration);
            toast.addEventListener('mouseenter', function () { pauseDismiss(toast); });
            toast.addEventListener('mouseleave', function () { resumeDismiss(toast); });
            toast.addEventListener('focusin', function () { pauseDismiss(toast); });
            toast.addEventListener('focusout', function () { resumeDismiss(toast); });
        }

        return toast;
    }

    window.gdrcdToast = show;
})();
