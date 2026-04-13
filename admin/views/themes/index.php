<?php
$pageTitle = 'Temi';
$activeMenu = 'themes';

ob_start();
?>
<style>
.theme-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; }
.theme-card {
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:16px; overflow:hidden; transition:transform 0.15s, box-shadow 0.15s;
}
.theme-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.2); }
.theme-card.active { border-color:var(--primary); box-shadow:0 0 0 2px var(--primary); }
.theme-preview {
    height:160px; display:flex; align-items:center; justify-content:center;
    font-size:3rem; opacity:0.15; position:relative; overflow:hidden;
}
.theme-preview-colors {
    position:absolute; bottom:0; left:0; right:0; height:4px; display:flex;
}
.theme-preview-colors span { flex:1; }
.active-badge {
    position:absolute; top:12px; right:12px; padding:4px 10px;
    background:var(--primary); color:white; border-radius:6px;
    font-size:0.7rem; font-weight:700; text-transform:uppercase;
}
.theme-info { padding:16px 20px; }
.theme-name { font-size:1rem; font-weight:700; margin-bottom:4px; }
.theme-desc { font-size:0.8rem; color:var(--text-muted); margin-bottom:12px; line-height:1.4; }
.theme-meta { font-size:0.75rem; color:var(--text-muted); display:flex; gap:12px; margin-bottom:12px; }
.theme-actions { display:flex; gap:8px; flex-wrap:wrap; }
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Temi</h1>
    <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('upload-modal').style.display='flex'">Installa da ZIP</button>
        <a href="<?= ocms_base_url() ?>/admin/themes/wizard" class="btn btn-primary">+ Crea Tema</a>
    </div>
</div>

<div class="theme-grid">
    <?php foreach ($themes as $t):
        $colors = $t['colors'] ?? [];
    ?>
    <div class="theme-card <?= ($t['active'] ?? false) ? 'active' : '' ?>">
        <div class="theme-preview" style="background:<?= ocms_escape($colors['bg'] ?? '#0f172a') ?>;">
            &#127912;
            <?php if ($t['active'] ?? false): ?>
                <span class="active-badge">Attivo</span>
            <?php endif; ?>
            <div class="theme-preview-colors">
                <span style="background:<?= ocms_escape($colors['primary'] ?? '#6366f1') ?>;"></span>
                <span style="background:<?= ocms_escape($colors['secondary'] ?? '#a78bfa') ?>;"></span>
                <span style="background:<?= ocms_escape($colors['bg'] ?? '#0f172a') ?>;"></span>
                <span style="background:<?= ocms_escape($colors['text'] ?? '#f1f5f9') ?>;"></span>
            </div>
        </div>
        <div class="theme-info">
            <div class="theme-name"><?= ocms_escape($t['name'] ?? $t['id']) ?></div>
            <div class="theme-desc"><?= ocms_escape($t['description'] ?? 'Nessuna descrizione') ?></div>
            <div class="theme-meta">
                <span>v<?= ocms_escape($t['version'] ?? '1.0') ?></span>
                <?php if (!empty($t['author'])): ?><span><?= ocms_escape($t['author']) ?></span><?php endif; ?>
                <span><?= $t['file_count'] ?? 0 ?> file</span>
            </div>
            <div class="theme-actions">
                <?php if (!($t['active'] ?? false)): ?>
                <form method="POST" action="<?= ocms_base_url() ?>/admin/themes/activate/<?= ocms_escape($t['id']) ?>" style="display:inline;">
                    <?= ocms_csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">Attiva</button>
                </form>
                <?php endif; ?>
                <a href="<?= ocms_base_url() ?>/admin/themes/download/<?= ocms_escape($t['id']) ?>" class="btn btn-secondary btn-sm">Scarica ZIP</a>
                <?php if (!($t['active'] ?? false) && $t['id'] !== 'flavor'): ?>
                <form method="POST" action="<?= ocms_base_url() ?>/admin/themes/delete/<?= ocms_escape($t['id']) ?>"
                      onsubmit="return confirm('Eliminare questo tema?');" style="display:inline;">
                    <?= ocms_csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Upload ZIP -->
<div id="upload-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div class="card" style="max-width:460px;width:100%;margin:20px;">
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:20px;">Installa Tema da ZIP</h3>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/themes/install" enctype="multipart/form-data">
            <?= ocms_csrf_field() ?>
            <div class="form-group">
                <label>Pacchetto ZIP del tema</label>
                <input type="file" name="package" accept=".zip" required class="form-input" style="padding:10px;">
            </div>
            <div class="form-hint" style="margin-bottom:20px;">Il pacchetto deve contenere una cartella con <code>theme.json</code> e <code>templates/</code>.</div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="this.closest('#upload-modal').style.display='none'">Annulla</button>
                <button type="submit" class="btn btn-primary">Installa</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
