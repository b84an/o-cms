<?php
$pageTitle = $ext['name'] ?? 'Estensione';
$activeMenu = 'extensions';

ob_start();
?>

<div class="page-header">
    <div>
        <a href="<?= ocms_base_url() ?>/admin/extensions" style="font-size:0.8rem;color:var(--text-muted);">← Tutte le estensioni</a>
        <h1 style="margin-top:4px;"><?= ocms_escape($ext['name']) ?></h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <label class="toggle-switch">
            <input type="checkbox" class="ext-toggle" data-id="<?= ocms_escape($ext['id']) ?>"
                   <?= ($ext['enabled'] ?? false) ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
        </label>
        <span style="font-size:0.85rem;font-weight:600;color:<?= ($ext['enabled'] ?? false) ? 'var(--success)' : 'var(--text-muted)' ?>;"
              id="status-label">
            <?= ($ext['enabled'] ?? false) ? 'Attiva' : 'Disattiva' ?>
        </span>
        <a href="<?= ocms_base_url() ?>/admin/extensions/download/<?= ocms_escape($ext['id']) ?>" class="btn btn-secondary btn-sm">Scarica ZIP</a>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/extensions/uninstall/<?= ocms_escape($ext['id']) ?>"
              onsubmit="return confirm('Disinstallare questa estensione? Tutti i file verranno eliminati.');" style="display:inline;">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Disinstalla</button>
        </form>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">
    <!-- INFO -->
    <div>
        <!-- Manifest -->
        <div class="card" style="margin-bottom:20px;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Informazioni</h3>
            <div style="display:grid;grid-template-columns:140px 1fr;gap:8px 16px;font-size:0.875rem;">
                <span style="color:var(--text-muted);font-weight:600;">ID</span>
                <span><code><?= ocms_escape($ext['id']) ?></code></span>

                <span style="color:var(--text-muted);font-weight:600;">Versione</span>
                <span>v<?= ocms_escape($ext['version'] ?? '1.0.0') ?></span>

                <span style="color:var(--text-muted);font-weight:600;">Descrizione</span>
                <span><?= ocms_escape($ext['description'] ?: '—') ?></span>

                <span style="color:var(--text-muted);font-weight:600;">Autore</span>
                <span>
                    <?php if (!empty($ext['author_url'])): ?>
                        <a href="<?= ocms_escape($ext['author_url']) ?>" target="_blank"><?= ocms_escape($ext['author'] ?: '—') ?></a>
                    <?php else: ?>
                        <?= ocms_escape($ext['author'] ?: '—') ?>
                    <?php endif; ?>
                </span>

                <span style="color:var(--text-muted);font-weight:600;">Licenza</span>
                <span><?= ocms_escape($ext['license'] ?? '—') ?></span>

                <span style="color:var(--text-muted);font-weight:600;">Entry Point</span>
                <span><code><?= ocms_escape($ext['entry_point'] ?? 'boot.php') ?></code></span>

                <span style="color:var(--text-muted);font-weight:600;">Pannello Admin</span>
                <span><?= ($ext['has_admin'] ?? false) ? 'Si' : 'No' ?></span>

                <span style="color:var(--text-muted);font-weight:600;">Frontend</span>
                <span><?= ($ext['has_frontend'] ?? false) ? 'Si' : 'No' ?></span>

                <?php if (!empty($ext['installed_at'])): ?>
                <span style="color:var(--text-muted);font-weight:600;">Installata il</span>
                <span><?= ocms_format_date($ext['installed_at']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Permessi -->
        <?php if (!empty($ext['permissions'])): ?>
        <div class="card" style="margin-bottom:20px;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px;">Permessi Richiesti</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($ext['permissions'] as $p): ?>
                    <span class="badge badge-draft"><?= ocms_escape($p) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Se attiva e ha admin, link diretto -->
        <?php if (($ext['enabled'] ?? false) && ($ext['has_admin'] ?? false)): ?>
        <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(139,92,246,0.05));border-color:rgba(99,102,241,0.2);">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;">Pannello Estensione</h3>
                    <p style="font-size:0.85rem;color:var(--text-muted);">L'estensione è attiva. Apri il suo pannello di gestione.</p>
                </div>
                <a href="<?= ocms_base_url() ?>/admin/ext/<?= ocms_escape($ext['id']) ?>" class="btn btn-primary">Apri</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- FILE -->
    <div>
        <div class="card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">File</h3>
            <div style="font-size:0.8rem;font-family:monospace;line-height:1.8;">
                <?php foreach ($files as $f):
                    $indent = substr_count($f['path'], '/');
                    $name = basename($f['path']);
                    $icon = $f['is_dir'] ? '&#128193;' : '&#128196;';
                    $size = $f['is_dir'] ? '' : ' <span style="color:var(--text-muted);">(' . number_format($f['size']) . ' B)</span>';
                ?>
                <div style="padding-left:<?= $indent * 16 ?>px;">
                    <?= $icon ?> <?= ocms_escape($name) ?><?= $size ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
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

    document.querySelector('.ext-toggle').addEventListener('change', async function() {
        const id = this.dataset.id;
        const label = document.getElementById('status-label');
        try {
            const res = await fetch(BASE + '/admin/extensions/toggle/' + id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf_token: CSRF })
            });
            const data = await res.json();
            if (data.success) {
                label.textContent = data.enabled ? 'Attiva' : 'Disattiva';
                label.style.color = data.enabled ? 'var(--success)' : 'var(--text-muted)';
                if (data.enabled) location.reload(); // Mostra link pannello
            } else {
                this.checked = !this.checked;
                alert(data.error || 'Errore');
            }
        } catch(e) {
            this.checked = !this.checked;
        }
    });
});
</script>

<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
