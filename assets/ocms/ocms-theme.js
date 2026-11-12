/* O-CMS 2 — scelta tema chiaro/scuro condivisa da admin e temi.
   Caricare nel <head> SENZA defer per evitare il lampo del tema sbagliato.
   Qualsiasi elemento con [data-ocms-theme-toggle] alterna il tema;
   senza scelta salvata vale prefers-color-scheme. */
(function () {
    var KEY = 'ocms-theme';
    var root = document.documentElement;
    function saved() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
    function current() {
        var s = root.getAttribute('data-theme');
        if (s) return s;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }
    var s = saved();
    if (s === 'light' || s === 'dark') root.setAttribute('data-theme', s);

    function sync() {
        var t = current();
        document.querySelectorAll('[data-ocms-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-pressed', t === 'light' ? 'true' : 'false');
            b.setAttribute('aria-label', t === 'light' ? 'Passa al tema scuro' : 'Passa al tema chiaro');
            b.setAttribute('title', t === 'light' ? 'Tema scuro' : 'Tema chiaro');
        });
    }
    function toggle() {
        var next = current() === 'light' ? 'dark' : 'light';
        var apply = function () {
            root.setAttribute('data-theme', next);
            try { localStorage.setItem(KEY, next); } catch (e) {}
            sync();
        };
        // passaggio morbido con le View Transitions, dove ci sono
        if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.startViewTransition(apply);
        } else {
            apply();
        }
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-ocms-theme-toggle]');
        if (b) { e.preventDefault(); toggle(); }
    });
    document.addEventListener('DOMContentLoaded', sync);
    window.OCMSTheme = { toggle: toggle, current: current };
})();
