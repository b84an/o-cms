<?php
$pageTitle = 'Media';
$activeMenu = 'media';

ob_start();
?>
<style>
.upload-zone {
    border: 2px dashed var(--border); border-radius: 16px;
    padding: 40px; text-align: center; cursor: pointer;
    transition: all 0.2s; margin-bottom: 24px; position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--primary); background: rgba(99,102,241,0.05);
}
.upload-zone .icon { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }
.upload-zone p { color: var(--text-muted); font-size: 0.9rem; }
.upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

.upload-progress { display: none; margin-bottom: 24px; }
.progress-bar { height: 4px; background: var(--bg-input); border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; background: var(--primary); border-radius: 4px; transition: width 0.3s; width: 0%; }

.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }

.media-item {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 12px; overflow: hidden; cursor: pointer;
    transition: all 0.15s; position: relative;
}
.media-item:hover { border-color: var(--primary); transform: translateY(-2px); }
.media-item.selected { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary); }

.media-thumb {
    width: 100%; height: 140px; object-fit: cover; display: block;
    background: var(--bg-input);
}
.media-thumb-placeholder {
    width: 100%; height: 140px; display: flex; align-items: center; justify-content: center;
    background: var(--bg-input); font-size: 2rem; opacity: 0.3;
}
.media-info { padding: 10px 12px; }
.media-name { font-size: 0.8rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.media-size { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }

/* Detail panel */
.media-detail-overlay {
    display: none; position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.media-detail-overlay.open { display: flex; }
.media-detail-panel {
    background: var(--bg-card); border-radius: 16px;
    max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto;
    margin: 20px; padding: 24px;
}
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Media</h1>
    <span style="color:var(--text-muted);font-size:0.875rem;"><?= count($media) ?> file</span>
</div>

<!-- Upload Zone -->
<div class="upload-zone" id="upload-zone">
    <div class="icon">&#128228;</div>
    <p><strong>Trascina i file qui</strong> o clicca per selezionare</p>
    <p style="font-size:0.8rem;margin-top:4px;">Immagini, PDF, documenti, video (max 10MB)</p>
    <input type="file" id="file-input" multiple accept="image/*,application/pdf,.zip,.mp4,.mp3">
</div>

<div class="upload-progress" id="upload-progress">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <span style="font-size:0.8rem;font-weight:600;" id="upload-status">Caricamento...</span>
        <span style="font-size:0.8rem;color:var(--text-muted);" id="upload-percent">0%</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
</div>

<?php if (empty($media)): ?>
    <div class="card">
        <div class="empty-state" style="padding:40px 20px;">
            <h3>Nessun media</h3>
            <p>Carica il tuo primo file usando l'area sopra.</p>
        </div>
    </div>
<?php else: ?>
    <div class="media-grid" id="media-grid">
        <?php foreach ($media as $m):
            $isImage = str_starts_with($m['mime_type'] ?? '', 'image/');
            $sizeStr = $m['size'] < 1024*1024
                ? round($m['size']/1024) . ' KB'
                : round($m['size']/(1024*1024), 1) . ' MB';
        ?>
        <div class="media-item" data-id="<?= ocms_escape($m['id']) ?>"
             data-url="<?= ocms_escape($m['url']) ?>"
             data-name="<?= ocms_escape($m['original_name'] ?? $m['filename']) ?>"
             data-mime="<?= ocms_escape($m['mime_type'] ?? '') ?>"
             data-size="<?= ocms_escape($sizeStr) ?>"
             data-date="<?= ocms_escape(ocms_format_date($m['uploaded_at'] ?? '')) ?>"
             data-dims="<?= isset($m['width']) ? $m['width'].'x'.$m['height'] : '' ?>">
            <?php if ($isImage): ?>
                <img src="<?= ocms_base_url() . ocms_escape($m['url']) ?>" class="media-thumb" alt="" loading="lazy">
            <?php else: ?>
                <div class="media-thumb-placeholder">&#128196;</div>
            <?php endif; ?>
            <div class="media-info">
                <div class="media-name"><?= ocms_escape($m['original_name'] ?? $m['filename']) ?></div>
                <div class="media-size"><?= $sizeStr ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Detail Overlay -->
<div class="media-detail-overlay" id="detail-overlay">
    <div class="media-detail-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:1.1rem;font-weight:700;" id="detail-name"></h3>
            <button type="button" style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;" onclick="closeDetail()">&#10005;</button>
        </div>
        <div id="detail-preview" style="margin-bottom:16px;text-align:center;"></div>
        <div style="display:grid;grid-template-columns:100px 1fr;gap:6px 12px;font-size:0.85rem;margin-bottom:20px;">
            <span style="color:var(--text-muted);font-weight:600;">URL</span>
            <span><input type="text" class="form-input" id="detail-url" readonly style="padding:6px 8px;font-size:0.8rem;cursor:text;" onclick="this.select()"></span>
            <span style="color:var(--text-muted);font-weight:600;">Tipo</span>
            <span id="detail-mime"></span>
            <span style="color:var(--text-muted);font-weight:600;">Dimensione</span>
            <span id="detail-size"></span>
            <span style="color:var(--text-muted);font-weight:600;">Risoluzione</span>
            <span id="detail-dims"></span>
            <span style="color:var(--text-muted);font-weight:600;">Caricato il</span>
            <span id="detail-date"></span>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn btn-danger btn-sm" id="btn-delete-media">Elimina</button>
            <a href="#" class="btn btn-primary btn-sm" id="btn-download" target="_blank" download>Scarica</a>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const CSRF = '<?= ocms_csrf_token() ?>';
    const BASE = '<?= ocms_base_url() ?>';
    const zone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');

    // Drag & drop
    ['dragover','dragenter'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('dragover'); }));
    zone.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) uploadFiles(ev.dataTransfer.files); });
    fileInput.addEventListener('change', () => { if (fileInput.files.length) uploadFiles(fileInput.files); });

    async function uploadFiles(files) {
        const progress = document.getElementById('upload-progress');
        const fill = document.getElementById('progress-fill');
        const status = document.getElementById('upload-status');
        const percent = document.getElementById('upload-percent');
        progress.style.display = 'block';

        for (let i = 0; i < files.length; i++) {
            status.textContent = `Caricamento ${i+1}/${files.length}: ${files[i].name}`;
            fill.style.width = ((i/files.length)*100) + '%';
            percent.textContent = Math.round((i/files.length)*100) + '%';

            const fd = new FormData();
            fd.append('file', files[i]);
            fd.append('_csrf_token', CSRF);

            try {
                const res = await fetch(BASE + '/admin/media/upload', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) alert('Errore: ' + (data.error || 'Upload fallito'));
            } catch(e) {
                alert('Errore rete: ' + e.message);
            }
        }

        fill.style.width = '100%';
        percent.textContent = '100%';
        status.textContent = 'Completato!';
        setTimeout(() => location.reload(), 500);
    }

    // Detail
    let currentMediaId = null;
    document.querySelectorAll('.media-item').forEach(item => {
        item.addEventListener('click', function() {
            currentMediaId = this.dataset.id;
            document.getElementById('detail-name').textContent = this.dataset.name;
            document.getElementById('detail-url').value = this.dataset.url;
            document.getElementById('detail-mime').textContent = this.dataset.mime;
            document.getElementById('detail-size').textContent = this.dataset.size;
            document.getElementById('detail-dims').textContent = this.dataset.dims || '—';
            document.getElementById('detail-date').textContent = this.dataset.date;
            document.getElementById('btn-download').href = BASE + this.dataset.url;

            const preview = document.getElementById('detail-preview');
            if (this.dataset.mime.startsWith('image/')) {
                preview.innerHTML = '<img src="' + BASE + this.dataset.url + '" style="max-width:100%;max-height:300px;border-radius:8px;">';
            } else {
                preview.innerHTML = '<div style="font-size:4rem;opacity:0.2;">&#128196;</div>';
            }

            document.getElementById('detail-overlay').classList.add('open');
        });
    });

    window.closeDetail = () => document.getElementById('detail-overlay').classList.remove('open');
    document.getElementById('detail-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeDetail(); });

    document.getElementById('btn-delete-media').addEventListener('click', async function() {
        if (!confirm('Eliminare questo file?')) return;
        const res = await fetch(BASE + '/admin/media/delete/' + currentMediaId, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf_token: CSRF })
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Errore');
    });
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
