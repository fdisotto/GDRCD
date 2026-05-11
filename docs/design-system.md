# GDRCD Design System

Single source of truth per UI di GDRCD. Obiettivo: coerenza visiva tra installer, login, homepage, scheda, chat, mappa, forum.

> **Versione corrente: LIGHT.** La versione DARK arriverà come tema alternativo (vedi sezione Roadmap a fondo pagina). Tutto il sistema è pensato per essere "tematizzabile" senza riscrivere componenti.

---

## 1. Stack

- **Tailwind CSS 3.4** standalone (no Node, no CDN). Output minified in `themes/tailwind/output.css` generato in fase di build Docker (multi-stage).
- **Font**: `Cinzel` (display, headings) + `Inter` (sans, body) caricati da Google Fonts.
- **Tokens**: definiti in `tailwind.config.js`, esposti come utilities Tailwind (`bg-gdrcd-*`, `text-gdrcd-*`, ecc.).
- **Componenti riusabili**: classi `@layer components` in `themes/tailwind/input.css` con prefisso `gdrcd-`.

---

## 2. Token

### 2.1 Colori (light)

| Token                     | Hex        | Uso                                       |
|---------------------------|-----------:|-------------------------------------------|
| `gdrcd-bg`                | `#f8f7f4`  | sfondo pagina (parchment caldo)           |
| `gdrcd-panel`             | `#ffffff`  | superficie card / pannelli                |
| `gdrcd-panel-alt`         | `#f1ede3`  | superficie alternata (header card, hover) |
| `gdrcd-border`            | `#e5e0d4`  | bordo hairline standard                   |
| `gdrcd-border-strong`     | `#cfc8b6`  | bordo enfatizzato                         |
| `gdrcd-text`              | `#1f2937`  | testo primario                            |
| `gdrcd-text-soft`         | `#374151`  | testo secondario                          |
| `gdrcd-muted`             | `#6b7280`  | label, helper, didascalie                 |
| `gdrcd-subtle`            | `#9ca3af`  | placeholder, icone disabled               |
| `gdrcd-accent`            | `#a47e3b`  | brand (oro caldo, GDR/medieval)           |
| `gdrcd-accent-hover`      | `#8a6831`  | accent pressed/hover                      |
| `gdrcd-accent-soft`       | `#f3ead4`  | tinta tenue dell'accent (badge, icone)    |
| `gdrcd-accent-ring`       | `#d6b873`  | focus ring                                |
| `gdrcd-success` / `-soft` | `#15803d` / `#dcfce7` | feedback positivo            |
| `gdrcd-error`   / `-soft` | `#b91c1c` / `#fee2e2` | errori, conferme distruttive |
| `gdrcd-warning` / `-soft` | `#b45309` / `#fef3c7` | attenzione                   |
| `gdrcd-info`    / `-soft` | `#1d4ed8` / `#dbeafe` | informativi                  |

### 2.2 Tipografia

| Ruolo            | Classe         | Tag tipico   |
|------------------|----------------|--------------|
| Heading 1        | `gdrcd-h1`     | `<h1>`       |
| Heading 2        | `gdrcd-h2`     | `<h2>`       |
| Heading 3        | `gdrcd-h3`     | `<h3>`       |
| Eyebrow / kicker | `gdrcd-eyebrow`| `<span>`     |
| Prose corrente   | `gdrcd-prose`  | `<p>`        |
| Testo muted      | `gdrcd-muted`  | `<p>`/`<span>` |

Regole:
- Headings sempre in `font-display` (Cinzel). Body in `font-sans` (Inter).
- Tracking (`tracking-wide`) applicato di default sui heading per dare aria.
- Mai usare `<h1>` senza `gdrcd-h1` (e analoghi).

### 2.3 Spaziatura, raggi, ombre

- Scale Tailwind di default (`p-4`, `gap-3`, ecc.). Niente valori arbitrari salvo eccezioni motivate.
- Raggio standard pannelli: `rounded-gdrcd` (= 0.75rem).
- Ombre: `shadow-gdrcd-card` (default), `shadow-gdrcd-elev` (modali, hero).

---

## 3. Componenti

Sempre preferire componenti a combinazioni utility ad-hoc. Nuovo componente → estendere `input.css` con `@layer components`, **non** copiare le utility nelle view.

### 3.1 Layout

```html
<div class="gdrcd-shell">
    <div class="gdrcd-container">
        ...
    </div>
</div>
```

`gdrcd-shell` impone min-height schermo, sfondo, centramento. `gdrcd-container` (max-w 3xl) o `gdrcd-container-sm` (max-w xl).

### 3.2 Card

```html
<section class="gdrcd-card">
    <header class="gdrcd-card-header">
        <h2 class="gdrcd-h2">Titolo</h2>
    </header>
    <div class="gdrcd-card-body">
        <p class="gdrcd-prose">Contenuto.</p>
    </div>
</section>
```

Variante con ombra più forte: `gdrcd-card-elev` al posto di `gdrcd-card`.

### 3.3 Bottoni

| Classe                  | Quando                                            |
|-------------------------|---------------------------------------------------|
| `gdrcd-btn-primary`     | Azione principale della pagina                    |
| `gdrcd-btn-secondary`   | Azione secondaria                                 |
| `gdrcd-btn-ghost`       | Annulla, link-like, navigazione laterale          |
| `gdrcd-btn-danger`      | Azioni distruttive (cancella, kick, ban)          |

Una sola `btn-primary` per vista. Per ordini ravvicinati: primary a destra, ghost a sinistra (su mobile invertire: primary in basso).

### 3.4 Form

```html
<label class="gdrcd-label" for="username">Utente</label>
<input class="gdrcd-input" id="username" name="username" type="text">
<p class="gdrcd-help">Aiutino sotto al campo.</p>
```

Input gestiscono focus ring tramite token `gdrcd-accent-ring`. Niente outline custom su singoli campi.

### 3.5 Alert

```html
<div class="gdrcd-alert-success">Tutto ok.</div>
<div class="gdrcd-alert-error">Qualcosa non va.</div>
<div class="gdrcd-alert-warning">Attenzione.</div>
<div class="gdrcd-alert-info">Per tua info.</div>
```

### 3.6 Link

- `gdrcd-link`: link in body text (sottolineato, accent).
- `gdrcd-link-quiet`: link di servizio (ritorno, breadcrumb).

### 3.7 Badge

`gdrcd-badge-neutral|accent|success|error` per chip stato/etichetta.

### 3.8 Sidebar widget

```html
<div class="gdrcd-widget">
    <div class="gdrcd-widget-title">Online</div>
    <div class="gdrcd-widget-body">...</div>
</div>
```

Variante navigazione:

```html
<nav class="gdrcd-widget" aria-label="Sezioni">
    <div class="gdrcd-widget-title">Esplora</div>
    <div class="gdrcd-nav-list">
        <a href="...">Iscrizione</a>
        <a href="...">Regolamento</a>
    </div>
</nav>
```

### 3.9 Stat list (chiave/valore)

```html
<table class="gdrcd-stat-list">
    <tr><td>Personaggi</td><td>123</td></tr>
    <tr><td>Online</td><td>4</td></tr>
</table>
```

### 3.10 Top bar / Footer pagina

```html
<header class="gdrcd-topbar">
    <div class="gdrcd-topbar-inner">
        <div>
            <h1 class="gdrcd-brand"><a href="/">Titolo</a></h1>
            <div class="gdrcd-brand-subtitle">Sottotitolo</div>
        </div>
        <form class="gdrcd-login-inline">...</form>
    </div>
</header>

<footer class="gdrcd-page-footer">
    <div class="gdrcd-page-footer-inner">© 2026</div>
</footer>
```

`gdrcd-login-inline` rende campi label+input affiancati per la barra superiore della homepage.

### 3.11 Stepper

Wizard a fasi numerate (es. iscrizione).

```html
<ol class="gdrcd-stepper" aria-label="Fasi">
    <li class="gdrcd-stepper-item is-done">
        <span class="gdrcd-stepper-circle">1</span>
        <span class="gdrcd-stepper-label">Condizioni</span>
    </li>
    <li class="gdrcd-stepper-divider" aria-hidden="true"></li>
    <li class="gdrcd-stepper-item is-active">
        <span class="gdrcd-stepper-circle">2</span>
        <span class="gdrcd-stepper-label">Dati</span>
    </li>
    <li class="gdrcd-stepper-divider" aria-hidden="true"></li>
    <li class="gdrcd-stepper-item">
        <span class="gdrcd-stepper-circle">3</span>
        <span class="gdrcd-stepper-label">Riepilogo</span>
    </li>
</ol>
```

Stati: nessuno (futuro), `is-active` (corrente), `is-done` (passato).

### 3.12 Icon Circle

```html
<span class="gdrcd-icon-circle">
    <svg class="w-6 h-6" ...></svg>
</span>
```

Per hero icons sopra a heading (es. installer, errore 404).

---

## 4. Convenzioni

1. **Naming**: tutti i componenti prefissati `gdrcd-`. Token colore `gdrcd-<ruolo>`.
2. **Mai `style=""` inline** salvo valori dinamici impossibili da rappresentare in classi (es. `width` calcolata server-side).
3. **Niente CSS file per pagina**. Le pagine devono comporre solo classi del design system. Se manca un componente → si aggiunge a `input.css`, non a un CSS di pagina.
4. **PHP rendering**: passare al template solo dati, non classi. Es: `$status === 'success' ? 'gdrcd-alert-success' : 'gdrcd-alert-error'` è OK; costruire classi via concatenazione di token utility è da evitare (Tailwind purge non vede stringhe spezzate).
5. **Stringhe classi complete**: per essere riconosciute dal JIT scanner, le classi vanno scritte intere nel sorgente. No `"bg-gdrcd-" . $color`.
6. **Mobile-first**: design parte da mobile. `md:` per tablet+, `lg:` per desktop.
7. **Accessibilità**: 
   - `<button>` per azioni, `<a>` per navigazione.
   - `aria-label` su bottoni icon-only.
   - Contrasto minimo AA: testo 4.5:1, headings 3:1.

---

## 5. Workflow

### Sviluppo

Servizio `tailwind` in `docker-compose.override.yml` esegue uno script (`docker/tailwind-watch.sh`) che usa `inotifywait` + `tailwindcss` per ricompilare `themes/tailwind/output.css` ad ogni modifica di `.php`/`.js`/`.css`.

Bind-mount sorgente sul container `web` → modifiche live, senza rebuild image.

```bash
docker compose up -d            # parte tutto, watcher incluso
docker compose logs -f tailwind # vedere ricompilazioni
```

### Build immagine prod

In assenza dell'override (`docker compose -f docker-compose.yml up`), lo stage `tailwind-builder` del `Dockerfile` produce `output.css` minified durante `docker compose build`.

### Aggiungere un componente

1. Aggiungi la regola in `themes/tailwind/input.css` dentro `@layer components`.
2. Documenta qui (sezione 3) con snippet di esempio.
3. Refactora le view che ripetevano la combinazione utility.

---

## 6. Migrazione pagine legacy

Pagine attualmente con CSS custom da `themes/<theme>/...` saranno rimpiazzate gradualmente. Ordine consigliato:

1. ✅ `installer.php`
2. ✅ `index.php` + `pages/homepage/*` (homepage layout, sidebar widgets, login bar inline, stats, reset password)
3. ✅ `pages/homepage/iscrizione.inc.php` (form 4-step con stepper, summary, welcome)
4. ✅ `pages/homepage/user_razze.inc.php` + `pages/user_razze.inc.php` (grid card razze + bonus badge)
5. ✅ `pages/mappaclick.inc.php` (mappa cliccabile + vicinato + pannello master mobile/meteo)
6. ✅ App shell loggato: `header.inc.php` (no più CSS legacy), `layouts/left-right_frames.php` (topbar + sidebar 3-col), `gdrcd_controllo_sessione` (schermata sessione scaduta)
7. ⏳ Side widget modules: `info_location`, `frame_messages`, `frame_forum`, `link_menu`, `frame_presenti` — wrappati in `gdrcd-widget` ma markup interno ancora legacy
8. ⏳ `login.php` / `protezione.php`
4. ⏳ Layout principale (`layouts/`)
5. ⏳ Scheda personaggio
6. ⏳ Chat
7. ⏳ Forum

Durante la migrazione, le view migrate **non** linkano i CSS legacy specifici, restano solo `output.css` di Tailwind.

---

## 7. Roadmap dark mode

Quando si attiva il dark mode:

1. Aggiungere `darkMode: 'class'` in `tailwind.config.js`.
2. Definire varianti `dark:` direttamente nei componenti `@layer components` (es. `dark:bg-gdrcd-bg-dark`).
3. Aggiungere set di token "dark" (`gdrcd-bg-dark`, `gdrcd-panel-dark`, ecc.) in config.
4. Toggle controllato da `<html class="dark">` impostato via preferenza utente (cookie/localStorage) + `prefers-color-scheme` come default.

L'API dei componenti (`gdrcd-card`, `gdrcd-btn-primary`, ecc.) **non cambia**: il dark mode è una skin sotto le classi esistenti.
