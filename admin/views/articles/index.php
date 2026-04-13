<?php
$pageTitle = 'Articoli';
$activeMenu = 'articles';

// Filtro categoria da query string
$filterCat = $_GET['cat'] ?? '';
$filteredArticles = $articles;
if ($filterCat !== '') {
    $filteredArticles = array_filter($articles, fn($a) => ($a['category'] ?? '') === $filterCat);
}

// Mappa slug → nome categoria
$catMap = [];
foreach ($categories as $c) {
    $catMap[$c['slug']] = $c['name'];
}

ob_start();
?>

<div class="page-header">
    <h1>Articoli <span style="font-weight:400;font-size:0.9rem;color:var(--text-muted);">(<?= count($filteredArticles) ?><?= $filterCat ? ' di ' . count($articles) : '' ?>)</span></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= ocms_base_url() ?>/admin/categories" class="btn btn-secondary">Categorie</a>
        <a href="<?= ocms_base_url() ?>/admin/articles/new" class="btn btn-primary">+ Nuovo Articolo</a>
    </div>
</div>

<!-- Filtro categoria -->
<div class="card" style="padding:12px 16px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span style="font-size:0.85rem;font-weight:600;color:var(--text-muted);">Filtra:</span>
        <a href="<?= ocms_base_url() ?>/admin/articles" class="btn btn-sm <?= $filterCat === '' ? 'btn-primary' : 'btn-secondary' ?>">Tutte (<?= count($articles) ?>)</a>
        <?php foreach ($categories as $c):
            $count = 0;
            foreach ($articles as $a) { if (($a['category'] ?? '') === $c['slug']) $count++; }
            if ($count === 0) continue;
        ?>
            <a href="<?= ocms_base_url() ?>/admin/articles?cat=<?= ocms_escape($c['slug']) ?>" class="btn btn-sm <?= $filterCat === $c['slug'] ? 'btn-primary' : 'btn-secondary' ?>">
                <?= ocms_escape($c['name']) ?> (<?= $count ?>)
            </a>
        <?php endforeach; ?>
        <?php
            $uncategorized = 0;
            foreach ($articles as $a) { if (empty($a['category'])) $uncategorized++; }
            if ($uncategorized > 0):
        ?>
            <a href="<?= ocms_base_url() ?>/admin/articles?cat=__none__" class="btn btn-sm <?= $filterCat === '__none__' ? 'btn-primary' : 'btn-secondary' ?>">
                Senza categoria (<?= $uncategorized ?>)
            </a>
        <?php endif; ?>
    </div>
</div>

<?php
// Gestisci filtro speciale "__none__"
if ($filterCat === '__none__') {
    $filteredArticles = array_filter($articles, fn($a) => empty($a['category']));
}
?>

<?php if (empty($filteredArticles)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon">&#9998;</div>
            <h3>Nessun articolo<?= $filterCat ? ' in questa categoria' : '' ?></h3>
            <?php if ($filterCat): ?>
                <a href="<?= ocms_base_url() ?>/admin/articles" class="btn btn-secondary">Mostra tutti</a>
            <?php else: ?>
                <p>Scrivi il tuo primo articolo per il blog.</p>
                <a href="<?= ocms_base_url() ?>/admin/articles/new" class="btn btn-primary">Scrivi Articolo</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Azioni di massa -->
    <form method="POST" action="<?= ocms_base_url() ?>/admin/articles/bulk-move" id="bulk-form">
        <?= ocms_csrf_field() ?>
        <input type="hidden" name="return_cat" value="<?= ocms_escape($filterCat) ?>">
        <div id="bulk-bar" style="display:none;margin-bottom:12px;">
            <div class="card" style="padding:10px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:0.85rem;font-weight:600;"><span id="bulk-count">0</span> selezionati</span>
                <select name="new_category" class="form-input" style="width:auto;padding:6px 10px;font-size:0.85rem;">
                    <option value="">Sposta in categoria...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= ocms_escape($c['slug']) ?>"><?= ocms_escape($c['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="__none__">— Rimuovi categoria —</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Sposta</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAll(false)">Deseleziona</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" id="select-all" title="Seleziona tutti"></th>
                            <th>Titolo</th>
                            <th>Categoria</th>
                            <th>Stato</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredArticles as $a): ?>
                        <tr>
                            <td><input type="checkbox" name="slugs[]" value="<?= ocms_escape($a['slug']) ?>" class="article-check"></td>
                            <td>
                                <a href="<?= ocms_base_url() ?>/admin/articles/edit/<?= ocms_escape($a['slug']) ?>" style="font-weight:600;color:var(--text);">
                                    <?php if (!empty($a['featured'])): ?><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2" style="vertical-align:-1px;margin-right:4px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><?php endif; ?><?= ocms_escape($a['title']) ?>
                                </a>
                                <?php if (!empty($a['excerpt'])): ?>
                                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;"><?= ocms_escape(ocms_truncate($a['excerpt'], 80)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($a['category'])): ?>
                                    <a href="<?= ocms_base_url() ?>/admin/articles?cat=<?= ocms_escape($a['category']) ?>" class="badge badge-published" style="text-decoration:none;">
                                        <?= ocms_escape($catMap[$a['category']] ?? $a['category']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $a['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
                                    <?= $a['status'] === 'published' ? 'Pubblicato' : 'Bozza' ?>
                                </span>
                            </td>
                            <td style="color:var(--text-muted);font-size:0.85rem;">
                                <?= ocms_format_date($a['updated_at']) ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:8px;">
                                    <a href="<?= ocms_base_url() ?>/admin/articles/edit/<?= ocms_escape($a['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                    <form method="POST" action="<?= ocms_base_url() ?>/admin/articles/delete/<?= ocms_escape($a['slug']) ?>"
                                          onsubmit="return confirm('Eliminare questo articolo?');" style="display:inline;">
                                        <?= ocms_csrf_field() ?>
                                        <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
(function() {
    const checks = document.querySelectorAll('.article-check');
    const selectAll = document.getElementById('select-all');
    const bulkBar = document.getElementById('bulk-bar');
    const bulkCount = document.getElementById('bulk-count');

    function updateBulk() {
        const n = document.querySelectorAll('.article-check:checked').length;
        bulkBar.style.display = n > 0 ? '' : 'none';
        bulkCount.textContent = n;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checks.forEach(c => c.checked = this.checked);
            updateBulk();
        });
    }

    checks.forEach(c => c.addEventListener('change', updateBulk));

    window.toggleAll = function(state) {
        checks.forEach(c => c.checked = state);
        if (selectAll) selectAll.checked = state;
        updateBulk();
    };

    // Conferma spostamento
    document.getElementById('bulk-form')?.addEventListener('submit', function(e) {
        const sel = this.querySelector('select[name="new_category"]');
        if (!sel.value) { e.preventDefault(); alert('Seleziona una categoria di destinazione'); return; }
        const n = document.querySelectorAll('.article-check:checked').length;
        if (!confirm('Spostare ' + n + ' articoli nella categoria "' + sel.options[sel.selectedIndex].text + '"?')) {
            e.preventDefault();
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
