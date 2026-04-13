<?php
$pageTitle = 'Estensioni';
$activeMenu = 'extensions';

ob_start();
?>

<div class="page-header">
    <h1>Estensioni</h1>
    <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('upload-modal').style.display='flex'">
            Installa da ZIP
        </button>
        <a href="<?= ocms_base_url() ?>/admin/extensions/wizard" class="btn btn-primary">+ Crea Estensione</a>
    </div>
</div>

<?php if (empty($extensions)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon">&#129513;</div>
            <h3>Nessuna estensione</h3>
            <p>Crea la tua prima estensione con il wizard o installane una da file ZIP.</p>
            <div style="display:flex;gap:10px;justify-content:center;margin-top:8px;">
                <a href="<?= ocms_base_url() ?>/admin/extensions/wizard" class="btn btn-primary">Crea con Wizard</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="ext-grid">
        <?php foreach ($extensions as $ext): ?>
        <div class="ext-card" data-id="<?= ocms_escape($ext['id']) ?>">
            <div class="ext-card-header">
                <div class="ext-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6.5-7z"/><path d="M10 12l2 2 4-4"/></svg>
                </div>
                <div class="ext-toggle-wrap">
                    <label class="toggle-switch">
                        <input type="checkbox" class="ext-toggle" data-id="<?= ocms_escape($ext['id']) ?>"
                               <?= ($ext['enabled'] ?? false) ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            <div class="ext-card-body">
                <h3 class="ext-name"><?= ocms_escape($ext['name']) ?></h3>
                <p class="ext-desc"><?= ocms_escape($ext['description'] ?: 'Nessuna descrizione') ?></p>
                <div class="ext-meta">
                    <span>v<?= ocms_escape($ext['version'] ?? '1.0.0') ?></span>
                    <?php if (!empty($ext['author'])): ?>
                        <span><?= ocms_escape($ext['author']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ext-card-footer">
                <a href="<?= ocms_base_url() ?>/admin/extensions/detail/<?= ocms_escape($ext['id']) ?>" class="btn btn-secondary btn-sm">Dettagli</a>
                <a href="<?= ocms_base_url() ?>/admin/extensions/download/<?= ocms_escape($ext['id']) ?>" class="btn btn-secondary btn-sm">Scarica ZIP</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal Upload ZIP -->
<div id="upload-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div class="card" style="max-width:460px;width:100%;margin:20px;">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:20px;">Installa Estensione da ZIP</h3>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/extensions/install" enctype="multipart/form-data">
            <?= ocms_csrf_field() ?>
            <div class="form-group">
                <label>Pacchetto ZIP</label>
                <input type="file" name="package" accept=".zip" required
                       class="form-input" style="padding:10px;">
            </div>
            <div class="form-hint" style="margin-bottom:20px;">
                Il pacchetto deve contenere un file <code>extension.json</code> valido.
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="this.closest('#upload-modal').style.display='none'">Annulla</button>
                <button type="submit" class="btn btn-primary">Installa</button>
            </div>
        </form>
    </div>
</div>

<style>
.ext-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

.ext-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden; transition: transform 0.15s, box-shadow 0.15s;
}
.ext-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }

.ext-card-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 20px 0;
}
.ext-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: rgba(99,102,241,0.15); color: var(--primary-light);
    display: flex; align-items: center; justify-content: center;
}
.ext-card-body { padding: 16px 20px; }
.ext-name { font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; }
.ext-desc { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 12px; }
.ext-meta { display: flex; gap: 16px; font-size: 0.75rem; color: var(--text-muted); }

.ext-card-footer {
    display: flex; gap: 8px; padding: 0 20px 20px;
}

/* Toggle Switch */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; inset: 0;
    background: var(--bg-input); border: 1px solid var(--border);
    border-radius: 24px; transition: 0.2s;
}
.toggle-slider::before {
    content: ''; position: absolute; height: 18px; width: 18px;
    left: 2px; bottom: 2px; background: var(--text-muted);
    border-radius: 50%; transition: 0.2s;
}
input:checked + .toggle-slider { background: var(--primary); border-color: var(--primary); }
input:checked + .toggle-slider::before { transform: translateX(20px); background: white; }
</style>

<?php $content = ob_get_clean(); ob_start(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const CSRF = '<?= ocms_csrf_token() ?>';
    const BASE = '<?= ocms_base_url() ?>';

    // Toggle attiva/disattiva
    document.querySelectorAll('.ext-toggle').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const id = this.dataset.id;
            try {
                const res = await fetch(BASE + '/admin/extensions/toggle/' + id, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ _csrf_token: CSRF })
                });
                const data = await res.json();
                if (!data.success) {
                    this.checked = !this.checked;
                    alert(data.error || 'Errore');
                }
            } catch(e) {
                this.checked = !this.checked;
                alert('Errore di rete');
            }
        });
    });
});
</script>

<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
