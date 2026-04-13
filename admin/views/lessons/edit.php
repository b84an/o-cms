<?php
$isNew = ($lesson === null);
$pageTitle = $isNew ? 'Nuova Lezione' : 'Modifica Lezione';
$activeMenu = 'lessons';

$lesson = $lesson ?? [
    'id' => '', 'title' => '', 'slug' => '', 'description' => '',
    'tags' => [], 'main_file' => '', 'status' => 'draft',
    'author' => '', 'created_at' => '', 'updated_at' => '',
];

// Scansiona file nella cartella
$lessonFiles = [];
$dir = ocms_base_path() . '/uploads/lezioni/' . ($lesson['slug'] ?: '__nonexistent__');
if (!$isNew && is_dir($dir)) {
    $extMap = [
        'html' => ['html', 'htm'],
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'audio' => ['mp3', 'm4a', 'ogg', 'wav'],
        'video' => ['mp4', 'webm', 'ogv'],
        'pdf' => ['pdf'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];
    $catIcons = ['html'=>'📄','image'=>'🖼️','audio'=>'🎵','video'=>'🎬','pdf'=>'📕','archive'=>'📦'];
    foreach (scandir($dir) as $item) {
        if ($item[0] === '.' || is_dir($dir . '/' . $item)) continue;
        if (in_array($item, ['info.txt', 'visite.txt']) || preg_match('/^index(_backup_\d+)?\.php$/', $item)) continue;
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        $cat = 'other';
        foreach ($extMap as $c => $exts) {
            if (in_array($ext, $exts)) { $cat = $c; break; }
        }
        $size = filesize($dir . '/' . $item);
        $lessonFiles[] = ['name' => $item, 'cat' => $cat, 'icon' => $catIcons[$cat] ?? '📎', 'size' => $size];
    }
    usort($lessonFiles, fn($a, $b) => strcasecmp($a['name'], $b['name']));
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

ob_start();
?>

<form method="POST" action="<?= ocms_base_url() ?>/admin/lessons/save" id="lesson-form" enctype="multipart/form-data">
    <?= ocms_csrf_field() ?>
    <input type="hidden" name="id" value="<?= ocms_escape($lesson['id']) ?>">
    <input type="hidden" name="original_slug" value="<?= ocms_escape($lesson['slug']) ?>">
    <input type="hidden" name="created_at" value="<?= ocms_escape($lesson['created_at']) ?>">

    <div class="page-header">
        <h1><?= $isNew ? 'Nuova Lezione' : 'Modifica Lezione' ?></h1>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= ocms_base_url() ?>/admin/lessons" class="btn btn-secondary">Annulla</a>
            <button type="submit" name="status" value="draft" class="btn btn-secondary">Salva Bozza</button>
            <button type="submit" name="status" value="published" class="btn btn-primary">Pubblica</button>
        </div>
    </div>

    <div class="editor-layout" style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
        <div>
            <!-- Titolo -->
            <div class="card" style="margin-bottom:20px;">
                <input type="text" name="title" class="form-input" placeholder="Titolo della lezione"
                       value="<?= ocms_escape($lesson['title']) ?>" required
                       style="font-size:1.4rem;font-weight:700;padding:16px;background:transparent;border:none;">
            </div>

            <!-- Descrizione -->
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Descrizione</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Breve descrizione della lezione..."
                              style="min-height:60px;"><?= ocms_escape($lesson['description']) ?></textarea>
                </div>
            </div>

            <!-- File della lezione -->
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <label style="font-weight:600;font-size:1rem;margin:0;">📁 File della lezione</label>
                    <?php if (!$isNew): ?>
                    <label class="btn btn-primary btn-sm" style="cursor:pointer;margin:0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Carica file
                        <input type="file" name="files[]" multiple id="file-upload" style="display:none;" onchange="document.getElementById('lesson-form').submit();">
                    </label>
                    <?php endif; ?>
                </div>

                <?php if ($isNew): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem;">Salva prima la lezione, poi potrai caricare i file.</p>
                <?php elseif (empty($lessonFiles)): ?>
                    <div style="text-align:center;padding:30px;color:var(--text-muted);">
                        <p style="font-size:2rem;margin-bottom:8px;">📭</p>
                        <p>Nessun file. Carica HTML, immagini, audio, video o PDF.</p>
                    </div>
                <?php else: ?>
                    <div id="file-list" style="display:flex;flex-direction:column;gap:6px;">
                    <?php foreach ($lessonFiles as $f): ?>
                        <div class="file-row" data-file="<?= ocms_escape($f['name']) ?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;<?= $f['name'] === $lesson['main_file'] ? 'border-color:var(--primary);background:rgba(99,102,241,0.08);' : '' ?>">
                            <span style="font-size:1.1rem;flex-shrink:0;"><?= $f['icon'] ?></span>
                            <span style="flex:1;font-size:0.88rem;font-weight:500;word-break:break-all;"><?= ocms_escape($f['name']) ?></span>
                            <span style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;flex-shrink:0;"><?= formatSize($f['size']) ?></span>
                            <?php if ($f['name'] === $lesson['main_file']): ?>
                                <span style="font-size:0.65rem;font-weight:700;color:var(--primary-light);background:rgba(99,102,241,0.15);padding:2px 8px;border-radius:10px;flex-shrink:0;">PRINCIPALE</span>
                            <?php endif; ?>
                            <button type="button" onclick="setMainFile('<?= ocms_escape($f['name']) ?>')" title="Imposta come principale" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;flex-shrink:0;">⭐</button>
                            <button type="button" onclick="deleteFile('<?= ocms_escape($f['name']) ?>')" title="Elimina file" style="background:none;border:none;cursor:pointer;color:var(--error);padding:4px;flex-shrink:0;">✕</button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div style="margin-top:12px;font-size:0.8rem;color:var(--text-muted);">
                        <?= count($lessonFiles) ?> file · Totale: <?= formatSize(array_sum(array_column($lessonFiles, 'size'))) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upload drag & drop -->
            <?php if (!$isNew): ?>
            <div class="card" id="drop-zone" style="margin-bottom:20px;border:2px dashed var(--border);text-align:center;padding:30px;cursor:pointer;transition:border-color .2s,background .2s;"
                 ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='rgba(99,102,241,0.05)';"
                 ondragleave="this.style.borderColor='var(--border)';this.style.background='';"
                 ondrop="event.preventDefault();this.style.borderColor='var(--border)';this.style.background='';handleDrop(event);">
                <p style="color:var(--text-muted);font-size:0.9rem;">Trascina qui i file per caricarli</p>
                <p style="color:var(--text-muted);font-size:0.75rem;margin-top:4px;">HTML, immagini, audio, video, PDF, archivi</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" class="form-input" value="<?= ocms_escape($lesson['slug']) ?>" placeholder="generato-dal-titolo">
                </div>
                <div class="form-group">
                    <label>Tag (separati da virgola)</label>
                    <input type="text" name="tags" class="form-input" value="<?= ocms_escape(implode(', ', $lesson['tags'])) ?>" placeholder="Java, TPSIT, Quarte">
                </div>
                <div class="form-group">
                    <label>File principale</label>
                    <input type="text" name="main_file" id="main-file-input" class="form-input" value="<?= ocms_escape($lesson['main_file']) ?>" placeholder="lezione.html">
                </div>
            </div>

            <?php if (!$isNew): ?>
            <div class="card">
                <div style="font-size:0.8rem;color:var(--text-muted);display:flex;flex-direction:column;gap:6px;">
                    <div>Creata: <?= ocms_format_date($lesson['created_at'], 'd/m/Y H:i') ?></div>
                    <div>Aggiornata: <?= ocms_format_date($lesson['updated_at'], 'd/m/Y H:i') ?></div>
                    <?php if (($lesson['views'] ?? 0) > 0): ?>
                    <div>Visite: <?= number_format($lesson['views'], 0, '', '.') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (!$isNew): ?>
<script>
const SLUG = '<?= ocms_escape($lesson['slug']) ?>';
const BASE = '<?= ocms_base_url() ?>';
const CSRF = '<?= $_SESSION['_csrf_token'] ?? '' ?>';

function setMainFile(name) {
    document.getElementById('main-file-input').value = name;
    document.getElementById('lesson-form').querySelector('[name="status"]') || null;
    // Submit con lo status attuale
    const form = document.getElementById('lesson-form');
    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'status';
    statusInput.value = '<?= ocms_escape($lesson['status']) ?>';
    form.appendChild(statusInput);
    form.submit();
}

async function deleteFile(name) {
    if (!confirm('Eliminare il file "' + name + '"?')) return;
    const res = await fetch(BASE + '/admin/lessons/delete-file/' + SLUG, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({file: name, _csrf_token: CSRF})
    });
    const data = await res.json();
    if (data.success) {
        const row = document.querySelector('.file-row[data-file="' + CSS.escape(name) + '"]');
        if (row) row.remove();
    } else {
        alert(data.error || 'Errore');
    }
}

function handleDrop(e) {
    const files = e.dataTransfer.files;
    if (!files.length) return;
    const fd = new FormData();
    for (let i = 0; i < files.length; i++) fd.append('files[]', files[i]);
    fetch(BASE + '/admin/lessons/upload-files/' + SLUG, {
        method: 'POST',
        body: fd
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.error || 'Errore upload');
    });
}
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
