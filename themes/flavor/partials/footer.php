    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></p>
        </div>
    </footer>
    <script>
    (function(){
        const saved = localStorage.getItem('ocms-theme');
        if (saved) document.documentElement.setAttribute('data-theme', saved);
        updateThemeIcons();
    })();
    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('ocms-theme', next);
        updateThemeIcons();
    }
    function updateThemeIcons() {
        const isLight = document.documentElement.getAttribute('data-theme') === 'light';
        const sun = document.getElementById('theme-icon-sun');
        const moon = document.getElementById('theme-icon-moon');
        if (sun && moon) { sun.style.display = isLight ? 'block' : 'none'; moon.style.display = isLight ? 'none' : 'block'; }
    }
    </script>
