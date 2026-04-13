<?php
$isNew = ($article === null);
$pageTitle = $isNew ? 'Nuovo Articolo' : 'Modifica Articolo';
$activeMenu = 'articles';

// AI provider info
$_aiProvider = $config['ai']['provider'] ?? 'none';
$_aiHasKey = !empty($config['ai']['api_key'] ?? '');
$_aiCliAvailable = !empty($config['ai_cli_script']) && file_exists($config['ai_cli_script']);
$_aiEnabled = $_aiCliAvailable || ($_aiProvider !== 'none' && $_aiHasKey);
$_aiLabels = ['anthropic'=>'Claude','openai'=>'GPT','google'=>'Gemini','mistral'=>'Mistral','groq'=>'Groq'];
$_aiName = $_aiCliAvailable ? 'Claude' : ($_aiLabels[$_aiProvider] ?? 'AI');

$article = $article ?? [
    'id' => '', 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '',
    'cover_image' => '', 'gallery' => [], 'category' => '', 'tags' => [], 'status' => 'draft',
    'featured' => false, 'publish_at' => '',
    'meta' => ['title' => '', 'description' => '', 'og_image' => ''],
    'author' => '', 'created_at' => '', 'updated_at' => '',
];

$gallery = $article['gallery'] ?? [];
$publishAt = $article['publish_at'] ?? '';
// Converti ISO a formato datetime-local
if ($publishAt) {
    $dt = new DateTime($publishAt);
    $publishAtLocal = $dt->format('Y-m-d\TH:i');
} else {
    $publishAtLocal = '';
}

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jodit/4.6.13/es2015/jodit.min.css">
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<form method="POST" action="<?= ocms_base_url() ?>/admin/articles/save" id="article-form" enctype="multipart/form-data">
    <?= ocms_csrf_field() ?>
    <input type="hidden" name="id" value="<?= ocms_escape($article['id']) ?>">
    <input type="hidden" name="original_slug" value="<?= ocms_escape($article['slug']) ?>">
    <input type="hidden" name="created_at" value="<?= ocms_escape($article['created_at']) ?>">
    <input type="hidden" name="gallery" id="gallery-data" value="<?= ocms_escape(json_encode($gallery)) ?>">

    <div class="page-header">
        <h1><?= $isNew ? 'Nuovo Articolo' : 'Modifica Articolo' ?></h1>
        <div style="display:flex;gap:10px;align-items:center;">
            <?php if (!$isNew): ?>
            <a href="<?= ocms_base_url() ?>/admin/articles/revisions/<?= ocms_escape($article['slug']) ?>" class="btn btn-secondary btn-sm" title="Revisioni">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Revisioni
            </a>
            <?php endif; ?>
            <a href="<?= ocms_base_url() ?>/admin/articles" class="btn btn-secondary">Annulla</a>
            <button type="submit" name="status" value="draft" class="btn btn-secondary">Salva Bozza</button>
            <button type="submit" name="status" value="published" class="btn btn-primary">Pubblica</button>
        </div>
    </div>

    <div class="editor-layout">
        <div class="editor-main">
            <!-- Titolo -->
            <div class="card" style="margin-bottom:20px;">
                <input type="text" name="title" class="form-input" placeholder="Titolo dell'articolo"
                       value="<?= ocms_escape($article['title']) ?>" id="art-title" required
                       style="font-size:1.4rem;font-weight:700;padding:16px;background:transparent;border:none;">
            </div>

            <!-- Excerpt -->
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Estratto</label>
                    <textarea name="excerpt" class="form-textarea" rows="2" placeholder="Breve descrizione per le anteprime..."
                              style="min-height:50px;"><?= ocms_escape($article['excerpt']) ?></textarea>
                </div>
            </div>

            <!-- Editor -->
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <label style="margin-bottom:0;">Contenuto</label>
                        <?php if ($_aiEnabled): ?>
                        <button type="button" id="ai-btn" onclick="openAiModal()"
                                style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;border:none;border-radius:8px;font-size:0.8rem;font-weight:600;cursor:pointer;font-family:inherit;transition:transform .15s,box-shadow .15s;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a4 4 0 0 1 4 4c0 1.95-1.4 3.58-3.25 3.93L12 22"/><path d="M8 6a4 4 0 0 1 8 0"/><path d="M17 12.5c1.77.77 3 2.53 3 4.5a5 5 0 0 1-10 0"/><path d="M4 17a5 5 0 0 1 3-4.5"/></svg>
                            Chiedi a <?= $_aiName ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php
                    // Prependi base URL ai path delle immagini per visualizzarle nell'editor
                    $editorContent = $article['content'];
                    $bu = ocms_base_url();
                    if ($bu !== '' && $bu !== '/') {
                        $editorContent = str_replace('src="/uploads/', 'src="' . $bu . '/uploads/', $editorContent);
                        $editorContent = str_replace('src="/data/', 'src="' . $bu . '/data/', $editorContent);
                    }
                    ?>
                    <textarea name="content" id="editor"><?= ocms_escape($editorContent) ?></textarea>
                </div>
            </div>

            <!-- Modale AI -->
            <div id="ai-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
                <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:28px;width:90%;max-width:580px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                        <h3 style="font-size:1.1rem;font-weight:700;display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M12 2a4 4 0 0 1 4 4c0 1.95-1.4 3.58-3.25 3.93L12 22"/><path d="M8 6a4 4 0 0 1 8 0"/><path d="M17 12.5c1.77.77 3 2.53 3 4.5a5 5 0 0 1-10 0"/><path d="M4 17a5 5 0 0 1 3-4.5"/></svg>
                            Chiedi a <?= $_aiName ?>
                        </h3>
                        <button type="button" onclick="closeAiModal()" style="background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;">&times;</button>
                    </div>

                    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                        Descrivi l'articolo che vuoi generare. <?= $_aiName ?> scriverà il contenuto in formato HTML pronto per l'editor.
                    </p>

                    <textarea id="ai-prompt" class="form-textarea" rows="4" placeholder="Es: Scrivi un articolo su come fare il pane in casa, con consigli per principianti e una sezione sugli errori comuni..."
                              style="min-height:100px;margin-bottom:16px;"></textarea>

                    <div style="display:flex;align-items:center;gap:8px;">
                        <select id="ai-mode" class="form-select" style="width:auto;padding:8px 36px 8px 12px;font-size:0.85rem;">
                            <option value="write">Scrivi articolo completo</option>
                            <option value="expand">Espandi/migliora il contenuto attuale</option>
                            <option value="rewrite">Riscrivi il contenuto attuale</option>
                        </select>
                        <span style="flex:1;"></span>
                        <button type="button" onclick="closeAiModal()" class="btn btn-secondary btn-sm">Annulla</button>
                        <button type="button" id="ai-submit" onclick="generateAi()" class="btn btn-sm" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;border:none;">
                            Genera
                        </button>
                    </div>

                    <div id="ai-loading" style="display:none;margin-top:16px;text-align:center;padding:20px;">
                        <div style="display:inline-block;width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--primary-light);border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                        <p style="color:var(--text-muted);font-size:0.85rem;margin-top:10px;"><?= $_aiName ?> sta scrivendo...</p>
                    </div>
                    <div id="ai-error" style="display:none;margin-top:12px;color:#ef4444;font-size:0.85rem;"></div>

                    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
                </div>
            </div>

            <!-- Galleria Immagini -->
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
                    Galleria Immagini
                    <span id="gallery-count" style="font-weight:400;font-size:0.8rem;color:var(--text-muted);margin-left:8px;">(<?= count($gallery) ?> foto)</span>
                </h3>

                <div id="gallery-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-bottom:16px;">
                    <?php foreach ($gallery as $i => $img): ?>
                    <div class="gallery-item" data-index="<?= $i ?>" style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:var(--bg);border:1px solid var(--border);">
                        <img src="<?= ocms_base_url() . ocms_escape($img['thumb'] ?? $img['url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        <button type="button" class="gallery-remove" onclick="removeGalleryImage(<?= $i ?>)"
                                style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;border:none;background:rgba(0,0,0,0.7);color:#fff;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="gallery-dropzone" style="border:2px dashed var(--border);border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;">
                    <div style="color:var(--text-muted);font-size:0.85rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 6px;opacity:.5;">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                        </svg>
                        Trascina immagini o <span style="color:var(--primary-light);text-decoration:underline;">sfoglia</span>
                        <div style="font-size:0.7rem;margin-top:4px;opacity:.6;">Upload multiplo supportato</div>
                    </div>
                    <input type="file" id="gallery-file" accept="image/*" multiple style="display:none;">
                </div>
                <div id="gallery-progress" style="display:none;margin-top:8px;">
                    <div style="background:var(--bg-light);border-radius:4px;overflow:hidden;height:4px;">
                        <div id="gallery-progress-bar" style="height:100%;background:var(--primary);width:0%;transition:width .3s;"></div>
                    </div>
                    <div id="gallery-progress-text" style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;"></div>
                </div>
            </div>

            <!-- SEO -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">SEO</h3>
                <div class="form-group">
                    <label>Meta Title</label>
                    <input type="text" name="meta_title" id="meta_title" class="form-input"
                           placeholder="Titolo per i motori di ricerca"
                           value="<?= ocms_escape($article['meta']['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Meta Description</label>
                    <textarea name="meta_description" id="meta_description" class="form-textarea" rows="2"
                              style="min-height:60px;" placeholder="Descrizione per i motori di ricerca"><?= ocms_escape($article['meta']['description'] ?? '') ?></textarea>
                </div>
                <input type="hidden" name="og_image" value="<?= ocms_escape($article['meta']['og_image'] ?? '') ?>">
            </div>
        </div>

        <div class="editor-sidebar">
            <!-- Stato -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Pubblicazione</h3>
                <span class="badge <?= $article['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
                    <?= $article['status'] === 'published' ? 'Pubblicato' : 'Bozza' ?>
                </span>
                <?php if ($article['updated_at']): ?>
                <div style="margin-top:8px;font-size:0.8rem;color:var(--text-muted);">
                    Aggiornato: <?= ocms_format_date($article['updated_at']) ?>
                </div>
                <?php endif; ?>

                <!-- Pubblicazione programmata -->
                <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                    <label>Pubblica il</label>
                    <input type="datetime-local" name="publish_at" class="form-input" value="<?= ocms_escape($publishAtLocal) ?>"
                           style="font-size:0.85rem;">
                    <div class="form-hint">Lascia vuoto per pubblicazione immediata</div>
                </div>

                <!-- In evidenza -->
                <div style="margin-top:16px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.85rem;color:var(--text);margin-bottom:0;text-transform:none;letter-spacing:0;">
                        <input type="checkbox" name="featured" value="1" <?= !empty($article['featured']) ? 'checked' : '' ?>
                               style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="<?= !empty($article['featured']) ? '#f59e0b' : 'none' ?>" stroke="#f59e0b" stroke-width="2" style="vertical-align:-2px;margin-right:2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            In evidenza
                        </span>
                    </label>
                    <div class="form-hint">Mostra in homepage</div>
                </div>
            </div>

            <!-- Slug -->
            <div class="card">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Slug</label>
                    <input type="text" name="slug" id="art-slug" class="form-input"
                           placeholder="auto-generato" value="<?= ocms_escape($article['slug']) ?>">
                </div>
            </div>

            <!-- Cover Image -->
            <div class="card">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Immagine di copertina</label>
                    <input type="hidden" name="cover_image" id="cover-input" value="<?= ocms_escape($article['cover_image']) ?>">

                    <?php $hasCover = !empty($article['cover_image']); ?>
                    <div id="cover-preview" style="<?= $hasCover ? '' : 'display:none;' ?>margin-bottom:10px;position:relative;">
                        <img id="cover-img" src="<?= $hasCover ? ocms_base_url() . ocms_escape($article['cover_image']) : '' ?>"
                             alt="Cover" style="width:100%;border-radius:8px;aspect-ratio:1200/630;object-fit:cover;">
                        <button type="button" id="cover-remove" title="Rimuovi"
                                style="position:absolute;top:6px;right:6px;width:28px;height:28px;border-radius:50%;border:none;
                                       background:rgba(0,0,0,0.7);color:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">
                            &times;
                        </button>
                    </div>

                    <div id="cover-dropzone" style="<?= $hasCover ? 'display:none;' : '' ?>border:2px dashed var(--border);border-radius:8px;padding:24px;
                                text-align:center;cursor:pointer;transition:border-color .2s,background .2s;">
                        <div style="color:var(--text-muted);font-size:0.85rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.5;">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                            </svg>
                            Trascina o <span style="color:var(--primary-light);text-decoration:underline;">sfoglia</span>
                            <div style="font-size:0.75rem;margin-top:4px;opacity:.6;">1200&times;630 automatico</div>
                        </div>
                        <input type="file" id="cover-file" accept="image/jpeg,image/png,image/gif,image/webp"
                               style="display:none;">
                    </div>

                    <div id="cover-progress" style="display:none;margin-top:8px;">
                        <div style="background:var(--bg-light);border-radius:4px;overflow:hidden;height:4px;">
                            <div id="cover-progress-bar" style="height:100%;background:var(--primary);width:0%;transition:width .3s;"></div>
                        </div>
                    </div>
                    <div id="cover-error" style="display:none;color:#ef4444;font-size:0.8rem;margin-top:6px;"></div>
                </div>
            </div>

            <!-- Categoria -->
            <div class="card">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Categoria</label>
                    <select name="category" class="form-select">
                        <option value="">— Nessuna —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= ocms_escape($cat['slug']) ?>"
                                    <?= ($article['category'] ?? '') === $cat['slug'] ? 'selected' : '' ?>>
                                <?= ocms_escape($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Tags -->
            <div class="card">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Tag</label>
                    <input type="text" name="tags" class="form-input"
                           placeholder="tag1, tag2, tag3"
                           value="<?= ocms_escape(implode(', ', $article['tags'] ?? [])) ?>">
                    <div class="form-hint">Separati da virgola, creati automaticamente</div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php $content = ob_get_clean(); ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jodit/4.6.13/es2015/jodit.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Jodit.make('#editor', {
        height: 500, theme: 'dark', toolbarSticky: true,
        placeholder: 'Scrivi il contenuto dell\'articolo...',
        buttons: ['bold','italic','underline','strikethrough','|','ul','ol','|','font','fontsize','paragraph','|','brush','|','image','video','table','link','|','align','|','undo','redo','|','hr','eraser','copyformat','|','fullsize','source'],
        style: { background: '#0f172a', color: '#f1f5f9' },
        editorStyle: { background: '#0f172a', color: '#f1f5f9', 'font-family': "'Inter', sans-serif", 'font-size': '15px', 'line-height': '1.7', padding: '20px' },
        uploader: { insertImageAsBase64URI: true },
        showCharsCounter: false, showWordsCounter: false, showXPathInStatusbar: false,
        askBeforePasteHTML: false, askBeforePasteFromWord: false,
    });

    const title = document.getElementById('art-title');
    const slug = document.getElementById('art-slug');
    if (!slug.value) {
        title.addEventListener('input', function() {
            slug.value = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');
        });
    }

    const csrfToken = document.querySelector('input[name="_csrf_token"]').value;
    const baseUrl = '<?= ocms_base_url() ?>';

    // ─── Cover Image Upload ───
    const dropzone = document.getElementById('cover-dropzone');
    const fileInput = document.getElementById('cover-file');
    const coverInput = document.getElementById('cover-input');
    const preview = document.getElementById('cover-preview');
    const coverImg = document.getElementById('cover-img');
    const removeBtn = document.getElementById('cover-remove');
    const progress = document.getElementById('cover-progress');
    const progressBar = document.getElementById('cover-progress-bar');
    const errorDiv = document.getElementById('cover-error');

    function showCover(url) {
        coverImg.src = baseUrl + url;
        coverInput.value = url;
        preview.style.display = '';
        dropzone.style.display = 'none';
        errorDiv.style.display = 'none';
    }
    function removeCover() {
        coverImg.src = '';
        coverInput.value = '';
        preview.style.display = 'none';
        dropzone.style.display = '';
    }
    function uploadCover(file) {
        if (!file.type.startsWith('image/')) { errorDiv.textContent = 'Seleziona un file immagine'; errorDiv.style.display = ''; return; }
        if (file.size > 10 * 1024 * 1024) { errorDiv.textContent = 'File troppo grande (max 10MB)'; errorDiv.style.display = ''; return; }
        const fd = new FormData();
        fd.append('cover', file);
        fd.append('_csrf_token', csrfToken);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', baseUrl + '/admin/articles/upload-cover');
        progress.style.display = ''; progressBar.style.width = '0%'; errorDiv.style.display = 'none';
        xhr.upload.onprogress = e => { if (e.lengthComputable) progressBar.style.width = Math.round(e.loaded/e.total*100)+'%'; };
        xhr.onload = () => {
            progress.style.display = 'none';
            try { const r = JSON.parse(xhr.responseText); r.success ? showCover(r.url) : (errorDiv.textContent = r.error, errorDiv.style.display = ''); }
            catch(e) { errorDiv.textContent = 'Errore risposta server'; errorDiv.style.display = ''; }
        };
        xhr.onerror = () => { progress.style.display = 'none'; errorDiv.textContent = 'Errore di rete'; errorDiv.style.display = ''; };
        xhr.send(fd);
    }
    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) uploadCover(fileInput.files[0]); });
    removeBtn.addEventListener('click', removeCover);
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.style.borderColor='var(--primary)'; dropzone.style.background='rgba(99,102,241,0.05)'; });
    dropzone.addEventListener('dragleave', () => { dropzone.style.borderColor='var(--border)'; dropzone.style.background=''; });
    dropzone.addEventListener('drop', e => { e.preventDefault(); dropzone.style.borderColor='var(--border)'; dropzone.style.background=''; if (e.dataTransfer.files[0]) uploadCover(e.dataTransfer.files[0]); });

    // ─── Gallery Upload ───
    let galleryImages = <?= json_encode($gallery) ?>;
    const galleryData = document.getElementById('gallery-data');
    const galleryGrid = document.getElementById('gallery-grid');
    const galleryDropzone = document.getElementById('gallery-dropzone');
    const galleryFile = document.getElementById('gallery-file');
    const galleryProgress = document.getElementById('gallery-progress');
    const galleryProgressBar = document.getElementById('gallery-progress-bar');
    const galleryProgressText = document.getElementById('gallery-progress-text');
    const galleryCount = document.getElementById('gallery-count');

    function updateGalleryData() {
        galleryData.value = JSON.stringify(galleryImages);
        galleryCount.textContent = '(' + galleryImages.length + ' foto)';
    }

    function renderGallery() {
        galleryGrid.innerHTML = galleryImages.map((img, i) =>
            '<div class="gallery-item" data-index="'+i+'" style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:4/3;background:var(--bg);border:1px solid var(--border);">' +
            '<img src="'+baseUrl+(img.thumb||img.url)+'" alt="" style="width:100%;height:100%;object-fit:cover;">' +
            '<button type="button" onclick="removeGalleryImage('+i+')" style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;border:none;background:rgba(0,0,0,0.7);color:#fff;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;">&times;</button>' +
            '</div>'
        ).join('');
        updateGalleryData();
    }

    window.removeGalleryImage = function(index) {
        galleryImages.splice(index, 1);
        renderGallery();
    };

    async function uploadGalleryFiles(files) {
        const imageFiles = Array.from(files).filter(f => f.type.startsWith('image/'));
        if (!imageFiles.length) return;

        galleryProgress.style.display = '';
        for (let i = 0; i < imageFiles.length; i++) {
            galleryProgressText.textContent = 'Upload ' + (i+1) + '/' + imageFiles.length + '...';
            galleryProgressBar.style.width = Math.round(i/imageFiles.length*100) + '%';

            const fd = new FormData();
            fd.append('gallery_image', imageFiles[i]);
            fd.append('_csrf_token', csrfToken);

            try {
                const res = await fetch(baseUrl + '/admin/articles/upload-gallery', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    galleryImages.push({ url: data.url, thumb: data.thumb, name: data.name });
                }
            } catch(e) { /* skip errori singoli */ }
        }
        galleryProgressBar.style.width = '100%';
        setTimeout(() => { galleryProgress.style.display = 'none'; }, 500);
        renderGallery();
    }

    galleryDropzone.addEventListener('click', () => galleryFile.click());
    galleryFile.addEventListener('change', () => { if (galleryFile.files.length) uploadGalleryFiles(galleryFile.files); galleryFile.value = ''; });
    galleryDropzone.addEventListener('dragover', e => { e.preventDefault(); galleryDropzone.style.borderColor='var(--primary)'; galleryDropzone.style.background='rgba(99,102,241,0.05)'; });
    galleryDropzone.addEventListener('dragleave', () => { galleryDropzone.style.borderColor='var(--border)'; galleryDropzone.style.background=''; });
    galleryDropzone.addEventListener('drop', e => { e.preventDefault(); galleryDropzone.style.borderColor='var(--border)'; galleryDropzone.style.background=''; if (e.dataTransfer.files.length) uploadGalleryFiles(e.dataTransfer.files); });
});

// ─── AI Generate ───
function openAiModal() {
    const modal = document.getElementById('ai-modal');
    modal.style.display = 'flex';
    document.getElementById('ai-prompt').focus();
    document.getElementById('ai-error').style.display = 'none';
    document.getElementById('ai-loading').style.display = 'none';
}

function closeAiModal() {
    document.getElementById('ai-modal').style.display = 'none';
}

async function generateAi() {
    const prompt = document.getElementById('ai-prompt').value.trim();
    if (!prompt) { document.getElementById('ai-prompt').focus(); return; }

    const mode = document.getElementById('ai-mode').value;
    const csrfToken = document.querySelector('input[name="_csrf_token"]').value;
    const baseUrl = '<?= ocms_base_url() ?>';
    const loading = document.getElementById('ai-loading');
    const errorDiv = document.getElementById('ai-error');
    const submitBtn = document.getElementById('ai-submit');

    // Costruisci prompt in base alla modalità
    let fullPrompt = prompt;
    const jodit = Jodit.instances[Object.keys(Jodit.instances)[0]];
    const currentContent = jodit ? jodit.value : '';

    if (mode === 'expand' && currentContent) {
        fullPrompt = 'Espandi e migliora il seguente contenuto mantenendo la struttura. Aggiungi dettagli, esempi e approfondimenti. Contenuto attuale:\n\n' + currentContent.replace(/<[^>]+>/g, ' ').substring(0, 2000) + '\n\nIstruzioni aggiuntive: ' + prompt;
    } else if (mode === 'rewrite' && currentContent) {
        fullPrompt = 'Riscrivi completamente il seguente contenuto migliorandolo. Mantieni lo stesso argomento ma rendi il testo più professionale e coinvolgente. Contenuto attuale:\n\n' + currentContent.replace(/<[^>]+>/g, ' ').substring(0, 2000) + '\n\nIstruzioni aggiuntive: ' + prompt;
    }

    loading.style.display = 'block';
    errorDiv.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.style.opacity = '0.5';

    try {
        const res = await fetch(baseUrl + '/admin/articles/ai-generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: fullPrompt, _csrf_token: csrfToken })
        });

        const data = await res.json();

        if (data.success && data.content) {
            // Inserisci nell'editor
            if (jodit) {
                if (mode === 'write' || mode === 'rewrite') {
                    jodit.value = data.content;
                } else {
                    jodit.value = jodit.value + '\n' + data.content;
                }
            }
            closeAiModal();
            document.getElementById('ai-prompt').value = '';
        } else {
            errorDiv.textContent = data.error || 'Errore nella generazione';
            errorDiv.style.display = 'block';
        }
    } catch (e) {
        errorDiv.textContent = 'Errore di connessione';
        errorDiv.style.display = 'block';
    }

    loading.style.display = 'none';
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
}

// Chiudi modale con Esc
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('ai-modal').style.display === 'flex') closeAiModal();
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
