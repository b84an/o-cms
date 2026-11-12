/* Flavor — comportamenti del tema: menu, sottomenu, lightbox, risposte ai commenti,
   filtro delle lezioni, copia del codice. Niente dipendenze, niente innerHTML. */
(function () {
    'use strict';
    var doc = document;
    var mqMobile = window.matchMedia('(max-width: 900px)');

    function el(tag, cls, text) {
        var n = doc.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    /* ── Menu principale ── */
    var navToggle = doc.querySelector('[data-nav-toggle]');
    var navPanel = doc.querySelector('[data-nav-panel]');
    function closeSubs(except) {
        doc.querySelectorAll('.nav-sub-toggle[aria-expanded="true"]').forEach(function (b) {
            if (b !== except) {
                b.setAttribute('aria-expanded', 'false');
                b.closest('.nav-item').classList.remove('is-open');
            }
        });
    }
    function setNav(open, returnFocus) {
        if (!navToggle || !navPanel) return;
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        navPanel.classList.toggle('is-open', open);
        doc.documentElement.classList.toggle('nav-open', open);
        if (!open) {
            closeSubs(null);
            if (returnFocus) navToggle.focus();
        } else {
            var first = navPanel.querySelector('a, button, input');
            if (first) first.focus();
        }
    }
    if (navToggle && navPanel) {
        navToggle.addEventListener('click', function () {
            setNav(navToggle.getAttribute('aria-expanded') !== 'true', false);
        });
        var onMq = function () { if (!mqMobile.matches) setNav(false, false); };
        if (mqMobile.addEventListener) mqMobile.addEventListener('change', onMq); else if (mqMobile.addListener) mqMobile.addListener(onMq);
    }
    doc.querySelectorAll('.nav-sub-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = btn.getAttribute('aria-expanded') !== 'true';
            closeSubs(btn);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.closest('.nav-item').classList.toggle('is-open', open);
        });
    });
    doc.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('.has-sub')) closeSubs(null);
        if (navPanel && navPanel.classList.contains('is-open') && !e.target.closest('.site-header')) setNav(false, false);
    });
    doc.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var openSub = doc.querySelector('.nav-sub-toggle[aria-expanded="true"]');
        if (openSub) { closeSubs(null); openSub.focus(); return; }
        if (navPanel && navPanel.classList.contains('is-open')) setNav(false, true);
    });
    // Uscendo con Tab da un sottomenu, si chiude
    doc.querySelectorAll('.has-sub').forEach(function (item) {
        item.addEventListener('focusout', function (e) {
            if (e.relatedTarget && !item.contains(e.relatedTarget)) {
                var b = item.querySelector('.nav-sub-toggle');
                if (b) { b.setAttribute('aria-expanded', 'false'); item.classList.remove('is-open'); }
            }
        });
    });

    /* ── Lightbox: link con data-lightbox="gruppo" ── */
    var lb = null, lbItems = [], lbIndex = 0, lbOpener = null;
    function buildLightbox() {
        lb = el('dialog', 'lightbox');
        lb.setAttribute('aria-label', 'Visualizzatore immagini');
        var fig = el('figure', 'lightbox-figure');
        var img = el('img', 'lightbox-img');
        img.alt = '';
        var cap = el('figcaption', 'lightbox-cap');
        fig.appendChild(img);
        fig.appendChild(cap);
        var mk = function (cls, label, path) {
            var b = el('button', 'lightbox-btn ' + cls);
            b.type = 'button';
            b.setAttribute('aria-label', label);
            var svg = doc.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('aria-hidden', 'true');
            var p = doc.createElementNS('http://www.w3.org/2000/svg', 'path');
            p.setAttribute('d', path);
            svg.appendChild(p);
            b.appendChild(svg);
            return b;
        };
        var close = mk('lb-close', 'Chiudi', 'M6 6l12 12M18 6 6 18');
        var prev = mk('lb-prev', 'Immagine precedente', 'm15 18-6-6 6-6');
        var next = mk('lb-next', 'Immagine successiva', 'm9 18 6-6-6-6');
        var count = el('p', 'lightbox-count');
        lb.appendChild(fig);
        lb.appendChild(prev);
        lb.appendChild(next);
        lb.appendChild(close);
        lb.appendChild(count);
        doc.body.appendChild(lb);
        close.addEventListener('click', function () { lb.close(); });
        prev.addEventListener('click', function () { show(lbIndex - 1); });
        next.addEventListener('click', function () { show(lbIndex + 1); });
        lb.addEventListener('click', function (e) { if (e.target === lb || e.target === fig) lb.close(); });
        lb.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); show(lbIndex - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); show(lbIndex + 1); }
        });
        lb.addEventListener('close', function () {
            doc.documentElement.classList.remove('lb-open');
            if (lbOpener) lbOpener.focus();
        });
        var x0 = null;
        lb.addEventListener('touchstart', function (e) { x0 = e.changedTouches[0].clientX; }, { passive: true });
        lb.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 50) show(lbIndex + (dx < 0 ? 1 : -1));
            x0 = null;
        }, { passive: true });
        lb._img = img; lb._cap = cap; lb._count = count; lb._prev = prev; lb._next = next;
    }
    function show(i) {
        if (!lbItems.length) return;
        lbIndex = (i + lbItems.length) % lbItems.length;
        var a = lbItems[lbIndex];
        var thumb = a.querySelector('img');
        lb._img.src = a.getAttribute('href');
        lb._img.alt = thumb ? thumb.alt : '';
        var cap = a.getAttribute('data-caption') || '';
        lb._cap.textContent = cap;
        lb._cap.hidden = !cap;
        lb._count.textContent = lbItems.length > 1 ? (lbIndex + 1) + ' / ' + lbItems.length : '';
        lb._prev.hidden = lb._next.hidden = lbItems.length < 2;
    }
    doc.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-lightbox]') : null;
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey) return;
        if (typeof HTMLDialogElement === 'undefined') return; // senza <dialog> il link apre l'immagine
        e.preventDefault();
        if (!lb) buildLightbox();
        var group = a.getAttribute('data-lightbox');
        lbItems = Array.prototype.slice.call(doc.querySelectorAll('a[data-lightbox="' + group.replace(/"/g, '') + '"]'));
        lbOpener = a;
        show(lbItems.indexOf(a));
        doc.documentElement.classList.add('lb-open');
        lb.showModal();
    });

    /* ── Commenti: apri/chiudi il modulo di risposta ── */
    doc.querySelectorAll('[data-reply-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = doc.getElementById(btn.getAttribute('aria-controls'));
            if (!box) return;
            var open = box.hidden;
            box.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { var f = box.querySelector('input:not([type=hidden]), textarea'); if (f) f.focus(); }
        });
    });

    /* ── Lezioni: ricerca, filtro per argomento, ordinamento ── */
    var finder = doc.querySelector('[data-finder]');
    if (finder) {
        var list = finder.querySelector('[data-finder-list]');
        var cards = Array.prototype.slice.call(list.children);
        var input = finder.querySelector('[data-finder-q]');
        var status = finder.querySelector('[data-finder-status]');
        var empty = finder.querySelector('[data-finder-empty]');
        var tagBtns = Array.prototype.slice.call(finder.querySelectorAll('[data-finder-tag]'));
        var sortBtns = Array.prototype.slice.call(finder.querySelectorAll('[data-finder-sort]'));
        var activeTag = '', sortMode = 'date', timer = null;
        var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
        var apply = function () {
            var words = norm(input.value).split(/\s+/).filter(Boolean);
            var shown = 0;
            cards.forEach(function (c) {
                var hay = norm(c.getAttribute('data-hay'));
                var ok = words.every(function (w) { return hay.indexOf(w) !== -1; });
                if (ok && activeTag) ok = (c.getAttribute('data-tags') || '').indexOf('|' + activeTag + '|') !== -1;
                c.hidden = !ok;
                if (ok) shown++;
            });
            var sorted = cards.slice().sort(function (a, b) {
                if (sortMode === 'alpha') return a.getAttribute('data-title').localeCompare(b.getAttribute('data-title'), 'it');
                return (+a.getAttribute('data-order')) - (+b.getAttribute('data-order'));
            });
            sorted.forEach(function (c) { list.appendChild(c); });
            status.textContent = shown === cards.length ? '' : (shown === 1 ? '1 lezione trovata' : shown + ' lezioni trovate');
            empty.hidden = shown !== 0;
        };
        input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(apply, 120); });
        tagBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.getAttribute('data-finder-tag');
                activeTag = activeTag === t ? '' : t;
                tagBtns.forEach(function (x) { x.setAttribute('aria-pressed', x.getAttribute('data-finder-tag') === activeTag ? 'true' : 'false'); });
                apply();
            });
        });
        sortBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                sortMode = b.getAttribute('data-finder-sort');
                sortBtns.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
                apply();
            });
        });
        var reset = finder.querySelector('[data-finder-reset]');
        if (reset) reset.addEventListener('click', function () {
            input.value = ''; activeTag = '';
            tagBtns.forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
            apply();
            input.focus();
        });
    }

    /* ── Blocchi di codice: pulsante "Copia" sui <pre> diretti del testo ── */
    if (navigator.clipboard && window.isSecureContext) {
        doc.querySelectorAll('.prose > pre').forEach(function (pre) {
            if ((pre.textContent || '').trim().length < 2) return;
            var wrap = el('div', 'code-wrap');
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);
            var b = el('button', 'code-copy', 'Copia');
            b.type = 'button';
            b.setAttribute('aria-label', 'Copia il codice');
            b.addEventListener('click', function () {
                navigator.clipboard.writeText(pre.innerText.replace(/\n$/, '')).then(function () {
                    b.textContent = 'Copiato';
                    b.classList.add('is-done');
                    setTimeout(function () { b.textContent = 'Copia'; b.classList.remove('is-done'); }, 1600);
                }, function () { b.textContent = 'Non riuscito'; });
            });
            wrap.appendChild(b);
        });
    }

    /* ── Tabelle larghe nei contenuti: scorrimento orizzontale senza rompere la pagina ── */
    doc.querySelectorAll('.prose table').forEach(function (t) {
        if (t.closest('.table-scroll') || t.parentNode.classList.contains('table-scroll')) return;
        var w = el('div', 'table-scroll');
        t.parentNode.insertBefore(w, t);
        w.appendChild(t);
        if (w.scrollWidth > w.clientWidth + 2) {
            // scorre davvero: raggiungibile da tastiera
            w.setAttribute('tabindex', '0');
            w.setAttribute('role', 'region');
            w.setAttribute('aria-label', 'Tabella, scorri in orizzontale');
        }
    });
})();
