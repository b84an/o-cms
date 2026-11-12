/* Flavor — schermo FoglioFirme da proiettare: conteggio firme aggiornato ogni 5 secondi.
   /firma/{slug}/count restituisce last = null a chi non è staff: in quel caso la riga
   "ultima firma" resta nascosta. */
(function () {
    'use strict';
    var root = document.querySelector('[data-firma-show]');
    if (!root) return;
    var url = root.getAttribute('data-count-url');
    var countEl = root.querySelector('[data-firma-count]');
    var labelEl = root.querySelector('[data-firma-label]');
    var lastEl = root.querySelector('[data-firma-last]');
    var statusEl = root.querySelector('[data-firma-status]');
    var current = parseInt(countEl.textContent, 10) || 0;
    var lastSeen = '';
    var delay = 5000;

    function apply(d) {
        if (typeof d.count === 'number' && d.count !== current) {
            var grew = d.count > current;
            current = d.count;
            countEl.textContent = String(current);
            labelEl.textContent = current === 1 ? 'firma raccolta' : 'firme raccolte';
            if (grew) {
                countEl.classList.remove('is-bump');
                void countEl.offsetWidth; // riavvia l'animazione
                countEl.classList.add('is-bump');
            }
        }
        if (d.last && typeof d.last === 'object' && (d.last.nome || d.last.cognome)) {
            var who = ((d.last.nome || '') + ' ' + (d.last.cognome || '')).trim();
            if (who !== lastSeen) {
                lastSeen = who;
                lastEl.textContent = 'Ultima firma: ' + who;
                lastEl.hidden = false;
            }
        } else {
            lastEl.hidden = true;
        }
        if (typeof d.status === 'string') {
            var open = d.status === 'open';
            statusEl.textContent = open ? 'Raccolta firme aperta' : 'Raccolta firme chiusa';
            statusEl.classList.toggle('is-closed', !open);
        }
    }

    function poll() {
        fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.json(); })
            .then(function (d) { if (d && typeof d === 'object') apply(d); delay = 5000; })
            .catch(function () { delay = Math.min(delay * 2, 60000); }) // rete assente: rallenta
            .then(function () { setTimeout(poll, document.hidden ? 15000 : delay); });
    }
    setTimeout(poll, 1000);
})();
