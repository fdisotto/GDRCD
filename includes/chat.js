/**
 * GDRCD chat polling (vanilla JS).
 *
 * Sostituisce l'iframe storico che faceva meta-refresh su ref_header.inc.php
 * per renderizzare i nuovi messaggi nella chat. Polla l'endpoint JSON
 * /api/chat.inc.php?after=<lastId>, costruisce in modo sicuro i nodi DOM per
 * ogni messaggio (textContent, niente innerHTML su dati utente) e li
 * appende al container #pagina_chat.
 *
 * Configurazione tramite data-* sul container:
 *   - data-poll-url       : endpoint JSON (default /api/chat.inc.php)
 *   - data-poll-interval  : intervallo in ms (default 4000)
 *   - data-from-bottom    : "1" se i messaggi vanno in cima (ordine invertito)
 *   - data-login          : login dell'utente corrente (per chat_me)
 *   - data-permessi       : livello permessi (per spy chat private)
 *   - data-spy-private    : "1" se spyprivaterooms attivo
 *   - data-chat-avatar    : "1" se gli avatar di chat sono attivi
 *   - data-chat-icons     : "1" se le icone razza/genere sono attive
 *   - data-avatar-link    : "1" se gli avatar sono link a scheda
 *   - data-avatar-popup   : "1" se il link avatar apre popup
 *   - data-theme          : tema corrente (per path icone razza)
 *   - data-msg-whisper-by : i18n "ti sussurra"
 *   - data-msg-whisper-to : i18n "Sussurri a"
 *   - data-msg-whisper-from-to : i18n "sussurra a" (spy)
 *
 * Lo stato lastId e' mantenuto su window.gdrcdChatLastId in modo che, se
 * eventualmente il form di invio messaggio finisce ancora nell'iframe legacy
 * (form target chat_frame) e qualcosa popolasse #pagina_chat, possiamo
 * comunque resincronizzare.
 *
 * Dedup: ogni riga emessa ha data-msg-id; alla scrittura controlliamo che
 * l'id non sia gia' presente nel DOM, evitando duplicati.
 *
 * TODO: implementare un parser BBCode/colori (oggi `gdrcd_chatcolor` /
 *       `gdrcd_chatme` sono PHP-side). Per ora il testo e' renderizzato come
 *       plaintext via textContent (sicuro contro XSS).
 *
 * @see api/chat.inc.php
 * @see pages/frame_chat.inc.php
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    function init() {
        var container = document.getElementById('pagina_chat');
        if (!container) return;

        var cfg = {
            url:        container.getAttribute('data-poll-url')      || '/api/chat.inc.php',
            interval:   parseInt(container.getAttribute('data-poll-interval'), 10) || 4000,
            fromBottom: container.getAttribute('data-from-bottom') === '1',
            login:      container.getAttribute('data-login') || '',
            permessi:   parseInt(container.getAttribute('data-permessi'), 10) || 0,
            spyPrivate: container.getAttribute('data-spy-private') === '1',
            chatAvatar: container.getAttribute('data-chat-avatar') === '1',
            chatIcons:  container.getAttribute('data-chat-icons') === '1',
            avatarLink: container.getAttribute('data-avatar-link') === '1',
            avatarPopup:container.getAttribute('data-avatar-popup') === '1',
            theme:      container.getAttribute('data-theme') || '',
            msg: {
                whisperBy:     container.getAttribute('data-msg-whisper-by')      || 'ti sussurra',
                whisperTo:     container.getAttribute('data-msg-whisper-to')      || 'Sussurri a',
                whisperFromTo: container.getAttribute('data-msg-whisper-from-to') || 'sussurra a'
            }
        };

        // Costanti coerenti con includes/constant_values.inc.php
        var MODERATOR = 3;

        // Stato globale (vedi nota in header).
        if (typeof window.gdrcdChatLastId !== 'number') {
            window.gdrcdChatLastId = 0;
        }

        var timer = null;
        var inFlight = false;
        var paused  = false;

        function setEl(tag, cls) {
            var el = document.createElement(tag);
            if (cls) el.className = cls;
            return el;
        }

        function formatTime(ora) {
            // L'API restituisce "YYYY-MM-DD HH:MM:SS"; estraiamo HH:MM.
            if (!ora) return '';
            var m = String(ora).match(/(\d{2}):(\d{2})/);
            return m ? m[1] + ':' + m[2] : '';
        }

        function raceIcon(imgsField) {
            if (!cfg.chatIcons || !imgsField) return null;
            var parts = String(imgsField).split(';');
            var raceImg = parts[1] || '';
            if (!raceImg || raceImg === 'standard_razza.png') return null;
            var img = setEl('img', 'w-4 h-4 object-contain shrink-0');
            img.src = 'themes/' + cfg.theme + '/imgs/races/' + raceImg;
            img.alt = '';
            return img;
        }

        function genderIcon(imgsField) {
            if (!cfg.chatIcons || !imgsField) return null;
            var parts = String(imgsField).split(';');
            var sex = (parts[0] || '').toLowerCase();
            if (sex !== 'm' && sex !== 'f') return null;
            // Glifo unicode minimale: il render fedele degli SVG sta in PHP
            // (includes/icons.inc.php). Qui evitiamo di duplicare i path SVG;
            // se servisse render identico si potra' estrarre in un endpoint.
            var span = setEl('span', sex === 'm' ? 'text-sky-600' : 'text-pink-600');
            span.textContent = sex === 'm' ? '♂' : '♀';
            return span;
        }

        function iconsSpan(imgsField) {
            if (!cfg.chatIcons) return null;
            var race   = raceIcon(imgsField);
            var gender = genderIcon(imgsField);
            if (!race && !gender) return null;
            var span = setEl('span', 'chat_icons inline-flex items-center gap-1 align-middle');
            if (race)   span.appendChild(race);
            if (gender) span.appendChild(gender);
            return span;
        }

        function avatarNode(msg) {
            if (!cfg.chatAvatar || !msg.url_img_chat) return null;
            var img = setEl('img', 'chat_avatar');
            img.src = msg.url_img_chat;
            img.alt = '';
            if (!cfg.avatarLink) return img;

            var a = setEl('a');
            if (cfg.avatarPopup) {
                a.href = 'javascript:void(0);';
                a.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    if (typeof window.modalWindow === 'function') {
                        window.modalWindow('scheda', 'Scheda di ' + msg.mittente,
                            'popup.php?page=scheda&pg=' + encodeURIComponent(msg.mittente));
                    }
                });
            } else {
                a.href = 'main.php?page=scheda&pg=' + encodeURIComponent(msg.mittente);
            }
            a.appendChild(img);
            return a;
        }

        function nameNode(msg, withColon) {
            var span = setEl('span', 'chat_name');
            var a = setEl('a');
            a.href = '#';
            a.textContent = msg.mittente;
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                var tag = document.getElementById('tag');
                var type = document.getElementById('type');
                var message = document.getElementById('message');
                if (tag) tag.value = msg.mittente;
                if (type && type.options && type.options.length > 2) {
                    type.options[2].selected = true;
                }
                if (message) message.focus();
            });
            span.appendChild(a);

            if (msg.destinatario) {
                var tagSpan = setEl('span', 'chat_tag');
                tagSpan.textContent = ' [' + msg.destinatario + ']';
                span.appendChild(tagSpan);
            }
            // Per il parlato il ":" è in coda al nome (come faceva ref_header).
            span.appendChild(document.createTextNode(withColon ? ': ' : ' '));
            return span;
        }

        function msgNode(testo, extraClass) {
            // TODO: parser BBCode/colori (gdrcd_chatcolor / gdrcd_chatme).
            // Per ora plaintext via textContent (no XSS).
            var span = setEl('span', 'chat_msg' + (extraClass ? ' ' + extraClass : ''));
            span.textContent = testo || '';
            return span;
        }

        function timeNode(ora) {
            var span = setEl('span', 'chat_time');
            span.textContent = formatTime(ora);
            return span;
        }

        /**
         * Costruisce il DOM di una riga chat in base al tipo del messaggio.
         * Tipi: P=parlato, A=azione, S=sussurro, N=PNG, M=master, I=immagine,
         *       C=skill check, D=dado, O=uso oggetto.
         */
        function buildRow(msg) {
            var row = setEl('div', 'chat_row_' + msg.tipo);
            row.setAttribute('data-msg-id', String(msg.id));

            switch (msg.tipo) {
                case 'A':
                case 'P': {
                    var av = avatarNode(msg);
                    if (av) row.appendChild(av);
                    row.appendChild(timeNode(msg.ora));
                    var ic = iconsSpan(msg.imgs);
                    if (ic) row.appendChild(ic);
                    row.appendChild(nameNode(msg, msg.tipo === 'P'));
                    row.appendChild(msgNode(msg.testo));
                    if (cfg.chatAvatar) {
                        var br = document.createElement('br');
                        br.style.clear = 'both';
                        row.appendChild(br);
                    }
                    break;
                }
                case 'S': {
                    // Filtro lato client: il server-side già limita ai
                    // partecipanti, ma per robustezza confermiamo.
                    var amDest = msg.destinatario && msg.destinatario === cfg.login;
                    var amSrc  = msg.mittente && msg.mittente === cfg.login;
                    var canSpy = cfg.spyPrivate && cfg.permessi >= MODERATOR;
                    if (!amDest && !amSrc && !canSpy) return null;

                    var name = setEl('span', 'chat_name');
                    if (amDest) {
                        name.textContent = msg.mittente + ' ' + cfg.msg.whisperBy + ': ';
                    } else if (amSrc) {
                        name.textContent = cfg.msg.whisperTo + ' ' + (msg.destinatario || '') + ': ';
                    } else {
                        name.textContent = msg.mittente + ' ' + cfg.msg.whisperFromTo + ' ' + (msg.destinatario || '') + ' ';
                    }
                    row.appendChild(name);
                    row.appendChild(msgNode(msg.testo));
                    break;
                }
                case 'N': {
                    row.appendChild(timeNode(msg.ora));
                    var dst = setEl('span', 'chat_name');
                    dst.textContent = (msg.destinatario || '') + ' ';
                    row.appendChild(dst);
                    row.appendChild(msgNode(msg.testo));
                    break;
                }
                case 'M': {
                    var master = setEl('span', 'chat_master');
                    master.textContent = msg.testo || '';
                    row.appendChild(master);
                    break;
                }
                case 'I': {
                    var img = setEl('img', 'chat_img');
                    img.src = msg.testo || '';
                    img.alt = '';
                    row.appendChild(img);
                    break;
                }
                case 'C':
                case 'D':
                case 'O': {
                    row.appendChild(timeNode(msg.ora));
                    row.appendChild(msgNode(msg.testo));
                    break;
                }
                default:
                    row.appendChild(msgNode(msg.testo));
            }
            return row;
        }

        function alreadyRendered(id) {
            return !!container.querySelector('[data-msg-id="' + id + '"]');
        }

        function appendMessages(messages) {
            if (!Array.isArray(messages) || messages.length === 0) return false;
            var inserted = false;
            for (var i = 0; i < messages.length; i++) {
                var m = messages[i];
                if (!m || typeof m.id === 'undefined') continue;
                if (alreadyRendered(m.id)) continue;
                var row = buildRow(m);
                if (!row) continue;
                if (cfg.fromBottom) {
                    container.insertBefore(row, container.firstChild);
                } else {
                    container.appendChild(row);
                }
                inserted = true;
            }
            if (inserted) {
                if (cfg.fromBottom) {
                    container.scrollTop = 0;
                } else {
                    container.scrollTop = container.scrollHeight;
                }
            }
            return inserted;
        }

        function poll() {
            if (inFlight || paused) return;
            inFlight = true;
            var lastId = window.gdrcdChatLastId | 0;
            fetch(cfg.url + '?after=' + lastId, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            }).then(function (res) {
                if (res.status === 401) {
                    window.location.href = 'index.php';
                    return null;
                }
                if (!res.ok) return null;
                return res.json();
            }).then(function (data) {
                inFlight = false;
                if (!data) return;
                appendMessages(data.messages);
                if (typeof data.last_id === 'number' && data.last_id > window.gdrcdChatLastId) {
                    window.gdrcdChatLastId = data.last_id;
                }
            }).catch(function () {
                inFlight = false;
            });
        }

        function start() {
            stop();
            // Prima poll immediata, poi a intervalli regolari.
            poll();
            timer = window.setInterval(poll, cfg.interval);
        }

        function stop() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                paused = true;
                stop();
            } else {
                paused = false;
                start();
            }
        });

        // API minima esposta (utile per debug e per il form di invio: dopo
        // un submit possiamo forzare un poll per ridurre la latenza visiva).
        window.GDRCDChat = {
            pollNow: poll,
            stop: stop,
            start: start,
            config: cfg
        };

        start();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
