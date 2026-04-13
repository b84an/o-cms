<?php
$isNew = ($gallery === null);
$pageTitle = $isNew ? 'Nuova Galleria' : 'Modifica Galleria';
$activeMenu = 'galleries';

$gallery = $gallery ?? [
    'id' => '', 'title' => '', 'slug' => '', 'description' => '',
    'cover_image' => '', 'images' => [], 'layout' => 'masonry',
    'tags' => [], 'status' => 'draft',
    'author' => '', 'created_at' => '', 'updated_at' => '',
];

$images = $gallery['images'] ?? [];

ob_start();
?>

<style>
.gallery-editor-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 960px) {
    .gallery-editor-layout { grid-template-columns: 1fr; }
}

.upload-zone {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 32px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
}
.upload-zone.dragover {
    border-color: var(--primary);
    background: rgba(99,102,241,0.08);
}
.upload-zone.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.upload-queue { margin-top: 12px; }
.upload-queue-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-size: 0.8rem;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
}
.upload-queue-item:last-child { border-bottom: none; }
.upload-queue-bar {
    flex: 1;
    height: 4px;
    background: var(--bg);
    border-radius: 4px;
    overflow: hidden;
}
.upload-queue-bar-fill {
    height: 100%;
    background: var(--primary);
    width: 0%;
    transition: width .3s;
}

.img-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin-top: 16px;
}
.img-card {
    background: var(--bg);
    border: 2px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    transition: border-color .2s, opacity .2s, transform .15s;
    position: relative;
}
.img-card.dragging {
    opacity: 0.4;
    transform: scale(0.95);
}
.img-card.drag-over {
    border-color: var(--primary);
}
.img-card-cover {
    border-color: #22c55e !important;
    box-shadow: 0 0 0 2px rgba(34,197,94,0.3);
}
.img-card-thumb {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
    background: var(--bg-card);
}
.img-card-body { padding: 10px; }
.img-card-body input,
.img-card-body textarea {
    width: 100%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    padding: 6px 8px;
    font-size: 0.8rem;
    font-family: inherit;
    margin-bottom: 6px;
    resize: vertical;
}
.img-card-body textarea { min-height: 40px; }
.img-card-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    padding: 0 10px 10px;
}
.img-card-actions button {
    padding: 4px 8px;
    font-size: 0.7rem;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-muted);
    cursor: pointer;
    font-family: inherit;
    transition: background .15s, color .15s;
}
.img-card-actions button:hover { background: var(--bg); color: var(--text); }
.img-card-actions .btn-cover-active {
    background: #22c55e;
    color: #fff;
    border-color: #22c55e;
}
.img-card-actions .btn-delete:hover { background: #ef4444; color: #fff; border-color: #ef4444; }
.img-card-drag {
    cursor: grab;
    padding: 4px 6px;
    color: var(--text-muted);
    font-size: 1rem;
    user-select: none;
}
.img-card-drag:active { cursor: grabbing; }

.shortcode-copy {
    display: flex;
    gap: 6px;
}
.shortcode-copy input {
    flex: 1;
}
.shortcode-copy button {
    white-space: nowrap;
}
</style>

<form method="POST" action="<?= ocms_base_url() ?>/admin/galleries/save" id="gallery-form" enctype="multipart/form-data">
    <?= ocms_csrf_field() ?>
    <input type="hidden" name="id" value="<?= ocms_escape($gallery['id']) ?>">
    <input type="hidden" name="original_slug" value="<?= ocms_escape($gallery['slug']) ?>">
    <input type="hidden" name="created_at" value="<?= ocms_escape($gallery['created_at']) ?>">
    <input type="hidden" name="cover_image" id="cover-image-input" value="<?= ocms_escape($gallery['cover_image']) ?>">
    <input type="hidden" name="images" id="images-data" value="<?= ocms_escape(json_encode($images)) ?>">

    <div class="page-header">
        <h1><?= $isNew ? 'Nuova Galleria' : 'Modifica Galleria' ?></h1>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= ocms_base_url() ?>/admin/galleries" class="btn btn-secondary">Torna alla lista</a>
            <button type="submit" class="btn btn-primary">Salva</button>
        </div>
    </div>

    <div class="gallery-editor-layout">
        <!-- MAIN COLUMN -->
        <div class="editor-main">
            <!-- Titolo / Slug / Descrizione -->
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group">
                    <label>Titolo</label>
                    <input type="text" name="title" id="gal-title" class="form-input" required
                           placeholder="Titolo della galleria"
                           value="<?= ocms_escape($gallery['title']) ?>">
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" id="gal-slug" class="form-input"
                           placeholder="auto-generato dal titolo"
                           value="<?= ocms_escape($gallery['slug']) ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Descrizione</label>
                    <textarea name="description" class="form-input" rows="3"
                              placeholder="Descrizione opzionale della galleria..."
                              style="min-height:80px;resize:vertical;"><?= ocms_escape($gallery['description']) ?></textarea>
                </div>
            </div>

            <!-- Immagini -->
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
                    Immagini
                    <span id="img-count" style="font-weight:400;font-size:0.8rem;color:var(--text-muted);margin-left:8px;">(<?= count($images) ?>)</span>
                </h3>

                <?php if ($isNew): ?>
                <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.9rem;background:var(--bg);border-radius:8px;border:1px solid var(--border);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.5;">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                    Salva prima la galleria, poi potrai caricare le immagini
                </div>
                <?php else: ?>
                <div id="upload-zone" class="upload-zone">
                    <div style="color:var(--text-muted);font-size:0.85rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.5;">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                        </svg>
                        Trascina qui le immagini o <span style="color:var(--primary-light);text-decoration:underline;">clicca per selezionare</span>
                        <div style="font-size:0.7rem;margin-top:4px;opacity:.6;">Upload multiplo supportato (JPG, PNG, WebP, GIF)</div>
                    </div>
                    <input type="file" id="upload-file" accept="image/*" multiple style="display:none;">
                </div>
                <div id="upload-queue" class="upload-queue"></div>
                <?php endif; ?>

                <div id="img-grid" class="img-grid">
                    <?php /* rendered by JS */ ?>
                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="editor-sidebar">
            <!-- Pubblicazione -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Pubblicazione</h3>
                <div class="form-group">
                    <label>Stato</label>
                    <select name="status" id="gal-status" class="form-select">
                        <option value="draft" <?= ($gallery['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Bozza</option>
                        <option value="published" <?= ($gallery['status'] ?? '') === 'published' ? 'selected' : '' ?>>Pubblicato</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Salva</button>
            </div>

            <!-- Layout -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Layout</h3>
                <div class="form-group" style="margin-bottom:0;">
                    <select name="layout" id="gal-layout" class="form-select">
                        <option value="masonry" <?= ($gallery['layout'] ?? 'masonry') === 'masonry' ? 'selected' : '' ?>>Masonry</option>
                        <option value="grid" <?= ($gallery['layout'] ?? '') === 'grid' ? 'selected' : '' ?>>Grid</option>
                        <option value="slider" <?= ($gallery['layout'] ?? '') === 'slider' ? 'selected' : '' ?>>Slider</option>
                    </select>
                </div>
            </div>

            <!-- Tag -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Tag</h3>
                <div class="form-group" style="margin-bottom:0;">
                    <input type="text" name="tags" id="gal-tags" class="form-input"
                           placeholder="tag1, tag2, tag3"
                           value="<?= ocms_escape(implode(', ', $gallery['tags'] ?? [])) ?>">
                    <div class="form-hint">Separati da virgola</div>
                    <?php if (!empty($allTags ?? [])): ?>
                    <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px;">
                        <?php foreach ($allTags as $t): ?>
                        <span class="badge" style="cursor:pointer;font-size:0.7rem;" onclick="addTag('<?= ocms_escape($t) ?>')"><?= ocms_escape($t) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$isNew): ?>
            <!-- Shortcode -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Shortcode</h3>
                <div class="shortcode-copy">
                    <input type="text" id="shortcode-input" class="form-input" readonly
                           value="[gallery:<?= ocms_escape($gallery['slug']) ?>]"
                           style="font-family:monospace;font-size:0.8rem;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyShortcode()" id="copy-btn">Copia</button>
                </div>
            </div>

            <!-- Statistiche -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Statistiche</h3>
                <div style="font-size:0.85rem;color:var(--text-muted);line-height:1.8;">
                    <div><strong>Immagini:</strong> <span id="stats-count"><?= count($images) ?></span></div>
                    <div><strong>Creata:</strong> <?= !empty($gallery['created_at']) ? ocms_escape(date('d/m/Y H:i', strtotime($gallery['created_at']))) : '—' ?></div>
                    <?php if (!empty($gallery['updated_at'])): ?>
                    <div><strong>Aggiornata:</strong> <?= ocms_escape(date('d/m/Y H:i', strtotime($gallery['updated_at']))) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const SLUG = '<?= ocms_escape($gallery['slug'] ?? '') ?>';
    const BASE = '<?= ocms_base_url() ?>';
    const CSRF = '<?= ocms_csrf_token() ?>';
    const IS_NEW = <?= $isNew ? 'true' : 'false' ?>;

    // ─── State ───
    let images = <?= json_encode($images) ?>;
    let coverImage = '<?= ocms_escape($gallery['cover_image'] ?? '') ?>';

    const imagesDataInput = document.getElementById('images-data');
    const coverImageInput = document.getElementById('cover-image-input');
    const imgGrid = document.getElementById('img-grid');
    const imgCount = document.getElementById('img-count');
    const statsCount = document.getElementById('stats-count');

    // ─── Auto slug from title ───
    const titleInput = document.getElementById('gal-title');
    const slugInput = document.getElementById('gal-slug');
    let slugEdited = !!slugInput.value;

    slugInput.addEventListener('input', function() { slugEdited = !!this.value; });
    titleInput.addEventListener('input', function() {
        if (!slugEdited) {
            slugInput.value = this.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '');
        }
    });

    // ─── Serialize before submit ───
    document.getElementById('gallery-form').addEventListener('submit', function() {
        imagesDataInput.value = JSON.stringify(images);
        coverImageInput.value = coverImage;
    });

    // ─── Render image grid ───
    function renderImages() {
        imgGrid.innerHTML = images.map((img, i) => {
            const isCover = coverImage && coverImage.includes(img.filename);
            return `
            <div class="img-card${isCover ? ' img-card-cover' : ''}" draggable="true" data-index="${i}" data-filename="${escape(img.filename)}">
                <img class="img-card-thumb" src="${BASE}/uploads/gallery/${SLUG}/${encodeURIComponent(img.filename)}" alt="">
                <div class="img-card-body">
                    <input type="text" placeholder="Titolo" value="${escape(img.title || '')}" data-field="title" data-index="${i}">
                    <textarea placeholder="Descrizione" rows="2" data-field="description" data-index="${i}">${escape(img.description || '')}</textarea>
                </div>
                <div class="img-card-actions">
                    <span class="img-card-drag" title="Trascina per riordinare">&#9776;</span>
                    <button type="button" class="${isCover ? 'btn-cover-active' : ''}" onclick="setCover(${i})" title="Imposta come copertina">
                        ${isCover ? '&#9733; Copertina' : '&#9734; Copertina'}
                    </button>
                    <span style="flex:1;"></span>
                    <button type="button" class="btn-delete" onclick="deleteImage(${i})" title="Elimina">&#10005; Elimina</button>
                </div>
            </div>`;
        }).join('');

        // Update counts
        if (imgCount) imgCount.textContent = '(' + images.length + ')';
        if (statsCount) statsCount.textContent = images.length;
        imagesDataInput.value = JSON.stringify(images);

        // Bind field changes
        imgGrid.querySelectorAll('[data-field]').forEach(el => {
            el.addEventListener('input', function() {
                const idx = parseInt(this.dataset.index);
                const field = this.dataset.field;
                if (images[idx]) {
                    images[idx][field] = this.value;
                    imagesDataInput.value = JSON.stringify(images);
                }
            });
        });

        // Bind drag reorder
        initDragReorder();
    }

    function escape(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ─── Set Cover ───
    window.setCover = function(index) {
        const img = images[index];
        if (!img) return;
        const path = '/uploads/gallery/' + SLUG + '/' + img.filename;
        if (coverImage === path) {
            coverImage = '';
        } else {
            coverImage = path;
        }
        coverImageInput.value = coverImage;
        renderImages();
    };

    // ─── Delete Image ───
    window.deleteImage = function(index) {
        const img = images[index];
        if (!img) return;
        if (!confirm('Eliminare questa immagine?')) return;

        fetch(BASE + '/admin/galleries/delete-image/' + encodeURIComponent(SLUG), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filename: img.filename, _csrf_token: CSRF })
        }).then(r => r.json()).then(data => {
            if (data.success || data.ok) {
                // If deleted image was cover, reset cover
                const path = '/uploads/gallery/' + SLUG + '/' + img.filename;
                if (coverImage === path) coverImage = '';
                images.splice(index, 1);
                // Re-index order
                images.forEach((im, i) => im.order = i);
                renderImages();
            } else {
                alert(data.error || 'Errore durante l\'eliminazione');
            }
        }).catch(() => {
            alert('Errore di rete');
        });
    };

    // ─── Drag & Drop Reorder ───
    let dragSrcIndex = null;

    function initDragReorder() {
        const cards = imgGrid.querySelectorAll('.img-card');
        cards.forEach(card => {
            card.addEventListener('dragstart', function(e) {
                dragSrcIndex = parseInt(this.dataset.index);
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragSrcIndex);
            });
            card.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                imgGrid.querySelectorAll('.img-card').forEach(c => c.classList.remove('drag-over'));
            });
            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });
            card.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });
            card.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                const targetIndex = parseInt(this.dataset.index);
                if (dragSrcIndex === null || dragSrcIndex === targetIndex) return;

                // Move in array
                const moved = images.splice(dragSrcIndex, 1)[0];
                images.splice(targetIndex, 0, moved);
                images.forEach((im, i) => im.order = i);
                dragSrcIndex = null;
                renderImages();
            });
        });
    }

    // ─── File Upload ───
    if (!IS_NEW) {
        const uploadZone = document.getElementById('upload-zone');
        const uploadFile = document.getElementById('upload-file');
        const uploadQueue = document.getElementById('upload-queue');

        if (uploadZone && uploadFile) {
            uploadZone.addEventListener('click', function() {
                uploadFile.click();
            });

            uploadFile.addEventListener('change', function() {
                if (this.files.length) uploadFiles(this.files);
                this.value = '';
            });

            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            uploadZone.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    uploadFiles(e.dataTransfer.files);
                }
            });
        }

        async function uploadFiles(fileList) {
            const files = Array.from(fileList).filter(f => f.type.startsWith('image/'));
            if (!files.length) return;

            uploadQueue.innerHTML = '';

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const itemId = 'uq-' + i;

                // Add queue item
                const itemEl = document.createElement('div');
                itemEl.className = 'upload-queue-item';
                itemEl.id = itemId;
                itemEl.innerHTML = `
                    <span style="min-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escape(file.name)}</span>
                    <div class="upload-queue-bar"><div class="upload-queue-bar-fill" id="${itemId}-bar"></div></div>
                    <span id="${itemId}-status" style="min-width:40px;text-align:right;">0%</span>`;
                uploadQueue.appendChild(itemEl);

                // Upload via XHR for progress
                await new Promise((resolve) => {
                    const fd = new FormData();
                    fd.append('image', file);
                    fd.append('_csrf_token', CSRF);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', BASE + '/admin/galleries/upload-images/' + encodeURIComponent(SLUG));

                    const bar = document.getElementById(itemId + '-bar');
                    const status = document.getElementById(itemId + '-status');

                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            const pct = Math.round(e.loaded / e.total * 100);
                            if (bar) bar.style.width = pct + '%';
                            if (status) status.textContent = pct + '%';
                        }
                    };

                    xhr.onload = function() {
                        if (bar) bar.style.width = '100%';
                        if (status) status.textContent = '100%';
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.success && data.image) {
                                images.push({
                                    filename: data.image.filename || data.image.name || file.name,
                                    title: data.image.title || '',
                                    description: data.image.description || '',
                                    order: images.length
                                });
                                if (status) status.innerHTML = '<span style="color:#22c55e;">&#10003;</span>';
                            } else {
                                if (status) status.innerHTML = '<span style="color:#ef4444;">&#10005;</span>';
                            }
                        } catch(e) {
                            if (status) status.innerHTML = '<span style="color:#ef4444;">&#10005;</span>';
                        }
                        resolve();
                    };

                    xhr.onerror = function() {
                        if (status) status.innerHTML = '<span style="color:#ef4444;">&#10005;</span>';
                        resolve();
                    };

                    xhr.send(fd);
                });
            }

            // Clear queue after a moment and render
            setTimeout(() => { uploadQueue.innerHTML = ''; }, 1500);
            renderImages();
        }
    }

    // ─── Add Tag from hints ───
    window.addTag = function(tag) {
        const input = document.getElementById('gal-tags');
        const current = input.value.split(',').map(t => t.trim()).filter(Boolean);
        if (!current.includes(tag)) {
            current.push(tag);
            input.value = current.join(', ');
        }
    };

    // ─── Copy Shortcode ───
    window.copyShortcode = function() {
        const input = document.getElementById('shortcode-input');
        if (!input) return;
        navigator.clipboard.writeText(input.value).then(() => {
            const btn = document.getElementById('copy-btn');
            const orig = btn.textContent;
            btn.textContent = 'Copiato!';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        });
    };

    // ─── Initial render ───
    renderImages();
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
