<?php
$isNew = ($page === null);
$pageTitle = $isNew ? 'Nuova Pagina' : 'Modifica Pagina';
$activeMenu = 'pages';

$_aiProvider = $config['ai']['provider'] ?? 'none';
$_aiHasKey = !empty($config['ai']['api_key'] ?? '');
$_aiCliAvailable = !empty($config['ai_cli_script']) && file_exists($config['ai_cli_script']);
$_aiEnabled = $_aiCliAvailable || ($_aiProvider !== 'none' && $_aiHasKey);
$_aiLabels = ['anthropic'=>'Claude','openai'=>'GPT','google'=>'Gemini','mistral'=>'Mistral','groq'=>'Groq'];
$_aiName = $_aiCliAvailable ? 'Claude' : ($_aiLabels[$_aiProvider] ?? 'AI');

// Defaults per nuova pagina
$page = $page ?? [
    'id' => '',
    'title' => '',
    'slug' => '',
    'content' => '',
    'template' => 'page',
    'layout' => 'none',
    'status' => 'draft',
    'meta' => ['title' => '', 'description' => '', 'og_image' => ''],
    'order' => 0,
    'parent' => '',
    'author' => '',
    'created_at' => '',
    'updated_at' => '',
];
$layouts = $layouts ?? [];

ob_start();
?>

<!-- Jodit Editor CSS + JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jodit/4.6.13/es2015/jodit.min.css">

<?php $headExtra = ob_get_clean(); ob_start(); ?>

<form method="POST" action="<?= ocms_base_url() ?>/admin/pages/save" id="page-form">
    <?= ocms_csrf_field() ?>
    <input type="hidden" name="id" value="<?= ocms_escape($page['id']) ?>">
    <input type="hidden" name="original_slug" value="<?= ocms_escape($page['slug']) ?>">
    <input type="hidden" name="created_at" value="<?= ocms_escape($page['created_at']) ?>">

    <div class="page-header">
        <h1><?= $isNew ? 'Nuova Pagina' : 'Modifica Pagina' ?></h1>
        <div style="display:flex;gap:10px;">
            <a href="<?= ocms_base_url() ?>/admin/pages" class="btn btn-secondary">Annulla</a>
            <button type="submit" name="status" value="draft" class="btn btn-secondary">Salva Bozza</button>
            <button type="submit" name="status" value="published" class="btn btn-primary">Pubblica</button>
        </div>
    </div>

    <div class="editor-layout">
        <!-- COLONNA PRINCIPALE -->
        <div class="editor-main">
            <!-- Titolo -->
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <input type="text" name="title" class="form-input" placeholder="Titolo della pagina"
                           value="<?= ocms_escape($page['title']) ?>"
                           style="font-size:1.4rem;font-weight:700;padding:16px;background:transparent;border:none;"
                           id="page-title" required>
                </div>
            </div>

            <!-- Editor WYSIWYG -->
            <div class="card">
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
                    $editorContent = $page['content'];
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
                        Descrivi il contenuto che vuoi generare. <?= $_aiName ?> scriverà in formato HTML pronto per l'editor.
                    </p>
                    <textarea id="ai-prompt" class="form-textarea" rows="4" placeholder="Es: Scrivi una pagina chi siamo per un'azienda di design, con mission e valori..."
                              style="min-height:100px;margin-bottom:16px;"></textarea>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <select id="ai-mode" class="form-select" style="width:auto;padding:8px 36px 8px 12px;font-size:0.85rem;">
                            <option value="write">Scrivi contenuto completo</option>
                            <option value="expand">Espandi/migliora il contenuto attuale</option>
                            <option value="rewrite">Riscrivi il contenuto attuale</option>
                        </select>
                        <span style="flex:1;"></span>
                        <button type="button" onclick="closeAiModal()" class="btn btn-secondary btn-sm">Annulla</button>
                        <button type="button" id="ai-submit" onclick="generateAi()" class="btn btn-sm" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;border:none;">Genera</button>
                    </div>
                    <div id="ai-loading" style="display:none;margin-top:16px;text-align:center;padding:20px;">
                        <div style="display:inline-block;width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--primary-light);border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                        <p style="color:var(--text-muted);font-size:0.85rem;margin-top:10px;"><?= $_aiName ?> sta scrivendo...</p>
                    </div>
                    <div id="ai-error" style="display:none;margin-top:12px;color:#ef4444;font-size:0.85rem;"></div>
                    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
                </div>
            </div>

            <!-- SEO -->
            <div class="card" style="margin-top:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">SEO</h3>

                <div class="form-group">
                    <label for="meta_title">Meta Title</label>
                    <input type="text" name="meta_title" id="meta_title" class="form-input"
                           placeholder="Titolo per i motori di ricerca"
                           value="<?= ocms_escape($page['meta']['title'] ?? '') ?>">
                    <div class="form-hint">Lascia vuoto per usare il titolo della pagina</div>
                </div>

                <div class="form-group">
                    <label for="meta_description">Meta Description</label>
                    <textarea name="meta_description" id="meta_description" class="form-textarea"
                              placeholder="Descrizione per i motori di ricerca" rows="2"
                              style="min-height:60px;"><?= ocms_escape($page['meta']['description'] ?? '') ?></textarea>
                </div>

                <input type="hidden" name="og_image" value="<?= ocms_escape($page['meta']['og_image'] ?? '') ?>">

                <!-- Anteprima SEO -->
                <div class="seo-preview">
                    <div class="seo-title" id="seo-preview-title"><?= ocms_escape($page['meta']['title'] ?: $page['title'] ?: 'Titolo pagina') ?></div>
                    <div class="seo-url"><?= ocms_escape(ocms_base_url()) ?>/<span id="seo-preview-slug"><?= ocms_escape($page['slug'] ?: 'slug-pagina') ?></span></div>
                    <div class="seo-desc" id="seo-preview-desc"><?= ocms_escape($page['meta']['description'] ?: 'Descrizione della pagina...') ?></div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR DESTRA -->
        <div class="editor-sidebar">
            <!-- Pubblicazione -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Pubblicazione</h3>

                <div class="form-group">
                    <label>Stato attuale</label>
                    <span class="badge <?= $page['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
                        <?= $page['status'] === 'published' ? 'Pubblicata' : 'Bozza' ?>
                    </span>
                </div>

                <?php if ($page['updated_at']): ?>
                <div class="form-group">
                    <label>Ultimo aggiornamento</label>
                    <span style="font-size:0.85rem;color:var(--text-muted);">
                        <?= ocms_format_date($page['updated_at']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!$isNew && $page['slug']): ?>
                <a href="<?= ocms_base_url() ?>/<?= ocms_escape($page['slug']) ?>" target="_blank"
                   class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;">
                    Vedi pagina
                </a>
                <?php endif; ?>
            </div>

            <!-- Slug -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">URL</h3>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="page-slug">Slug</label>
                    <input type="text" name="slug" id="page-slug" class="form-input"
                           placeholder="viene-generato-dal-titolo"
                           value="<?= ocms_escape($page['slug']) ?>">
                    <div class="form-hint">Lascia vuoto per generare automaticamente</div>
                </div>
            </div>

            <!-- Layout -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Layout</h3>
                <div class="form-group">
                    <label>Layout Builder</label>
                    <select name="layout" class="form-select">
                        <option value="none" <?= ($page['layout'] ?? 'none') === 'none' ? 'selected' : '' ?>>
                            Nessuno (usa template PHP)
                        </option>
                        <?php foreach ($layouts as $l): ?>
                        <option value="<?= ocms_escape($l['id']) ?>" <?= ($page['layout'] ?? '') === $l['id'] ? 'selected' : '' ?>>
                            <?= ocms_escape($l['name']) ?>
                            <?= $l['id'] === 'base' ? ' (ereditato)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">
                        Se scegli un layout, la pagina userà il Layout Builder.<br>
                        "Base" = header/footer globali + il tuo contenuto.<br>
                        <a href="<?= ocms_base_url() ?>/admin/layouts" style="font-size:0.75rem;">Gestisci Layout →</a>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Template PHP (fallback)</label>
                    <select name="template" class="form-select">
                        <option value="page" <?= $page['template'] === 'page' ? 'selected' : '' ?>>Pagina Standard</option>
                        <option value="home" <?= $page['template'] === 'home' ? 'selected' : '' ?>>Homepage</option>
                        <option value="full-width" <?= ($page['template'] ?? '') === 'full-width' ? 'selected' : '' ?>>Larghezza Piena</option>
                    </select>
                    <div class="form-hint">Usato solo se Layout è "Nessuno"</div>
                </div>
            </div>

            <!-- Ordine -->
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Ordinamento</h3>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="page-order">Ordine</label>
                    <input type="number" name="order" id="page-order" class="form-input"
                           value="<?= (int)$page['order'] ?>" min="0">
                </div>
                <div class="form-group" style="margin-top:12px;margin-bottom:0;">
                    <label for="page-parent">Pagina Padre</label>
                    <input type="text" name="parent" id="page-parent" class="form-input"
                           placeholder="slug-pagina-padre"
                           value="<?= ocms_escape($page['parent'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
</form>

<?php $content = ob_get_clean(); ob_start(); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jodit/4.6.13/es2015/jodit.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ─── Jodit Editor ───
    const editor = Jodit.make('#editor', {
        height: 500,
        theme: 'dark',
        toolbarSticky: true,
        toolbarStickyOffset: 0,
        placeholder: 'Inizia a scrivere il contenuto della pagina...',
        buttons: [
            'bold', 'italic', 'underline', 'strikethrough', '|',
            'ul', 'ol', '|',
            'font', 'fontsize', 'paragraph', '|',
            'brush', '|',
            'image', 'video', 'table', 'link', '|',
            'align', '|',
            'undo', 'redo', '|',
            'hr', 'eraser', 'copyformat', '|',
            'fullsize', 'source'
        ],
        buttonsXS: [
            'bold', 'italic', '|', 'ul', 'ol', '|',
            'image', 'link', '|', 'source'
        ],
        style: {
            background: '#0f172a',
            color: '#f1f5f9',
        },
        editorStyle: {
            background: '#0f172a',
            color: '#f1f5f9',
            'font-family': "'Inter', sans-serif",
            'font-size': '15px',
            'line-height': '1.7',
            padding: '20px',
        },
        uploader: {
            insertImageAsBase64URI: true
        },
        showCharsCounter: false,
        showWordsCounter: false,
        showXPathInStatusbar: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
    });

    // ─── Auto-slug dal titolo ───
    const titleInput = document.getElementById('page-title');
    const slugInput = document.getElementById('page-slug');
    const isNew = !slugInput.value;

    if (isNew) {
        titleInput.addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '');
            slugInput.value = slug;
            updateSeoPreview();
        });
    }

    // ─── SEO Live Preview ───
    const metaTitle = document.getElementById('meta_title');
    const metaDesc = document.getElementById('meta_description');

    function updateSeoPreview() {
        document.getElementById('seo-preview-title').textContent =
            metaTitle.value || titleInput.value || 'Titolo pagina';
        document.getElementById('seo-preview-slug').textContent =
            slugInput.value || 'slug-pagina';
        document.getElementById('seo-preview-desc').textContent =
            metaDesc.value || 'Descrizione della pagina...';
    }

    titleInput.addEventListener('input', updateSeoPreview);
    metaTitle.addEventListener('input', updateSeoPreview);
    metaDesc.addEventListener('input', updateSeoPreview);
    slugInput.addEventListener('input', updateSeoPreview);
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
    const jodit = Jodit.instances[Object.keys(Jodit.instances)[0]];
    const currentContent = jodit ? jodit.value : '';
    let fullPrompt = prompt;
    if (mode === 'expand' && currentContent) {
        fullPrompt = 'Espandi e migliora il seguente contenuto. Aggiungi dettagli e approfondimenti. Contenuto attuale:\n\n' + currentContent.replace(/<[^>]+>/g, ' ').substring(0, 2000) + '\n\nIstruzioni: ' + prompt;
    } else if (mode === 'rewrite' && currentContent) {
        fullPrompt = 'Riscrivi completamente il seguente contenuto rendendolo più professionale. Contenuto attuale:\n\n' + currentContent.replace(/<[^>]+>/g, ' ').substring(0, 2000) + '\n\nIstruzioni: ' + prompt;
    }
    loading.style.display = 'block'; errorDiv.style.display = 'none';
    submitBtn.disabled = true; submitBtn.style.opacity = '0.5';
    try {
        const res = await fetch(baseUrl + '/admin/articles/ai-generate', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: fullPrompt, _csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success && data.content) {
            if (jodit) {
                if (mode === 'write' || mode === 'rewrite') { jodit.value = data.content; }
                else { jodit.value = jodit.value + '\n' + data.content; }
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
    loading.style.display = 'none'; submitBtn.disabled = false; submitBtn.style.opacity = '1';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('ai-modal').style.display === 'flex') closeAiModal();
});
</script>

<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
