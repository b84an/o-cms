/* Flavor — quiz interattivo.
   Il browser non conosce le risposte corrette: invia il testo dell'opzione scelta
   per ogni domanda (indice originale) e il punteggio arriva dal server. */
(function () {
    'use strict';
    var dataEl = document.getElementById('quiz-data');
    if (!dataEl) return;
    var Q;
    try { Q = JSON.parse(dataEl.textContent); } catch (e) { return; }
    if (!Q || !Q.questions || !Q.questions.length) return;

    var KEY = 'ocms-quiz-v2:' + Q.slug;
    var KEEPALIVE_MS = 5 * 60 * 1000;
    var byIndex = {};
    Q.questions.forEach(function (q) { byIndex[q.i] = q; });
    var N = Q.questions.length;

    var $ = function (id) { return document.getElementById(id); };
    var els = {
        start: $('quiz-start'), form: $('quiz-start-form'), startErr: $('quiz-start-error'), startBtn: $('quiz-start-btn'),
        recovery: $('quiz-recovery'), run: $('quiz-run'), done: $('quiz-done'),
        timer: $('quiz-timer'), count: $('quiz-count'), answered: $('quiz-answered'), fill: $('quiz-progress-fill'),
        timeAlert: $('quiz-time-alert'), legend: $('quiz-q-legend'), options: $('quiz-options'),
        prev: $('quiz-prev'), next: $('quiz-next'), finish: $('quiz-finish'), map: $('quiz-map'),
        sending: $('quiz-sending'), error: $('quiz-error'), errorMsg: $('quiz-error-msg'), retry: $('quiz-retry'),
        result: $('quiz-result'), dialog: $('quiz-confirm'), dialogText: $('quiz-confirm-text'), dialogOk: $('quiz-confirm-ok')
    };

    var st = null;          // stato corrente: {v, student, order, answers, pos, startTime, finished, elapsed}
    var tick = null, keep = null, sending = false, warned = false;

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }
    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
        return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
    function load() {
        try {
            var raw = localStorage.getItem(KEY);
            if (!raw) return null;
            var s = JSON.parse(raw);
            if (!s || s.v !== 2 || !Array.isArray(s.order) || s.order.length !== N || typeof s.startTime !== 'number') return null;
            var seen = {};
            for (var k = 0; k < s.order.length; k++) {
                if (!byIndex.hasOwnProperty(s.order[k]) || seen[s.order[k]]) return null;
                seen[s.order[k]] = 1;
            }
            var clean = {};
            Object.keys(s.answers || {}).forEach(function (i) {
                var q = byIndex[i];
                if (q && q.options.indexOf(s.answers[i]) !== -1) clean[i] = s.answers[i];
            });
            s.answers = clean;
            s.pos = Math.min(Math.max(0, s.pos | 0), N - 1);
            return s;
        } catch (e) { return null; }
    }
    function clear() { try { localStorage.removeItem(KEY); } catch (e) {} }
    function show(view) {
        els.start.hidden = view !== 'start';
        els.run.hidden = view !== 'run';
        els.done.hidden = view !== 'done';
    }
    function elapsed() { return st.finished && typeof st.elapsed === 'number' ? st.elapsed : Math.round((Date.now() - st.startTime) / 1000); }
    function answeredCount() { return Object.keys(st.answers).length; }

    // Testo della domanda: i blocchi ```...``` diventano codice, il resto testo semplice
    function renderText(target, text) {
        var parts = String(text).split('```');
        if (parts.length < 3) { appendLines(target, text); return; }
        parts.forEach(function (p, i) {
            if (i % 2 === 0) { appendLines(target, p); }
            else {
                var pre = el('pre', 'quiz-code'), code = el('code', null, p.replace(/^\n/, '').replace(/\n$/, ''));
                pre.appendChild(code);
                target.appendChild(pre);
            }
        });
    }
    function appendLines(target, text) {
        String(text).split('\n').forEach(function (line, i) {
            if (i) target.appendChild(document.createElement('br'));
            if (line) target.appendChild(document.createTextNode(line));
        });
    }

    function renderQuestion(focus) {
        var qi = st.order[st.pos], q = byIndex[qi];
        els.legend.textContent = '';
        var num = el('span', 'quiz-q-num', 'Domanda ' + (st.pos + 1) + ' di ' + N);
        var txt = el('span', 'quiz-q-text');
        renderText(txt, q.text);
        els.legend.appendChild(num);
        els.legend.appendChild(txt);

        els.options.textContent = '';
        q.options.forEach(function (opt, k) {
            var id = 'qo-' + qi + '-' + k;
            var label = el('label', 'quiz-option');
            label.setAttribute('for', id);
            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'quiz-answer';
            input.id = id;
            input.value = String(k);
            input.checked = st.answers[qi] === opt;
            input.addEventListener('change', function () {
                if (st.finished) return;
                st.answers[qi] = opt;
                save();
                updateChrome();
            });
            var mark = el('span', 'quiz-option-mark');
            mark.setAttribute('aria-hidden', 'true');
            mark.textContent = String.fromCharCode(65 + k);
            label.appendChild(input);
            label.appendChild(mark);
            label.appendChild(el('span', 'quiz-option-text', opt));
            els.options.appendChild(label);
        });
        updateChrome();
        if (focus) {
            els.legend.setAttribute('tabindex', '-1');
            els.legend.focus({ preventScroll: true });
            var top = els.run.getBoundingClientRect().top + window.scrollY - 80;
            if (window.scrollY > top) window.scrollTo({ top: top });
        }
    }

    function updateChrome() {
        var n = answeredCount();
        els.count.textContent = 'Domanda ' + (st.pos + 1) + ' di ' + N;
        els.answered.textContent = n === 1 ? '1 risposta' : n + ' risposte';
        els.fill.style.width = (n / N * 100) + '%';
        els.prev.disabled = st.pos === 0;
        els.next.hidden = st.pos >= N - 1;
        els.finish.classList.toggle('is-emphasis', st.pos >= N - 1);
        var items = els.map.children;
        for (var k = 0; k < items.length; k++) {
            var b = items[k].firstChild, qi = st.order[k];
            b.classList.toggle('is-done', st.answers.hasOwnProperty(qi));
            if (k === st.pos) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
        }
    }

    function buildMap() {
        els.map.textContent = '';
        st.order.forEach(function (qi, k) {
            var li = document.createElement('li');
            var b = el('button', 'quiz-map-btn', String(k + 1));
            b.type = 'button';
            b.setAttribute('aria-label', 'Domanda ' + (k + 1));
            b.addEventListener('click', function () { st.pos = k; save(); renderQuestion(true); });
            li.appendChild(b);
            els.map.appendChild(li);
        });
    }

    function updateTimer() {
        var span = els.timer.lastChild, e = elapsed();
        if (Q.timeLimit > 0) {
            var left = Q.timeLimit - e;
            span.textContent = fmt(left);
            els.timer.classList.toggle('is-low', left <= 60);
            if (left <= 60 && !warned) { warned = true; els.timeAlert.textContent = 'Manca un minuto alla fine del tempo.'; }
            if (left <= 0) { lock('Tempo scaduto: il quiz viene consegnato automaticamente.'); }
        } else {
            span.textContent = fmt(e);
        }
    }

    function begin() {
        show('run');
        buildMap();
        renderQuestion(false);
        updateTimer();
        clearInterval(tick);
        tick = setInterval(updateTimer, 1000);
        clearInterval(keep);
        keep = setInterval(keepalive, KEEPALIVE_MS);
        window.addEventListener('beforeunload', guard);
    }
    function guard(e) { if (st && !st.sent) { e.preventDefault(); e.returnValue = ''; } }

    // Mantiene viva la sessione PHP (il codice d'accesso e il token stanno lì)
    function keepalive() {
        fetch(Q.emailUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email: '', _csrf_token: Q.csrf }), credentials: 'same-origin' }).catch(function () {});
    }

    function lock(reason) {
        if (st.finished) return;
        st.finished = true;
        st.elapsed = Q.timeLimit > 0 ? Math.min(elapsed(), Q.timeLimit) : elapsed();
        save();
        clearInterval(tick);
        clearInterval(keep);
        if (reason) els.timeAlert.textContent = reason;
        submit();
    }

    function submit() {
        if (sending) return;
        sending = true;
        show('done');
        els.result.hidden = true;
        els.error.hidden = true;
        els.sending.hidden = false;
        var payload = {
            _csrf_token: Q.csrf,
            student: st.student,
            answers: st.answers,
            time_seconds: st.elapsed
        };
        fetch(Q.submitUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.text().then(function (t) {
                var d = null;
                try { d = JSON.parse(t); } catch (e) {}
                if (!d) throw new Error('Il server ha risposto in modo inatteso (codice ' + r.status + ').');
                if (!d.success) throw new Error(d.error || 'Invio rifiutato dal server.');
                return d;
            });
        }).then(function (d) {
            st.sent = true;
            clear();
            window.removeEventListener('beforeunload', guard);
            showResult(d);
        }).catch(function (err) {
            var msg = err && err.message ? err.message : '';
            if (!msg || /fetch|network|load failed/i.test(msg)) msg = 'Connessione assente o interrotta.';
            if (/csrf|token/i.test(msg)) msg = 'La sessione è scaduta: ricarica la pagina (le risposte restano salvate), reinserisci il codice se richiesto e riprova.';
            els.errorMsg.textContent = msg;
            els.sending.hidden = true;
            els.error.hidden = false;
            els.retry.focus();
        }).then(function () { sending = false; });
    }

    function showResult(d) {
        els.sending.hidden = true;
        els.error.hidden = true;
        els.result.hidden = false;
        var score = Number(d.score) || 0;
        $('quiz-result-who').textContent = [st.student.name, st.student['class']].filter(Boolean).join(', classe ');
        $('quiz-score').textContent = String(score).replace('.', ',');
        $('quiz-score-ring').style.setProperty('--p', String(Math.max(0, Math.min(100, score))));
        $('quiz-score-ring').classList.add(score >= 60 ? 'is-pass' : 'is-fail');
        $('quiz-r-ok').textContent = d.correct_count;
        $('quiz-r-ko').textContent = d.wrong_count;
        $('quiz-r-skip').textContent = d.skipped_count;
        $('quiz-r-time').textContent = fmt(st.elapsed || 0);
        var t = els.result.querySelector('.result-title');
        t.setAttribute('tabindex', '-1');
        t.focus();
    }

    function confirmFinish() {
        var n = answeredCount();
        var text = n < N
            ? 'Hai risposto a ' + n + ' domande su ' + N + '. Le domande senza risposta contano come saltate. Dopo la consegna non potrai modificare le risposte.'
            : 'Hai risposto a tutte le ' + N + ' domande. Dopo la consegna non potrai modificarle.';
        if (els.dialog && typeof els.dialog.showModal === 'function') {
            els.dialogText.textContent = text;
            els.dialog.showModal();
        } else if (window.confirm(text + '\n\nConsegnare adesso?')) {
            lock();
        }
    }

    function shuffle(a) {
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1)), t = a[i];
            a[i] = a[j]; a[j] = t;
        }
        return a;
    }

    // ── Avvio ──
    els.form.addEventListener('submit', function (e) {
        e.preventDefault();
        els.startErr.hidden = true;
        var nameEl = $('student-name'), classEl = $('student-class'), emailEl = $('student-email');
        var name = nameEl ? nameEl.value.trim() : String(Q.loggedName || '');
        var cls = classEl.value.trim();
        var email = emailEl ? emailEl.value.trim() : '';
        var fail = function (msg, field) {
            els.startErr.textContent = msg;
            els.startErr.hidden = false;
            if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
        };
        [nameEl, classEl, emailEl].forEach(function (f) { if (f) f.removeAttribute('aria-invalid'); });
        if (!name) return fail('Scrivi cognome e nome.', nameEl);
        if (!cls) return fail('Scrivi la classe.', classEl);
        if (Q.requireEmail && (!email || !emailEl.checkValidity())) return fail('Scrivi un indirizzo email valido.', emailEl);

        var go = function () {
            var order = Q.questions.map(function (q) { return q.i; });
            if (Q.randomize) shuffle(order);
            st = { v: 2, student: { name: name, 'class': cls, email: email }, order: order, answers: {}, pos: 0, startTime: Date.now(), finished: false };
            save();
            begin();
        };
        if (!email) return go();
        els.startBtn.disabled = true;
        fetch(Q.emailUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ email: email, _csrf_token: Q.csrf }) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.taken) fail('Questa email ha già completato il quiz.', emailEl);
                else go();
            })
            .catch(go) // il server ricontrolla comunque alla consegna
            .then(function () { els.startBtn.disabled = false; });
    });

    els.prev.addEventListener('click', function () { if (st.pos > 0) { st.pos--; save(); renderQuestion(true); } });
    els.next.addEventListener('click', function () { if (st.pos < N - 1) { st.pos++; save(); renderQuestion(true); } });
    els.finish.addEventListener('click', confirmFinish);
    els.retry.addEventListener('click', submit);
    if (els.dialog) {
        els.dialogOk.addEventListener('click', function () { els.dialog.close(); lock(); });
        els.dialog.querySelector('[data-dialog-close]').addEventListener('click', function () { els.dialog.close(); });
    }

    var saved = load();
    if (saved) {
        els.recovery.hidden = false;
        if (saved.finished) {
            els.recovery.querySelector('p').textContent = 'Hai un quiz già terminato ma non ancora consegnato su questo dispositivo. Consegnalo adesso.';
            document.querySelector('[data-quiz-recover]').textContent = 'Consegna ora';
            document.querySelector('[data-quiz-discard]').hidden = true;
        }
        document.querySelector('[data-quiz-recover]').addEventListener('click', function () {
            st = saved;
            if (st.finished) { st.sent = false; show('done'); submit(); return; }
            begin();
            if (Q.timeLimit > 0 && elapsed() >= Q.timeLimit) lock('Tempo scaduto: il quiz viene consegnato automaticamente.');
        });
        document.querySelector('[data-quiz-discard]').addEventListener('click', function () {
            clear();
            saved = null;
            els.recovery.hidden = true;
        });
    }
})();
