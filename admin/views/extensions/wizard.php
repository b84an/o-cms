<?php
$pageTitle = 'Crea Estensione';
$activeMenu = 'extensions';

ob_start();
?>
<style>
.wizard-container { max-width: 720px; }

.wizard-steps {
    display: flex; gap: 0; margin-bottom: 32px; position: relative;
}
.wizard-step {
    flex: 1; text-align: center; padding: 14px 8px;
    font-size: 0.8rem; font-weight: 600; color: var(--text-muted);
    position: relative; cursor: pointer; transition: color 0.2s;
}
.wizard-step.active { color: var(--primary-light); }
.wizard-step.done { color: var(--success); }
.wizard-step .step-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--bg-input); border: 2px solid var(--border);
    margin-bottom: 6px; font-size: 0.8rem; font-weight: 700;
    transition: all 0.2s;
}
.wizard-step.active .step-num { border-color: var(--primary); background: var(--primary); color: white; }
.wizard-step.done .step-num { border-color: var(--success); background: var(--success); color: white; }
.wizard-step .step-label { display: block; }

.wizard-step::after {
    content: ''; position: absolute; top: 27px; left: 50%; width: 100%;
    height: 2px; background: var(--border); z-index: -1;
}
.wizard-step:last-child::after { display: none; }
.wizard-step.done::after { background: var(--success); }

.wizard-panel { display: none; }
.wizard-panel.active { display: block; }

.wizard-nav { display: flex; justify-content: space-between; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border); }

.feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.feature-option {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: var(--bg-input); border: 1px solid var(--border);
    border-radius: var(--radius-sm); cursor: pointer; transition: all 0.15s;
}
.feature-option:hover { border-color: var(--primary); background: rgba(99,102,241,0.05); }
.feature-option input[type="checkbox"] { accent-color: var(--primary); width: 18px; height: 18px; }
.feature-option .feat-info { flex: 1; }
.feature-option .feat-title { font-weight: 600; font-size: 0.9rem; display: block; }
.feature-option .feat-desc { font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px; }

.preview-code {
    background: var(--bg-input); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 16px; font-family: monospace;
    font-size: 0.85rem; line-height: 1.6; overflow-x: auto;
    white-space: pre; color: var(--text-muted); max-height: 300px;
}

.preview-manifest { display: grid; grid-template-columns: auto 1fr; gap: 4px 16px; font-size: 0.85rem; }
.preview-manifest dt { color: var(--text-muted); font-weight: 600; }
.preview-manifest dd { color: var(--text); }
</style>

<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Crea Nuova Estensione</h1>
    <a href="<?= ocms_base_url() ?>/admin/extensions" class="btn btn-secondary">Annulla</a>
</div>

<div class="wizard-container">
    <!-- Steps indicator -->
    <div class="wizard-steps">
        <div class="wizard-step active" data-step="1">
            <span class="step-num">1</span>
            <span class="step-label">Info Base</span>
        </div>
        <div class="wizard-step" data-step="2">
            <span class="step-num">2</span>
            <span class="step-label">Funzionalità</span>
        </div>
        <div class="wizard-step" data-step="3">
            <span class="step-num">3</span>
            <span class="step-label">Permessi</span>
        </div>
        <div class="wizard-step" data-step="4">
            <span class="step-num">4</span>
            <span class="step-label">Riepilogo</span>
        </div>
    </div>

    <form method="POST" action="<?= ocms_base_url() ?>/admin/extensions/wizard" id="wizard-form">
        <?= ocms_csrf_field() ?>

        <!-- STEP 1: Info Base -->
        <div class="wizard-panel active" data-panel="1">
            <div class="card">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:20px;">Informazioni Base</h3>

                <div class="form-group">
                    <label>Nome Estensione *</label>
                    <input type="text" name="name" id="ext-name" class="form-input"
                           placeholder="es. Galleria Foto, Newsletter, Analytics" required>
                    <div class="form-hint">Il nome verrà usato come identificativo (convertito in slug)</div>
                </div>

                <div class="form-group">
                    <label>Descrizione</label>
                    <textarea name="description" id="ext-desc" class="form-textarea" rows="3"
                              placeholder="Cosa fa questa estensione?"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Autore</label>
                        <input type="text" name="author" id="ext-author" class="form-input" placeholder="Il tuo nome">
                    </div>
                    <div class="form-group">
                        <label>Sito Autore</label>
                        <input type="text" name="author_url" class="form-input" placeholder="https://...">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Licenza</label>
                        <select name="license" class="form-select">
                            <option value="MIT">MIT</option>
                            <option value="GPL-3.0">GPL 3.0</option>
                            <option value="Apache-2.0">Apache 2.0</option>
                            <option value="Proprietary">Proprietaria</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Icona</label>
                        <select name="icon" class="form-select">
                            <option value="puzzle">Puzzle (default)</option>
                            <option value="chart">Grafico</option>
                            <option value="mail">Email</option>
                            <option value="image">Immagine</option>
                            <option value="shopping">Shop</option>
                            <option value="tool">Strumento</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: Funzionalità -->
        <div class="wizard-panel" data-panel="2">
            <div class="card">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Cosa include la tua estensione?</h3>
                <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:20px;">
                    Seleziona le funzionalità. Verranno creati i file e le cartelle necessarie.
                </p>

                <div class="feature-grid">
                    <label class="feature-option">
                        <input type="checkbox" name="has_admin" checked>
                        <div class="feat-info">
                            <span class="feat-title">Pannello Admin</span>
                            <span class="feat-desc">Pagina nell'area admin con sidebar, rotte e viste</span>
                        </div>
                    </label>
                    <label class="feature-option">
                        <input type="checkbox" name="has_frontend">
                        <div class="feat-info">
                            <span class="feat-title">Pagine Frontend</span>
                            <span class="feat-desc">Template visibili ai visitatori del sito</span>
                        </div>
                    </label>
                    <label class="feature-option">
                        <input type="checkbox" name="has_assets" checked>
                        <div class="feat-info">
                            <span class="feat-title">Assets (CSS/JS)</span>
                            <span class="feat-desc">File CSS e JavaScript personalizzati</span>
                        </div>
                    </label>
                    <label class="feature-option">
                        <input type="checkbox" name="has_data">
                        <div class="feat-info">
                            <span class="feat-title">Dati Propri</span>
                            <span class="feat-desc">Cartella data/ per salvare JSON dell'estensione</span>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- STEP 3: Permessi -->
        <div class="wizard-panel" data-panel="3">
            <div class="card">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Permessi Richiesti</h3>
                <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:20px;">
                    Dichiara i permessi che l'estensione richiede. Saranno visibili agli utenti prima dell'attivazione.
                </p>

                <div class="form-group">
                    <label>Permessi (separati da virgola)</label>
                    <input type="text" name="permissions" class="form-input"
                           placeholder="es. read_pages, write_articles, manage_users">
                    <div class="form-hint">Lascia vuoto se l'estensione non richiede permessi speciali</div>
                </div>

                <div style="margin-top:16px;padding:16px;background:var(--bg-input);border-radius:var(--radius-sm);">
                    <h4 style="font-size:0.85rem;font-weight:600;margin-bottom:8px;">Permessi disponibili nel CMS:</h4>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php
                        $perms = ['read_pages','write_pages','delete_pages','read_articles','write_articles',
                                  'delete_articles','manage_menus','manage_media','manage_users','manage_settings',
                                  'manage_extensions','read_analytics'];
                        foreach ($perms as $p):
                        ?>
                            <span class="badge badge-published" style="cursor:pointer;font-size:0.7rem;"
                                  onclick="addPermission('<?= $p ?>')"><?= $p ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 4: Riepilogo -->
        <div class="wizard-panel" data-panel="4">
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:16px;">Riepilogo Estensione</h3>
                <dl class="preview-manifest" id="preview-manifest">
                </dl>
            </div>
            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px;">Struttura File</h3>
                <div class="preview-code" id="preview-structure"></div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="wizard-nav">
            <button type="button" class="btn btn-secondary" id="btn-prev" style="visibility:hidden;">Indietro</button>
            <button type="button" class="btn btn-primary" id="btn-next">Avanti</button>
            <button type="submit" class="btn btn-primary" id="btn-create" style="display:none;">Crea Estensione</button>
        </div>
    </form>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    const totalSteps = 4;
    const steps = document.querySelectorAll('.wizard-step');
    const panels = document.querySelectorAll('.wizard-panel');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnCreate = document.getElementById('btn-create');

    function goTo(step) {
        // Validazione step 1
        if (currentStep === 1 && step > 1) {
            const name = document.getElementById('ext-name').value.trim();
            if (!name) { document.getElementById('ext-name').focus(); return; }
        }

        currentStep = step;

        // Aggiorna step indicators
        steps.forEach(s => {
            const n = parseInt(s.dataset.step);
            s.classList.remove('active', 'done');
            if (n === currentStep) s.classList.add('active');
            if (n < currentStep) s.classList.add('done');
        });

        // Mostra pannello corrente
        panels.forEach(p => {
            p.classList.toggle('active', parseInt(p.dataset.panel) === currentStep);
        });

        // Navigazione
        btnPrev.style.visibility = currentStep > 1 ? 'visible' : 'hidden';
        btnNext.style.display = currentStep < totalSteps ? '' : 'none';
        btnCreate.style.display = currentStep === totalSteps ? '' : 'none';

        // Aggiorna riepilogo allo step 4
        if (currentStep === 4) updatePreview();
    }

    btnNext.addEventListener('click', () => goTo(currentStep + 1));
    btnPrev.addEventListener('click', () => goTo(currentStep - 1));
    steps.forEach(s => s.addEventListener('click', () => {
        const n = parseInt(s.dataset.step);
        if (n <= currentStep + 1) goTo(n);
    }));

    function updatePreview() {
        const name = document.getElementById('ext-name').value.trim();
        const desc = document.getElementById('ext-desc').value.trim();
        const author = document.getElementById('ext-author').value.trim();
        const hasAdmin = document.querySelector('[name="has_admin"]').checked;
        const hasFrontend = document.querySelector('[name="has_frontend"]').checked;
        const hasAssets = document.querySelector('[name="has_assets"]').checked;
        const hasData = document.querySelector('[name="has_data"]').checked;
        const slug = name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');

        // Manifest preview
        document.getElementById('preview-manifest').innerHTML = `
            <dt>ID</dt><dd>${slug}</dd>
            <dt>Nome</dt><dd>${escHtml(name)}</dd>
            <dt>Descrizione</dt><dd>${escHtml(desc) || '<em style="opacity:0.5">nessuna</em>'}</dd>
            <dt>Autore</dt><dd>${escHtml(author) || '<em style="opacity:0.5">non specificato</em>'}</dd>
            <dt>Pannello Admin</dt><dd>${hasAdmin ? 'Si' : 'No'}</dd>
            <dt>Frontend</dt><dd>${hasFrontend ? 'Si' : 'No'}</dd>
            <dt>Assets</dt><dd>${hasAssets ? 'Si' : 'No'}</dd>
            <dt>Dati</dt><dd>${hasData ? 'Si' : 'No'}</dd>
        `;

        // Structure preview
        let tree = `extensions/${slug}/\n`;
        tree += `├── extension.json\n`;
        tree += `├── boot.php\n`;
        tree += `├── install.php\n`;
        tree += `├── uninstall.php\n`;
        if (hasAdmin) tree += `├── views/\n│   └── index.php\n`;
        if (hasFrontend) tree += `├── templates/\n`;
        if (hasAssets) tree += `├── assets/\n│   ├── css/style.css\n│   └── js/app.js\n`;
        if (hasData) tree += `├── data/\n`;
        document.getElementById('preview-structure').textContent = tree;
    }

    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Aggiungi permesso cliccando sui badge
    window.addPermission = function(p) {
        const input = document.querySelector('[name="permissions"]');
        const current = input.value.split(',').map(s => s.trim()).filter(Boolean);
        if (!current.includes(p)) {
            current.push(p);
            input.value = current.join(', ');
        }
    };
});
</script>

<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
