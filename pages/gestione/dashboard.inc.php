<?php
/**
 * Dashboard amministratore (main.php?page=gestione/dashboard)
 *
 * Pannello di metriche e grafici di alto livello sull'attivita' del gioco.
 * Accesso limitato a MODERATOR e superiori (SUPERUSER).
 *
 * I dati vengono caricati via fetch JSON dall'endpoint
 * /api/admin-metrics.inc.php e renderizzati lato client con Chart.js v4
 * (caricato in CDN solo per questa pagina, non in footer globale).
 *
 * @see api/admin-metrics.inc.php
 */

if (($_SESSION['permessi'] ?? 0) < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}
?>

<div class="space-y-8" id="gdrcd-dashboard-root" data-endpoint="/api/admin-metrics.inc.php">

    <header class="space-y-2">
        <h2 class="gdrcd-h1 flex items-center gap-3">
            <span class="gdrcd-icon-circle">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </span>
            Dashboard
        </h2>
        <p class="gdrcd-muted">Panoramica dell'attivita' del gioco: metriche aggregate e grafici degli ultimi 30 giorni.</p>
    </header>

    <!-- Stato caricamento / errore (visibile solo se fetch fallisce) -->
    <div id="gdrcd-dashboard-status" class="hidden gdrcd-alert-warning">
        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        <div id="gdrcd-dashboard-status-msg">Caricamento dati in corso...</div>
    </div>

    <!-- Card metriche -->
    <section>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">

            <div class="gdrcd-card">
                <div class="p-4 flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gdrcd-accent-soft text-gdrcd-accent border border-gdrcd-accent-ring/30 shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-3a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs uppercase tracking-wide text-gdrcd-subtle">PG totali</div>
                        <div class="text-2xl font-semibold text-gdrcd-text leading-tight mt-1" id="metric-total-pg">&mdash;</div>
                    </div>
                </div>
            </div>

            <div class="gdrcd-card">
                <div class="p-4 flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-200 shrink-0 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800/60">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs uppercase tracking-wide text-gdrcd-subtle">Attivi 24h</div>
                        <div class="text-2xl font-semibold text-gdrcd-text leading-tight mt-1" id="metric-active-24h">&mdash;</div>
                    </div>
                </div>
            </div>

            <div class="gdrcd-card">
                <div class="p-4 flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-100 text-sky-700 border border-sky-200 shrink-0 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-800/60">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs uppercase tracking-wide text-gdrcd-subtle">Nuovi (7gg)</div>
                        <div class="text-2xl font-semibold text-gdrcd-text leading-tight mt-1" id="metric-new-week">&mdash;</div>
                    </div>
                </div>
            </div>

            <div class="gdrcd-card">
                <div class="p-4 flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 border border-indigo-200 shrink-0 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800/60">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs uppercase tracking-wide text-gdrcd-subtle">Msg chat (7gg)</div>
                        <div class="text-2xl font-semibold text-gdrcd-text leading-tight mt-1" id="metric-msgs-7d">&mdash;</div>
                    </div>
                </div>
            </div>

            <div class="gdrcd-card">
                <div class="p-4 flex items-start gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-rose-100 text-rose-700 border border-rose-200 shrink-0 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800/60">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21l3-1.5L9 21l3-1.5L15 21l3-1.5L21 21V5l-3 1.5L15 5l-3 1.5L9 5 6 6.5 3 5v16z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs uppercase tracking-wide text-gdrcd-subtle">Segn. pending</div>
                        <div class="text-2xl font-semibold text-gdrcd-text leading-tight mt-1" id="metric-pending-reports">&mdash;</div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Grafici -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Presenze giornaliere (30gg)</h3>
                <p class="gdrcd-muted text-xs">PG distinti con almeno un refresh in giornata.</p>
            </div>
            <div class="gdrcd-card-body">
                <div class="relative h-64">
                    <canvas id="chart-presence-30d"></canvas>
                </div>
            </div>
        </div>

        <div class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Messaggi chat per giorno (30gg)</h3>
                <p class="gdrcd-muted text-xs">Numero totale di righe inserite nella tabella chat.</p>
            </div>
            <div class="gdrcd-card-body">
                <div class="relative h-64">
                    <canvas id="chart-msgs-30d"></canvas>
                </div>
            </div>
        </div>

        <div class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Top 10 chat per attivita' (30gg)</h3>
                <p class="gdrcd-muted text-xs">Luoghi con piu' messaggi negli ultimi 30 giorni.</p>
            </div>
            <div class="gdrcd-card-body">
                <div class="relative h-64">
                    <canvas id="chart-top-chats"></canvas>
                </div>
            </div>
        </div>

        <div class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3">Top 10 PG piu' attivi (30gg)</h3>
                <p class="gdrcd-muted text-xs">Mittenti con piu' messaggi inviati (esclusi messaggi di sistema).</p>
            </div>
            <div class="gdrcd-card-body">
                <div class="relative h-64">
                    <canvas id="chart-top-pgs"></canvas>
                </div>
            </div>
        </div>

    </section>

</div>

<!-- Chart.js standalone via CDN: caricato SOLO per questa pagina -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
(function () {
    'use strict';

    var ACCENT       = '#a47e3b';
    var ACCENT_SOFT  = 'rgba(164, 126, 59, 0.18)';
    var ACCENT_RING  = 'rgba(164, 126, 59, 0.55)';
    var BAR_PALETTE  = [
        '#a47e3b', '#c89958', '#7a5f2a', '#d4b07a', '#8a6b30',
        '#b89055', '#5d4720', '#e0c79a', '#a08055', '#705430'
    ];

    var root = document.getElementById('gdrcd-dashboard-root');
    if (!root) return;
    var endpoint = root.getAttribute('data-endpoint') || '/api/admin-metrics.inc.php';

    var statusEl = document.getElementById('gdrcd-dashboard-status');
    var statusMsg = document.getElementById('gdrcd-dashboard-status-msg');

    function showStatus(msg, kind) {
        if (!statusEl) return;
        statusEl.classList.remove('hidden', 'gdrcd-alert-warning', 'gdrcd-alert-error', 'gdrcd-alert-info');
        statusEl.classList.add(kind || 'gdrcd-alert-warning');
        if (statusMsg) statusMsg.textContent = msg;
    }
    function hideStatus() {
        if (statusEl) statusEl.classList.add('hidden');
    }

    function setMetric(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = (value == null ? '0' : String(value));
    }

    // Etichette compatte "dd/mm" per gli assi temporali.
    function shortDay(iso) {
        if (!iso) return '';
        var parts = String(iso).split('-');
        if (parts.length < 3) return iso;
        return parts[2] + '/' + parts[1];
    }

    // Tronca etichette troppo lunghe (nomi mappe/PG) per leggibilita'.
    function truncate(str, max) {
        str = String(str || '');
        if (str.length <= max) return str;
        return str.substring(0, max - 1) + '…';
    }

    function lineChart(canvasId, labels, data, label) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !window.Chart) return null;
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: ACCENT,
                    backgroundColor: ACCENT_SOFT,
                    borderWidth: 2,
                    pointRadius: 2,
                    pointBackgroundColor: ACCENT,
                    pointBorderColor: ACCENT,
                    tension: 0.25,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { intersect: false, mode: 'index' }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    function barChart(canvasId, labels, data, label, opts) {
        opts = opts || {};
        var ctx = document.getElementById(canvasId);
        if (!ctx || !window.Chart) return null;

        // Per barre orizzontali (top N) usiamo colori palette; per le
        // serie temporali a barre usiamo un solo colore.
        var bg, border;
        if (opts.palette) {
            bg = data.map(function (_, i) { return BAR_PALETTE[i % BAR_PALETTE.length]; });
            border = bg;
        } else {
            bg = ACCENT_SOFT;
            border = ACCENT;
        }

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: bg,
                    borderColor: border,
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 32
                }]
            },
            options: {
                indexAxis: opts.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { intersect: false, mode: 'index' }
                },
                scales: {
                    x: {
                        grid: { display: !!opts.horizontal },
                        beginAtZero: !!opts.horizontal,
                        ticks: { precision: 0 }
                    },
                    y: {
                        grid: { display: !opts.horizontal },
                        beginAtZero: !opts.horizontal,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    function render(payload) {
        hideStatus();

        var c = payload.counts || {};
        setMetric('metric-total-pg',        c.total_pg);
        setMetric('metric-active-24h',      c.active_24h);
        setMetric('metric-new-week',        c.new_week);
        setMetric('metric-msgs-7d',         c.msgs_7d);
        setMetric('metric-pending-reports', c.pending_reports);

        var presence = payload.presence_30d || [];
        lineChart(
            'chart-presence-30d',
            presence.map(function (p) { return shortDay(p.day); }),
            presence.map(function (p) { return p.count; }),
            'PG distinti'
        );

        var msgs = payload.msgs_30d || [];
        barChart(
            'chart-msgs-30d',
            msgs.map(function (p) { return shortDay(p.day); }),
            msgs.map(function (p) { return p.count; }),
            'Messaggi',
            { horizontal: false, palette: false }
        );

        var chats = payload.top_chats_30d || [];
        barChart(
            'chart-top-chats',
            chats.map(function (p) { return truncate(p.nome, 24); }),
            chats.map(function (p) { return p.count; }),
            'Messaggi',
            { horizontal: true, palette: true }
        );

        var pgs = payload.top_pgs_30d || [];
        barChart(
            'chart-top-pgs',
            pgs.map(function (p) { return truncate(p.nome, 24); }),
            pgs.map(function (p) { return p.count; }),
            'Messaggi inviati',
            { horizontal: true, palette: true }
        );
    }

    function load() {
        showStatus('Caricamento dati in corso...', 'gdrcd-alert-info');

        fetch(endpoint, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                return resp.json();
            })
            .then(function (data) {
                render(data);
            })
            .catch(function (err) {
                showStatus(
                    'Errore nel caricamento delle metriche: ' + (err && err.message ? err.message : 'sconosciuto'),
                    'gdrcd-alert-error'
                );
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
</script>
