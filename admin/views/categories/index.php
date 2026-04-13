<?php
$pageTitle = 'Categorie';
$activeMenu = 'articles';

// Mappa per dropdown "sposta in"
$catSlugs = array_column($categories, 'slug');

ob_start();
?>
<style>
.cat-form { display:flex; gap:10px; margin-bottom:24px; }
.cat-form .form-input { flex:1; }
.cat-delete-modal { display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; }
.cat-delete-modal.open { display:flex; }
.cat-delete-box { background:var(--bg-card, #1a1f2e); border:1px solid var(--border); border-radius:14px; padding:32px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.5); }
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Categorie</h1>
    <a href="<?= ocms_base_url() ?>/admin/articles" class="btn btn-secondary">&larr; Articoli</a>
</div>

<!-- Form aggiungi -->
<div class="card" style="margin-bottom:24px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Aggiungi Categoria</h3>
    <div class="cat-form" id="cat-form">
        <input type="text" class="form-input" id="cat-name" placeholder="Nome categoria">
        <input type="text" class="form-input" id="cat-desc" placeholder="Descrizione (opzionale)" style="max-width:300px;">
        <button type="button" class="btn btn-primary" id="btn-add-cat">Aggiungi</button>
    </div>
</div>

<?php if (empty($categories)): ?>
    <div class="card">
        <div class="empty-state" style="padding:40px 20px;">
            <h3>Nessuna categoria</h3>
            <p>Crea la prima categoria usando il form sopra.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Slug</th>
                        <th>Descrizione</th>
                        <th>Articoli</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-weight:600;"><?= ocms_escape($cat['name']) ?></td>
                        <td style="color:var(--text-muted);"><?= ocms_escape($cat['slug']) ?></td>
                        <td style="color:var(--text-muted);font-size:0.85rem;"><?= ocms_escape($cat['description'] ?? '') ?></td>
                        <td>
                            <?php if (($cat['article_count'] ?? 0) > 0): ?>
                                <a href="<?= ocms_base_url() ?>/admin/articles?cat=<?= ocms_escape($cat['slug']) ?>" class="badge badge-published" style="text-decoration:none;">
                                    <?= $cat['article_count'] ?>
                                </a>
                            <?php else: ?>
                                <span class="badge badge-draft">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;">
                                <a href="<?= ocms_base_url() ?>/admin/articles?cat=<?= ocms_escape($cat['slug']) ?>" class="btn btn-secondary btn-sm">Vedi articoli</a>
                                <button type="button" class="btn btn-danger btn-sm btn-delete-cat"
                                    data-slug="<?= ocms_escape($cat['slug']) ?>"
                                    data-name="<?= ocms_escape($cat['name']) ?>"
                                    data-count="<?= $cat['article_count'] ?? 0 ?>">Elimina</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal conferma eliminazione -->
<div class="cat-delete-modal" id="delete-modal">
    <div class="cat-delete-box">
        <h3 style="margin-bottom:16px;" id="modal-title">Elimina categoria</h3>
        <div id="modal-body"></div>
        <form method="POST" id="modal-form" style="margin-top:20px;">
            <?= ocms_csrf_field() ?>
            <input type="hidden" name="move_to" id="modal-move-to" value="">
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('delete-modal').classList.remove('open')">Annulla</button>
                <button type="submit" class="btn btn-danger" id="modal-submit">Elimina</button>
            </div>
        </form>
    </div>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.getElementById('btn-add-cat').addEventListener('click', async function() {
    const name = document.getElementById('cat-name').value.trim();
    const desc = document.getElementById('cat-desc').value.trim();
    if (!name) { document.getElementById('cat-name').focus(); return; }

    const res = await fetch('<?= ocms_base_url() ?>/admin/categories/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ _csrf_token: '<?= ocms_csrf_token() ?>', name, description: desc })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Errore');
});
document.getElementById('cat-name').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btn-add-cat').click(); }
});

// Delete con modal
const allCats = <?= json_encode(array_map(fn($c) => ['slug' => $c['slug'], 'name' => $c['name']], $categories), JSON_UNESCAPED_UNICODE) ?>;

document.querySelectorAll('.btn-delete-cat').forEach(btn => {
    btn.addEventListener('click', function() {
        const slug = this.dataset.slug;
        const name = this.dataset.name;
        const count = parseInt(this.dataset.count);
        const modal = document.getElementById('delete-modal');
        const body = document.getElementById('modal-body');
        const form = document.getElementById('modal-form');

        form.action = '<?= ocms_base_url() ?>/admin/categories/delete/' + slug;
        document.getElementById('modal-title').textContent = 'Elimina "' + name + '"';

        if (count === 0) {
            body.innerHTML = '<p style="color:var(--text-muted);">Questa categoria e vuota. Vuoi eliminarla?</p>';
            document.getElementById('modal-move-to').value = '';
        } else {
            let options = '<option value="">— Rimuovi categoria dagli articoli —</option>';
            allCats.forEach(c => {
                if (c.slug !== slug) options += '<option value="' + c.slug + '">' + c.name + '</option>';
            });
            body.innerHTML = '<div style="padding:12px 16px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);border-radius:8px;margin-bottom:16px;">'
                + '<strong style="color:#f59e0b;">Attenzione:</strong> questa categoria contiene <strong>' + count + ' articoli</strong>.</div>'
                + '<p style="color:var(--text-muted);margin-bottom:12px;">Cosa fare con gli articoli?</p>'
                + '<select class="form-input" onchange="document.getElementById(\'modal-move-to\').value=this.value" style="width:100%;">'
                + options + '</select>';
            document.getElementById('modal-move-to').value = '';
        }

        modal.classList.add('open');
    });
});

// Chiudi modal cliccando fuori
document.getElementById('delete-modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
