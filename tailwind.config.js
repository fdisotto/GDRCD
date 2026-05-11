/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './*.php',
        './pages/**/*.php',
        './includes/**/*.php',
        './layouts/**/*.php',
        './themes/**/*.php',
        './plugins/**/*.php',
        './includes/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                gdrcd: {
                    // Surface
                    bg:          '#f8f7f4', // page background (parchment)
                    panel:       '#ffffff', // cards / panels
                    'panel-alt': '#f1ede3', // subtle alternate panel
                    border:      '#e5e0d4', // hairline borders
                    'border-strong': '#cfc8b6',

                    // Text
                    text:        '#1f2937',
                    'text-soft': '#374151',
                    muted:       '#6b7280',
                    subtle:      '#9ca3af',

                    // Brand accent (deep warm gold)
                    accent:        '#a47e3b',
                    'accent-hover':'#8a6831',
                    'accent-soft': '#f3ead4',
                    'accent-ring': '#d6b873',

                    // Semantic states
                    success:        '#15803d',
                    'success-soft': '#dcfce7',
                    error:          '#b91c1c',
                    'error-soft':   '#fee2e2',
                    warning:        '#b45309',
                    'warning-soft': '#fef3c7',
                    info:           '#1d4ed8',
                    'info-soft':    '#dbeafe',

                    // ----- DARK THEME -----
                    // Surface (warm dark, slight brown tone to match parchment vibe)
                    'dark-bg':           '#1a1816',
                    'dark-panel':        '#26221d',
                    'dark-panel-alt':    '#2f2a24',
                    'dark-border':       '#3d362e',
                    'dark-border-strong':'#544a3e',

                    // Text (high contrast, off-white warm tones)
                    'dark-text':       '#e8e3d8',
                    'dark-text-soft':  '#bdb7a8',
                    'dark-muted':      '#8a8377',
                    'dark-subtle':     '#6a6457',

                    // Brand accent (brighter gold for AA contrast on dark)
                    'dark-accent':       '#d6b066',
                    'dark-accent-hover': '#e8c889',
                    'dark-accent-soft':  '#3a2f1c',
                    'dark-accent-ring':  '#a47e3b',

                    // Semantic states (lighter shades for dark bg)
                    'dark-success':      '#4ade80',
                    'dark-success-soft': '#14301f',
                    'dark-error':        '#f87171',
                    'dark-error-soft':   '#3a1818',
                    'dark-warning':      '#fbbf24',
                    'dark-warning-soft': '#3a2a10',
                    'dark-info':         '#60a5fa',
                    'dark-info-soft':    '#172a47',
                },
            },
            fontFamily: {
                display: ['Cinzel', 'serif'],
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                'gdrcd-card': '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06)',
                'gdrcd-elev': '0 4px 12px -2px rgb(0 0 0 / 0.08), 0 2px 6px -2px rgb(0 0 0 / 0.05)',
            },
            borderRadius: {
                'gdrcd': '0.75rem',
            },
        },
    },
    plugins: [],
};
