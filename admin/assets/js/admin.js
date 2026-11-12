/* O-CMS 2 — comportamento del pannello di amministrazione.
   Vanilla JS, caricato con defer. Nessun innerHTML con dati: solo
   createElement / textContent. Ogni funzione controlla che i suoi
   elementi esistano, così il file si può includere in qualsiasi vista. */
(function () {
    'use strict';

    var root = document.documentElement;
    var BASE = (document.body && document.body.getAttribute('data-base-url')) || '';
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ─── piccoli aiuti ───
    function el(tag, attrs, children) {
        var n = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                var v = attrs[k];
                if (v === null || v === undefined || v === false) return;
                if (k === 'class') n.className = v;
                else if (k === 'text') n.textContent = v;
                else n.setAttribute(k, v === true ? '' : String(v));
            });
        }
        (children || []).forEach(function (c) {
            if (c === null || c === undefined) return;
            n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return n;
    }
    function svgIcon(paths) {
        var NS = 'http://www.w3.org/2000/svg';
        var s = document.createElementNS(NS, 'svg');
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('fill', 'none');
        s.setAttribute('stroke', 'currentColor');
        s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round');
        s.setAttribute('stroke-linejoin', 'round');
        s.setAttribute('aria-hidden', 'true');
        paths.forEach(function (d) {
            var p = document.createElementNS(NS, 'path');
            p.setAttribute('d', d);
            s.appendChild(p);
        });
        return s;
    }
    function store(kind) {
        try { return kind === 'session' ? window.sessionStorage : window.localStorage; } catch (e) { return null; }
    }
    function lsGet(key, kind) { var s = store(kind); if (!s) return null; try { return s.getItem(key); } catch (e) { return null; } }
    function lsSet(key, val, kind) { var s = store(kind); if (!s) return false; try { s.setItem(key, val); return true; } catch (e) { return false; } }
    function lsDel(key, kind) { var s = store(kind); if (!s) return; try { s.removeItem(key); } catch (e) {} }
    function isMac() { return /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || ''); }
    function normalize(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function timeLabel(ts) {
        var d = new Date(ts);
        var now = new Date();
        var hm = pad(d.getHours()) + ':' + pad(d.getMinutes());
        if (d.toDateString() === now.toDateString()) return 'oggi alle ' + hm;
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' alle ' + hm;
    }
    function safeHref(u) {
        u = String(u || '');
        // solo percorsi interni o http(s): niente javascript: e simili
        if (/^\/(?!\/)/.test(u) || /^https?:\/\//i.test(u)) return u;
        return null;
    }

    // ═══ SIDEBAR: gruppi richiudibili + modalità a icone ═══
    var sidebar = document.getElementById('admin-sidebar');
    var NAV_KEY = 'ocms-admin-nav';
    var SIDEBAR_KEY = 'ocms-admin-sidebar';
    var desktopMQ = window.matchMedia('(min-width: 901px)');

    function isCollapsed() { return root.getAttribute('data-sidebar') === 'collapsed' && desktopMQ.matches; }

    function initSidebar() {
        if (!sidebar) return;
        var groups = sidebar.querySelectorAll('details[data-group]');
        groups.forEach(function (d) {
            // si memorizza solo l'apertura/chiusura fatta dall'utente, non quella automatica
            var summary = d.querySelector('summary');
            if (summary) summary.addEventListener('click', function () { d._byUser = true; });
            d.addEventListener('toggle', function () {
                if (!d._byUser) return;
                d._byUser = false;
                if (isCollapsed()) return; // in modalità icone i gruppi sono forzati aperti
                var st = {};
                try { st = JSON.parse(lsGet(NAV_KEY) || '{}') || {}; } catch (e) { st = {}; }
                st[d.getAttribute('data-group')] = d.open;
                lsSet(NAV_KEY, JSON.stringify(st));
            });
        });

        var toggle = sidebar.querySelector('[data-sidebar-toggle]');
        function applyCollapsed(collapsed, save) {
            if (collapsed) root.setAttribute('data-sidebar', 'collapsed');
            else root.removeAttribute('data-sidebar');
            if (save) lsSet(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
            if (toggle) {
                toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
                var lbl = collapsed ? 'Espandi la barra laterale' : 'Riduci la barra laterale';
                toggle.setAttribute('aria-label', lbl);
                toggle.setAttribute('title', lbl);
            }
            syncCollapsedState();
        }
        function syncCollapsedState() {
            var c = isCollapsed();
            var st = {};
            try { st = JSON.parse(lsGet(NAV_KEY) || '{}') || {}; } catch (e) { st = {}; }
            groups.forEach(function (d) {
                if (c) d.open = true;
                else d.open = st[d.getAttribute('data-group')] !== false || d.hasAttribute('data-has-active');
            });
            sidebar.querySelectorAll('.nav-item[data-nav-label]').forEach(function (a) {
                if (c) a.setAttribute('title', a.getAttribute('data-nav-label'));
                else a.removeAttribute('title');
            });
        }
        if (toggle) {
            toggle.addEventListener('click', function () { applyCollapsed(!isCollapsed(), true); });
        }
        applyCollapsed(root.getAttribute('data-sidebar') === 'collapsed', false);
        if (desktopMQ.addEventListener) desktopMQ.addEventListener('change', syncCollapsedState);

        // la voce attiva resta visibile anche con molte voci
        var active = sidebar.querySelector('.nav-item.active');
        if (active && active.scrollIntoView) {
            var nav = sidebar.querySelector('.sidebar-nav');
            if (nav && active.offsetTop > nav.clientHeight - 60) nav.scrollTop = active.offsetTop - nav.clientHeight / 2;
        }
    }

    // ═══ MENU MOBILE ═══
    function initMobileMenu() {
        var openBtn = document.querySelector('[data-menu-toggle]');
        var main = document.getElementById('admin-main');
        if (!sidebar || !openBtn) return;
        var lastFocus = null;

        function isOpen() { return root.classList.contains('nav-open'); }
        function open() {
            lastFocus = document.activeElement;
            root.classList.add('nav-open');
            openBtn.setAttribute('aria-expanded', 'true');
            if (main) main.setAttribute('inert', '');
            var first = sidebar.querySelector('.nav-item.active') || sidebar.querySelector('a, button');
            setTimeout(function () { if (first) first.focus(); }, 30);
        }
        function close(restore) {
            if (!isOpen()) return;
            root.classList.remove('nav-open');
            openBtn.setAttribute('aria-expanded', 'false');
            if (main) main.removeAttribute('inert');
            if (restore !== false) (lastFocus && lastFocus.focus ? lastFocus : openBtn).focus();
        }
        openBtn.addEventListener('click', function () { isOpen() ? close() : open(); });
        document.querySelectorAll('[data-menu-close]').forEach(function (b) {
            b.addEventListener('click', function () { close(); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) { e.preventDefault(); close(); }
            // focus tenuto dentro il menu aperto
            if (e.key === 'Tab' && isOpen()) {
                var f = Array.prototype.filter.call(
                    sidebar.querySelectorAll('a[href], button:not([disabled]), summary'),
                    function (x) { return x.offsetParent !== null; });
                if (!f.length) return;
                var firstEl = f[0], lastEl = f[f.length - 1];
                if (e.shiftKey && document.activeElement === firstEl) { e.preventDefault(); lastEl.focus(); }
                else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); firstEl.focus(); }
            }
        });
        // tornando a desktop il menu off-canvas non deve restare "aperto"
        if (desktopMQ.addEventListener) desktopMQ.addEventListener('change', function (m) { if (m.matches) close(false); });
    }

    // ═══ TOAST (messaggi flash) ═══
    function initToasts() {
        var toasts = document.querySelectorAll('[data-toast]');
        toasts.forEach(function (t) {
            var msg = t.querySelector('.toast-msg');
            // riscrive il testo dopo il primo disegno: così i lettori di schermo lo annunciano
            if (msg) {
                var txt = msg.textContent;
                msg.textContent = '';
                requestAnimationFrame(function () { setTimeout(function () { msg.textContent = txt; }, 60); });
            }
            var closeBtn = t.querySelector('[data-toast-close]');
            var timer = null, remaining = parseInt(t.getAttribute('data-autodismiss') || '0', 10), started = 0;
            var bar = t.querySelector('.toast-timer');

            function dismiss() {
                if (t.classList.contains('is-leaving')) return;
                clearTimeout(timer);
                if (reduceMotion) { t.remove(); return; }
                t.classList.add('is-leaving');
                t.addEventListener('animationend', function () { t.remove(); }, { once: true });
                setTimeout(function () { if (t.parentNode) t.remove(); }, 400);
            }
            function start() {
                if (!remaining) return;
                started = Date.now();
                timer = setTimeout(dismiss, remaining);
                if (bar && !reduceMotion) {
                    bar.style.animation = 'toast-timer ' + remaining + 'ms linear forwards';
                    bar.style.animationPlayState = 'running';
                }
            }
            function pause() {
                if (!remaining || !timer) return;
                clearTimeout(timer); timer = null;
                remaining = Math.max(800, remaining - (Date.now() - started));
                if (bar) bar.style.animationPlayState = 'paused';
            }
            function resume() {
                if (!remaining || timer) return;
                started = Date.now();
                timer = setTimeout(dismiss, remaining);
                if (bar) bar.style.animationPlayState = 'running';
            }
            if (closeBtn) closeBtn.addEventListener('click', dismiss);
            t.addEventListener('mouseenter', pause);
            t.addEventListener('mouseleave', resume);
            t.addEventListener('focusin', pause);
            t.addEventListener('focusout', resume);
            start();
        });
    }

    // ═══ BOTTONI DI INVIO: stato di caricamento ═══
    // Il submit arriva solo se la validazione HTML è passata. Il bottone NON viene
    // disabilitato (un bottone disabilitato non invierebbe il suo name/value,
    // es. status=draft|published): si blocca solo un secondo invio.
    function initSubmitLoading() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;
            if (form.getAttribute('target') && form.getAttribute('target') !== '_self') return;
            if (form.classList.contains('is-submitting')) { e.preventDefault(); return; }
            var btn = e.submitter || form.querySelector('[type="submit"], button:not([type])');
            // il controllo avviene dopo gli altri gestori (conferme, invii via fetch)
            setTimeout(function () {
                if (e.defaultPrevented) return;
                form.classList.add('is-submitting');
                form.setAttribute('aria-busy', 'true');
                if (btn && btn.classList && btn.classList.contains('btn')) btn.classList.add('is-loading');
                // rete di sicurezza: download, risposte 204, errori → il form torna usabile
                setTimeout(function () { resetForm(form); }, 12000);
            }, 0);
        });
        function resetForm(form) {
            form.classList.remove('is-submitting');
            form.removeAttribute('aria-busy');
            form.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); });
        }
        // tornando indietro (bfcache) i form devono essere di nuovo usabili
        window.addEventListener('pageshow', function (ev) {
            if (ev.persisted) document.querySelectorAll('form.is-submitting').forEach(resetForm);
        });
    }

    // ═══ PALETTE COMANDI ═══
    function initPalette() {
        var dlg = document.getElementById('cmdk');
        var input = document.getElementById('cmdk-input');
        var list = document.getElementById('cmdk-list');
        if (!dlg || !input || !list || typeof dlg.showModal !== 'function') {
            // senza <dialog>: il bottone porta alla ricerca avanzata
            document.querySelectorAll('[data-cmdk-open]').forEach(function (b) {
                b.addEventListener('click', function () { location.href = BASE + '/admin/search/full'; });
            });
            return;
        }

        var ICONS = {
            go: ['M5 12h14', 'M13 6l6 6-6 6'],
            plus: ['M12 5v14', 'M5 12h14'],
            site: ['M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6', 'M15 3h6v6', 'M10 14 21 3'],
            search: ['M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14z', 'M20 20l-3.5-3.5'],
            theme: ['M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z']
        };

        // voci: sezioni della sidebar + azioni
        function buildCommands() {
            var cmds = [
                { group: 'Azioni', label: 'Nuovo articolo', href: BASE + '/admin/articles/new', icon: 'plus', keywords: 'scrivi post blog crea' },
                { group: 'Azioni', label: 'Nuova pagina', href: BASE + '/admin/pages/new', icon: 'plus', keywords: 'crea' },
                { group: 'Azioni', label: 'Nuova lezione', href: BASE + '/admin/lessons/new', icon: 'plus', keywords: 'crea didattica' },
                { group: 'Azioni', label: 'Vai al sito', href: BASE + '/', icon: 'site', newTab: true, keywords: 'apri frontend pubblico' },
                { group: 'Azioni', label: 'Cambia tema chiaro/scuro', action: function () { if (window.OCMSTheme) window.OCMSTheme.toggle(); }, icon: 'theme', keywords: 'dark light notte giorno' }
            ];
            if (sidebar) {
                var seen = {};
                sidebar.querySelectorAll('.sidebar-nav a.nav-item').forEach(function (a) {
                    var label = a.getAttribute('data-nav-label') || a.textContent.trim();
                    var href = a.getAttribute('href');
                    if (!label || !href || seen[href]) return;
                    seen[href] = true;
                    var details = a.closest('details[data-group]');
                    var groupName = details ? details.querySelector('summary').textContent.trim() : '';
                    cmds.push({ group: 'Vai a', label: label, href: href, icon: 'go', hint: groupName, keywords: groupName });
                });
            }
            return cmds;
        }
        var commands = buildCommands();

        // filtro fuzzy: tutte le lettere in ordine, premiando inizi di parola e sequenze
        function fuzzy(query, text) {
            var q = normalize(query).replace(/\s+/g, '');
            var t = normalize(text);
            if (!q) return { score: 0, idx: [] };
            var idx = [], score = 0, ti = 0, prev = -2;
            for (var qi = 0; qi < q.length; qi++) {
                var found = -1;
                for (; ti < t.length; ti++) { if (t[ti] === q[qi]) { found = ti; break; } }
                if (found < 0) return null;
                var bonus = 1;
                if (found === 0 || /[\s\-_/]/.test(t[found - 1])) bonus += 3;
                if (found === prev + 1) bonus += 2;
                score += bonus;
                idx.push(found);
                prev = found;
                ti = found + 1;
            }
            score -= (t.length - q.length) * 0.02;
            if (t.indexOf(normalize(query).trim()) === 0) score += 5;
            return { score: score, idx: idx };
        }

        var items = [];     // voci visibili, in ordine
        var activeIdx = -1;
        var remote = [];    // risultati dalla ricerca contenuti
        var remoteFor = '';
        var fetchTimer = null, fetchCtrl = null;
        var opener = null;

        function labelWithMarks(text, idx) {
            var span = el('span', { class: 'cmdk-option-label' });
            if (!idx || !idx.length) { span.textContent = text; return span; }
            // gli indici valgono sul testo normalizzato: per testi con accenti scomposti si rinuncia
            if (normalize(text).length !== text.length) { span.textContent = text; return span; }
            var set = {}; idx.forEach(function (i) { set[i] = true; });
            var buf = '';
            for (var i = 0; i < text.length; i++) {
                if (set[i]) {
                    if (buf) { span.appendChild(document.createTextNode(buf)); buf = ''; }
                    span.appendChild(el('mark', { text: text[i] }));
                } else buf += text[i];
            }
            if (buf) span.appendChild(document.createTextNode(buf));
            return span;
        }

        function render() {
            var q = input.value.trim();
            while (list.firstChild) list.removeChild(list.firstChild);
            items = [];
            var scored = [];
            commands.forEach(function (c) {
                if (!q) { scored.push({ c: c, score: 0, idx: [] }); return; }
                var m = fuzzy(q, c.label);
                var mk = c.keywords ? fuzzy(q, c.keywords) : null;
                if (m) scored.push({ c: c, score: m.score + 2, idx: m.idx });
                else if (mk) scored.push({ c: c, score: mk.score - 2, idx: [] });
            });
            if (q) scored.sort(function (a, b) { return b.score - a.score; });

            var groups = q ? ['Risultati'] : ['Azioni', 'Vai a'];
            var byGroup = {};
            scored.forEach(function (s) {
                var g = q ? 'Risultati' : s.c.group;
                (byGroup[g] = byGroup[g] || []).push(s);
            });
            groups.forEach(function (g) {
                var arr = byGroup[g];
                if (!arr || !arr.length) return;
                list.appendChild(el('li', { class: 'cmdk-group', role: 'presentation', text: g }));
                arr.slice(0, q ? 8 : 40).forEach(function (s) { addOption(s.c, s.idx); });
            });

            if (q.length >= 2 && remoteFor === q && remote.length) {
                list.appendChild(el('li', { class: 'cmdk-group', role: 'presentation', text: 'Contenuti' }));
                remote.forEach(function (r) { addOption(r, []); });
            }
            if (q.length >= 2) {
                list.appendChild(el('li', { class: 'cmdk-group', role: 'presentation', text: 'Ricerca' }));
                addOption({ label: 'Cerca “' + q + '” in tutti i contenuti', href: BASE + '/admin/search/full?q=' + encodeURIComponent(q), icon: 'search' }, []);
            }
            if (!items.length) {
                list.appendChild(el('li', { class: 'cmdk-empty', role: 'presentation', text: 'Nessun risultato' }));
            }
            setActive(items.length ? 0 : -1);
        }

        function addOption(c, idx) {
            var id = 'cmdk-opt-' + items.length;
            var iconBox = el('span', { class: 'cmdk-option-icon', 'aria-hidden': 'true' });
            if (c.emoji) iconBox.textContent = c.emoji;
            else iconBox.appendChild(svgIcon(ICONS[c.icon] || ICONS.go));
            var li = el('li', { class: 'cmdk-option', role: 'option', id: id, 'aria-selected': 'false' }, [
                iconBox,
                labelWithMarks(c.label, idx),
                c.hint ? el('span', { class: 'cmdk-option-hint', text: c.hint }) : null
            ]);
            var index = items.length;
            li.addEventListener('mousemove', function () { if (activeIdx !== index) setActive(index); });
            li.addEventListener('click', function () { run(c); });
            list.appendChild(li);
            items.push({ el: li, cmd: c });
        }

        function setActive(i) {
            if (activeIdx >= 0 && items[activeIdx]) items[activeIdx].el.setAttribute('aria-selected', 'false');
            activeIdx = i;
            if (i >= 0 && items[i]) {
                items[i].el.setAttribute('aria-selected', 'true');
                input.setAttribute('aria-activedescendant', items[i].el.id);
                var li = items[i].el;
                var top = li.offsetTop, bottom = top + li.offsetHeight;
                if (top < list.scrollTop) list.scrollTop = top - 30;
                else if (bottom > list.scrollTop + list.clientHeight) list.scrollTop = bottom - list.clientHeight + 8;
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function run(c) {
            if (c.action) { close(); c.action(); return; }
            var href = safeHref(c.href);
            if (!href) return;
            close(false);
            if (c.newTab) window.open(href, '_blank', 'noopener');
            else location.href = href;
        }

        function fetchRemote(q) {
            clearTimeout(fetchTimer);
            if (q.length < 2) { remote = []; remoteFor = ''; return; }
            fetchTimer = setTimeout(function () {
                if (fetchCtrl && fetchCtrl.abort) fetchCtrl.abort();
                fetchCtrl = window.AbortController ? new AbortController() : null;
                fetch(BASE + '/admin/search?q=' + encodeURIComponent(q), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    signal: fetchCtrl ? fetchCtrl.signal : undefined
                }).then(function (r) { return r.ok ? r.json() : { results: [] }; })
                  .then(function (data) {
                      if (input.value.trim() !== q) return;
                      var parser = new DOMParser();
                      remote = (data && data.results ? data.results : []).slice(0, 6).map(function (r) {
                          // titolo e icona arrivano come HTML (con <mark>/entità): si estrae solo il testo
                          var title = parser.parseFromString('<b>' + String(r.title || '') + '</b>', 'text/html').body.textContent;
                          var emoji = parser.parseFromString('<b>' + String(r.icon || '') + '</b>', 'text/html').body.textContent;
                          return { label: title || '(senza titolo)', href: r.url, emoji: emoji.trim().slice(0, 4) || null, hint: String(r.type_label || '') };
                      }).filter(function (r) { return safeHref(r.href); });
                      remoteFor = q;
                      var keep = activeIdx;
                      render();
                      if (keep > 0 && keep < items.length) setActive(keep);
                  }).catch(function () { /* ricerca non disponibile: restano i comandi */ });
            }, 180);
        }

        function open(prefill) {
            if (dlg.open) return;
            opener = document.activeElement;
            commands = buildCommands();
            input.value = prefill || '';
            remote = []; remoteFor = '';
            render();
            dlg.showModal();
            input.focus();
            if (prefill) fetchRemote(prefill.trim());
        }
        function close(restore) {
            if (!dlg.open) return;
            dlg.close();
            if (restore !== false && opener && opener.focus) opener.focus();
        }

        input.addEventListener('input', function () { render(); fetchRemote(input.value.trim()); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (items.length) setActive((activeIdx + 1) % items.length); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (items.length) setActive((activeIdx - 1 + items.length) % items.length); }
            else if (e.key === 'Home' && e.ctrlKey) { e.preventDefault(); setActive(0); }
            else if (e.key === 'End' && e.ctrlKey) { e.preventDefault(); setActive(items.length - 1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIdx >= 0 && items[activeIdx]) run(items[activeIdx].cmd);
            }
        });
        // clic sullo sfondo (fuori dal riquadro) chiude
        dlg.addEventListener('click', function (e) {
            if (e.target !== dlg) return;
            var r = dlg.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) close();
        });
        dlg.addEventListener('close', function () { clearTimeout(fetchTimer); });

        document.querySelectorAll('[data-cmdk-open]').forEach(function (b) {
            b.addEventListener('click', function () { open(''); });
        });
        var kbd = document.querySelector('[data-shortcut-label]');
        if (kbd && isMac()) kbd.textContent = '⌘ K';

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && (e.key === 'k' || e.key === 'K')) {
                // dentro l'editor Ctrl+K inserisce un link: non lo si ruba
                var t = e.target;
                if (t && t.closest && t.closest('.jodit-container, .jodit, [contenteditable="true"]')) return;
                e.preventDefault();
                if (dlg.open) close(); else open('');
            }
        });
    }

    // ═══ SALVATAGGIO AUTOMATICO DELLE BOZZE (articoli, pagine) ═══
    // Chiave: ocms-draft:{utente}:{tipo}:{original_slug | nuovo} (utente = hash breve).
    // Le bozze si cancellano al logout e scadono dopo 7 giorni: sui PC condivisi
    // della scuola non restano testi di un collega nel browser. Non sovrascrive mai
    // nulla senza un clic: al ritorno mostra un avviso con Ripristina / Scarta.
    var DRAFT_PREFIX = 'ocms-draft:';
    var PENDING_KEY = 'ocms-draft-pending';

    // al logout si cancellano tutte le bozze locali
    function clearAllDrafts() {
        var s = store();
        if (!s) return;
        try {
            for (var i = s.length - 1; i >= 0; i--) {
                var k = s.key(i);
                if (k && k.indexOf(DRAFT_PREFIX) === 0) s.removeItem(k);
            }
        } catch (e) {}
    }
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.matches && e.target.matches('form[data-ocms-logout]')) clearAllDrafts();
    }, true);

    function cleanupDrafts() {
        var s = store();
        if (!s) return;
        var limit = Date.now() - 7 * 24 * 3600 * 1000;
        try {
            for (var i = s.length - 1; i >= 0; i--) {
                var k = s.key(i);
                if (!k || k.indexOf(DRAFT_PREFIX) !== 0) continue;
                var d = null;
                try { d = JSON.parse(s.getItem(k)); } catch (e) { d = null; }
                if (!d || !d.savedAt || d.savedAt < limit) s.removeItem(k);
            }
        } catch (e) {}
    }

    // dopo un invio: la bozza si cancella solo se il salvataggio è riuscito
    function settlePendingDraft() {
        var raw = lsGet(PENDING_KEY, 'session');
        if (!raw) return;
        lsDel(PENDING_KEY, 'session');
        var p = null;
        try { p = JSON.parse(raw); } catch (e) { return; }
        if (!p || !p.key || Date.now() - (p.at || 0) > 10 * 60 * 1000) return;
        var ok = document.querySelector('[data-toast][data-type="success"]');
        var err = document.querySelector('[data-toast][data-type="error"]');
        if (ok && !err) lsDel(p.key);
    }

    function initAutosave() {
        var form = document.querySelector('form[data-ocms-autosave]');
        if (!form) return;
        var type = form.getAttribute('data-ocms-autosave');
        var origInput = form.querySelector('input[name="original_slug"]');
        var who = document.body.getAttribute('data-user') || 'anon';
        var key = DRAFT_PREFIX + who + ':' + type + ':' + ((origInput && origInput.value) || 'nuovo');
        var updatedAt = Date.parse(form.getAttribute('data-updated-at') || '') || 0;
        var textarea = form.querySelector('textarea[name="content"]');
        var fieldNames = ['title', 'slug', 'excerpt'];

        function field(name) { return form.querySelector('[name="' + name + '"]'); }
        function jodit() {
            if (!window.Jodit || !window.Jodit.instances) return null;
            var all = window.Jodit.instances, firstInst = null;
            for (var k in all) {
                if (!Object.prototype.hasOwnProperty.call(all, k)) continue;
                var inst = all[k];
                if (!firstInst) firstInst = inst;
                if (inst && inst.element === textarea) return inst;
            }
            return firstInst;
        }
        function readContent() {
            var j = jodit();
            if (j) { try { return String(j.value); } catch (e) {} }
            return textarea ? textarea.value : '';
        }
        function snapshot() {
            var s = { content: readContent() };
            fieldNames.forEach(function (n) { var f = field(n); if (f) s[n] = f.value; });
            return s;
        }
        function same(a, b) {
            if (!a || !b) return false;
            if (a.content !== b.content) return false;
            for (var i = 0; i < fieldNames.length; i++) {
                var n = fieldNames[i];
                if ((a[n] || '') !== (b[n] || '')) return false;
            }
            return true;
        }

        var baseline = null;       // contenuto del documento all'apertura
        var lastSaved = null;
        var paused = false;        // in attesa di Ripristina/Scarta, o dopo l'invio
        var status = null;

        function ensureStatus() {
            if (status) return status;
            var header = form.querySelector('.page-header');
            var target = header ? header.querySelector('div, .actions') : null;
            status = el('span', { class: 'autosave-status', hidden: true });
            if (target) target.insertBefore(status, target.firstChild);
            else if (header) header.appendChild(status);
            return status;
        }
        function showStatus(text, isErr) {
            var s = ensureStatus();
            s.textContent = text;
            s.hidden = false;
            s.classList.toggle('is-error', !!isErr);
            s.classList.remove('just-saved');
            void s.offsetWidth;
            if (!isErr) s.classList.add('just-saved');
        }

        function save() {
            if (paused || !baseline) return;
            var cur = snapshot();
            if (same(cur, lastSaved)) return;
            if (same(cur, baseline)) {
                // tornato identico al salvato: la bozza locale non serve più
                if (lastSaved) { lsDel(key); lastSaved = null; showStatus('Nessuna modifica da salvare'); }
                return;
            }
            cur.savedAt = Date.now();
            cur.url = location.pathname;
            if (lsSet(key, JSON.stringify(cur))) {
                lastSaved = cur;
                showStatus('Bozza salvata in questo browser ' + timeLabel(cur.savedAt).replace('oggi ', ''));
            } else {
                showStatus('Bozza locale non salvata (spazio esaurito)', true);
            }
        }

        function restore(d) {
            var title = field('title'), slug = field('slug'), excerpt = field('excerpt');
            if (title && typeof d.title === 'string') {
                title.value = d.title;
                // avvisa gli ascoltatori della vista (slug automatico, anteprima SEO)
                title.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (slug && typeof d.slug === 'string') {
                slug.value = d.slug;
                slug.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (excerpt && typeof d.excerpt === 'string') {
                excerpt.value = d.excerpt;
                excerpt.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (typeof d.content === 'string') {
                var j = jodit();
                if (j) { try { j.value = d.content; } catch (e) {} }
                if (textarea) textarea.value = d.content;
            }
        }

        function offerRestore(d) {
            paused = true;
            var restoreBtn = el('button', { type: 'button', class: 'btn btn-primary btn-sm', text: 'Ripristina' });
            var discardBtn = el('button', { type: 'button', class: 'btn btn-secondary btn-sm', text: 'Scarta' });
            var icon = el('span', { class: 'draft-banner-icon', 'aria-hidden': 'true' }, [svgIcon(['M12 8v4l3 2', 'M3.05 11a9 9 0 1 1 .5 4', 'M3 4v5h5'])]);
            var banner = el('div', { class: 'draft-banner', role: 'region', 'aria-label': 'Bozza non salvata' }, [
                icon,
                el('div', { class: 'draft-banner-text' }, [
                    el('strong', { text: 'C’è una bozza non salvata' }),
                    el('span', { text: 'Salvata in questo browser ' + timeLabel(d.savedAt) + ', dopo l’ultimo salvataggio sul server. Il testo attuale non è stato toccato.' })
                ]),
                el('div', { class: 'draft-banner-actions' }, [restoreBtn, discardBtn])
            ]);
            var header = form.querySelector('.page-header');
            if (header && header.nextSibling) header.parentNode.insertBefore(banner, header.nextSibling);
            else form.insertBefore(banner, form.firstChild);

            function done() {
                banner.remove();
                paused = false;
            }
            restoreBtn.addEventListener('click', function () {
                restore(d);
                lastSaved = d;
                done();
                showStatus('Bozza ripristinata: ricordati di salvare');
                var t = field('title');
                if (t) t.focus();
            });
            discardBtn.addEventListener('click', function () {
                lsDel(key);
                lastSaved = null;
                done();
                showStatus('Bozza locale scartata');
            });
        }

        function start() {
            baseline = snapshot();
            var raw = lsGet(key), d = null;
            if (raw) { try { d = JSON.parse(raw); } catch (e) { d = null; } }
            if (d && typeof d.content === 'string') {
                if (d.savedAt > updatedAt && !same(d, baseline)) offerRestore(d);
                else lsDel(key); // vecchia o identica: non serve
            }
            setInterval(save, 5000);
            window.addEventListener('pagehide', save);
            document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') save(); });
        }

        form.addEventListener('submit', function (e) {
            // dopo gli altri gestori: se l'invio parte davvero, si smette di salvare
            setTimeout(function () {
                if (e.defaultPrevented) return;
                paused = true;
                var pending = { key: key, at: Date.now() };
                // se nel frattempo c'erano modifiche non ancora salvate, restano nella bozza
                var cur = snapshot();
                if (baseline && !same(cur, baseline)) {
                    cur.savedAt = Date.now();
                    lsSet(key, JSON.stringify(cur));
                }
                lsSet(PENDING_KEY, JSON.stringify(pending), 'session');
            }, 0);
        });

        // l'editor nasce al DOMContentLoaded: si aspetta che sia pronto
        var tries = 0;
        (function waitEditor() {
            tries++;
            var ready = !window.Jodit || (jodit() !== null);
            if ((ready && tries > 2) || tries > 40) start();
            else setTimeout(waitEditor, 150);
        })();
    }

    // ═══ LOGIN: mostra password, avviso maiuscole ═══
    function initLogin() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (b) {
            var input = document.getElementById(b.getAttribute('aria-controls'));
            if (!input) return;
            b.hidden = false;
            b.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                b.setAttribute('aria-pressed', show ? 'true' : 'false');
                b.setAttribute('aria-label', show ? 'Nascondi password' : 'Mostra password');
                input.focus();
            });
        });
        var caps = document.querySelector('[data-caps-hint]');
        var pwd = caps ? document.getElementById(caps.getAttribute('data-caps-hint')) : null;
        if (caps && pwd) {
            var check = function (e) {
                if (e.getModifierState) caps.classList.toggle('is-on', e.getModifierState('CapsLock'));
            };
            pwd.addEventListener('keydown', check);
            pwd.addEventListener('keyup', check);
            pwd.addEventListener('blur', function () { caps.classList.remove('is-on'); });
        }
    }

    // elementi resi "bottone" con role=button (es. zone di upload): attivabili da tastiera
    function initRoleButtons() {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var t = e.target;
            if (!t || !t.matches || !t.matches('[role="button"][tabindex]') || /^(BUTTON|A|INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return;
            e.preventDefault();
            t.click();
        });
    }

    function boot() {
        settlePendingDraft();
        cleanupDrafts();
        initSidebar();
        initMobileMenu();
        initToasts();
        initSubmitLoading();
        initPalette();
        initLogin();
        initRoleButtons();
        initAutosave();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
