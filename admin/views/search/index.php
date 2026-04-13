<?php
$pageTitle = 'Ricerca';
$activeMenu = '';
$totalPages = ($result && $result['total'] > 0) ? ceil($result['total'] / $perPage) : 0;

ob_start();
?>
<style>
.search-box { position:relative; margin-bottom:24px; }
.search-input {
    width:100%; padding:16px 20px 16px 48px; background:var(--bg-card);
    border:1px solid var(--border); border-radius:16px; color:var(--text);
    font-size:1.1rem; font-family:var(--font); outline:none; transition:border-color 0.2s, box-shadow 0.2s;
}
.search-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,0.12); }
.search-icon { position:absolute; left:18px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.1rem; }
.search-suggestions { position:absolute; top:100%; left:0; right:0; background:var(--bg-card); border:1px solid var(--border); border-radius:12px; margin-top:4px; z-index:50; display:none; box-shadow:0 8px 24px rgba(0,0,0,0.3); overflow:hidden; }
.search-suggestions.open { display:block; }
.search-suggest-item { padding:10px 16px; cursor:pointer; font-size:0.9rem; transition:background 0.1s; }
.search-suggest-item:hover { background:var(--bg-hover); }

.filters-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:4px; }
.filter-group label { font-size:0.7rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
.filter-chips { display:flex; gap:6px; flex-wrap:wrap; }
.filter-chip {
    padding:6px 12px; border-radius:6px; font-size:0.8rem; font-weight:500;
    cursor:pointer; transition:all 0.15s; border:1px solid var(--border);
    background:var(--bg-input); color:var(--text-muted);
}
.filter-chip:hover { border-color:var(--primary); color:var(--text); }
.filter-chip.active { background:rgba(99,102,241,0.15); border-color:var(--primary); color:var(--primary-light); }

.search-stats { font-size:0.85rem; color:var(--text-muted); margin-bottom:20px; }
.search-stats strong { color:var(--text); }

.result-item {
    display:flex; gap:16px; padding:20px; background:var(--bg-card);
    border:1px solid var(--border); border-radius:12px; margin-bottom:12px;
    transition:border-color 0.15s;
}
.result-item:hover { border-color:rgba(99,102,241,0.3); }
.result-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.result-icon.page { background:rgba(59,130,246,0.12); }
.result-icon.article { background:rgba(34,197,94,0.12); }
.result-icon.user { background:rgba(139,92,246,0.12); }
.result-icon.category { background:rgba(245,158,11,0.12); }
.result-icon.media { background:rgba(236,72,153,0.12); }
.result-icon.form { background:rgba(20,184,166,0.12); }
.result-icon.menu { background:rgba(99,102,241,0.12); }
.result-body { flex:1; min-width:0; }
.result-title { font-size:1rem; font-weight:600; margin-bottom:4px; }
.result-title a { color:var(--text); }
.result-title a:hover { color:var(--primary-light); }
.result-title mark { background:rgba(99,102,241,0.25); color:var(--primary-light); border-radius:2px; padding:0 2px; }
.result-meta { display:flex; gap:12px; font-size:0.8rem; color:var(--text-muted); margin-bottom:6px; }
.result-snippet { font-size:0.85rem; color:var(--text-muted); line-height:1.5; }
.result-snippet mark { background:rgba(99,102,241,0.2); color:var(--primary-light); border-radius:2px; padding:0 2px; }
.result-score { font-size:0.7rem; color:var(--text-muted); opacity:0.5; }

.pagination { display:flex; gap:6px; justify-content:center; margin-top:24px; }
.pagination a, .pagination span {
    padding:8px 14px; border-radius:8px; font-size:0.85rem; font-weight:500;
    border:1px solid var(--border); color:var(--text-muted); text-decoration:none;
}
.pagination a:hover { border-color:var(--primary); color:var(--text); }
.pagination .active { background:var(--primary); border-color:var(--primary); color:white; }
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Ricerca Avanzata</h1>
</div>

<!-- Barra ricerca -->
<form method="GET" action="<?= ocms_base_url() ?>/admin/search/full" id="search-form">
    <div class="search-box">
        <span class="search-icon">&#128269;</span>
        <input type="text" name="q" class="search-input" id="search-input"
               value="<?= ocms_escape($query) ?>" placeholder="Cerca in pagine, articoli, utenti, media, form, menu..."
               autocomplete="off" autofocus>
        <div class="search-suggestions" id="suggestions-box"></div>
    </div>

    <!-- Filtri -->
    <div class="filters-bar">
        <div class="filter-group">
            <label>Tipo</label>
            <div class="filter-chips">
                <?php foreach ($filters['types'] as $key => $label): ?>
                <label class="filter-chip <?= in_array($key, $activeTypes) ? 'active' : '' ?>">
                    <input type="checkbox" name="types[]" value="<?= $key ?>" <?= in_array($key, $activeTypes) ? 'checked' : '' ?> style="display:none;" onchange="this.form.submit();">
                    <?= ocms_escape($label) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-group">
            <label>Stato</label>
            <select name="status" class="form-select" style="padding:6px 10px;font-size:0.8rem;" onchange="this.form.submit();">
                <option value="">Tutti</option>
                <?php foreach ($filters['statuses'] as $key => $label): ?>
                <option value="<?= $key ?>" <?= $activeStatus === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($filters['categories'])): ?>
        <div class="filter-group">
            <label>Categoria</label>
            <select name="category" class="form-select" style="padding:6px 10px;font-size:0.8rem;" onchange="this.form.submit();">
                <option value="">Tutte</option>
                <?php foreach ($filters['categories'] as $cat): ?>
                <option value="<?= ocms_escape(ocms_slug($cat)) ?>" <?= $activeCategory === ocms_slug($cat) ? 'selected' : '' ?>><?= ocms_escape($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="filter-group">
            <label>Da</label>
            <input type="date" name="date_from" class="form-input" style="padding:6px 10px;font-size:0.8rem;" value="<?= ocms_escape($dateFrom) ?>" onchange="this.form.submit();">
        </div>
        <div class="filter-group">
            <label>A</label>
            <input type="date" name="date_to" class="form-input" style="padding:6px 10px;font-size:0.8rem;" value="<?= ocms_escape($dateTo) ?>" onchange="this.form.submit();">
        </div>
    </div>
</form>

<?php if (!empty($suggestions) && $query): ?>
<div style="margin-bottom:16px;">
    <span style="font-size:0.8rem;color:var(--text-muted);">Suggerimenti:</span>
    <?php foreach ($suggestions as $s): ?>
        <a href="<?= ocms_base_url() ?>/admin/search/full?q=<?= urlencode($s) ?>"
           style="display:inline-block;padding:3px 10px;background:var(--bg-card);border:1px solid var(--border);border-radius:6px;font-size:0.8rem;margin:2px;"><?= ocms_escape($s) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($query): ?>
    <?php if (!empty($result['results'])): ?>
        <div class="search-stats">
            <strong><?= $result['total'] ?></strong> risultati per "<strong><?= ocms_escape($query) ?></strong>"
            <?php if ($result['total'] > $perPage): ?> — pagina <?= $page ?> di <?= $totalPages ?><?php endif; ?>
        </div>

        <?php foreach ($result['results'] as $r): ?>
        <div class="result-item">
            <div class="result-icon <?= ocms_escape($r['type']) ?>"><?= $r['icon'] ?? '' ?></div>
            <div class="result-body">
                <div class="result-title">
                    <a href="<?= ocms_escape($r['admin_url'] ?? '#') ?>"><?= $r['highlights']['title'] ?? ocms_escape($r['title']) ?></a>
                </div>
                <div class="result-meta">
                    <span class="badge badge-published" style="font-size:0.7rem;"><?= ocms_escape($r['type_label'] ?? $r['type']) ?></span>
                    <?php if (!empty($r['status'])): ?>
                        <span><?= $r['status'] === 'published' ? 'Pubblicato' : 'Bozza' ?></span>
                    <?php endif; ?>
                    <?php if (!empty($r['category'])): ?>
                        <span>Cat: <?= ocms_escape($r['category']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($r['date'])): ?>
                        <span><?= ocms_format_date($r['date'], 'd/m/Y') ?></span>
                    <?php endif; ?>
                    <span class="result-score">Score: <?= $r['score'] ?></span>
                </div>
                <?php if (!empty($r['highlights']['excerpt'])): ?>
                    <div class="result-snippet"><?= $r['highlights']['excerpt'] ?></div>
                <?php elseif (!empty($r['highlights']['content'])): ?>
                    <div class="result-snippet"><?= $r['highlights']['content'] ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Paginazione -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++):
                $params = $_GET; $params['page'] = $i;
                $url = ocms_base_url() . '/admin/search/full?' . http_build_query($params);
            ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= ocms_escape($url) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card">
            <div class="empty-state" style="padding:40px 20px;">
                <div class="icon">&#128269;</div>
                <h3>Nessun risultato</h3>
                <p>Prova con parole diverse o rimuovi alcuni filtri.</p>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="empty-state" style="padding:40px 20px;">
            <div class="icon">&#128269;</div>
            <h3>Cerca in tutto il CMS</h3>
            <p>Pagine, articoli, utenti, media, form, menu — tutto in un unico posto.<br>
            Usa i filtri per restringere i risultati.</p>
        </div>
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('search-input');
    const box = document.getElementById('suggestions-box');
    const BASE = '<?= ocms_base_url() ?>';
    let timer;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { box.classList.remove('open'); return; }
        timer = setTimeout(async () => {
            try {
                const res = await fetch(BASE + '/admin/search/suggest?q=' + encodeURIComponent(q));
                const data = await res.json();
                if (data.length) {
                    box.innerHTML = data.map(s => '<div class="search-suggest-item" data-q="'+s.replace(/"/g,'&quot;')+'">'+s+'</div>').join('');
                    box.classList.add('open');
                    box.querySelectorAll('.search-suggest-item').forEach(el => {
                        el.addEventListener('mousedown', e => {
                            e.preventDefault();
                            input.value = el.dataset.q;
                            box.classList.remove('open');
                            document.getElementById('search-form').submit();
                        });
                    });
                } else { box.classList.remove('open'); }
            } catch(e) {}
        }, 200);
    });

    input.addEventListener('blur', () => setTimeout(() => box.classList.remove('open'), 150));
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2) input.dispatchEvent(new Event('input')); });
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
