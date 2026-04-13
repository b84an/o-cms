<?php
$pageTitle = 'Crea Tema';
$activeMenu = 'themes';

ob_start();
?>
<style>
.wizard-container { max-width:760px; }
.wizard-steps { display:flex; gap:0; margin-bottom:32px; }
.wizard-step { flex:1; text-align:center; padding:14px 8px; font-size:0.8rem; font-weight:600; color:var(--text-muted); cursor:pointer; position:relative; }
.wizard-step.active { color:var(--primary-light); }
.wizard-step.done { color:var(--success); }
.wizard-step .step-num { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:var(--bg-input); border:2px solid var(--border); margin-bottom:6px; font-size:0.8rem; font-weight:700; transition:all 0.2s; }
.wizard-step.active .step-num { border-color:var(--primary); background:var(--primary); color:white; }
.wizard-step.done .step-num { border-color:var(--success); background:var(--success); color:white; }
.wizard-step .step-label { display:block; }
.wizard-step::after { content:''; position:absolute; top:27px; left:50%; width:100%; height:2px; background:var(--border); z-index:-1; }
.wizard-step:last-child::after { display:none; }
.wizard-step.done::after { background:var(--success); }
.wizard-panel { display:none; }
.wizard-panel.active { display:block; }
.wizard-nav { display:flex; justify-content:space-between; margin-top:28px; padding-top:20px; border-top:1px solid var(--border); }

.color-picker-group { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.color-picker-item { display:flex; align-items:center; gap:10px; padding:12px; background:var(--bg-input); border:1px solid var(--border); border-radius:8px; }
.color-picker-item input[type="color"] { width:40px; height:40px; border:none; background:none; cursor:pointer; border-radius:6px; }
.color-picker-item .color-info { flex:1; }
.color-picker-item .color-label { font-weight:600; font-size:0.85rem; display:block; }
.color-picker-item .color-desc { font-size:0.75rem; color:var(--text-muted); display:block; margin-top:2px; }

.font-option { display:flex; align-items:center; gap:12px; padding:12px 16px; background:var(--bg-input); border:1px solid var(--border); border-radius:8px; cursor:pointer; transition:all 0.15s; margin-bottom:8px; }
.font-option:hover { border-color:var(--primary); }
.font-option input { accent-color:var(--primary); }
.font-option .font-name { font-weight:600; font-size:0.9rem; }
.font-option .font-preview { color:var(--text-muted); font-size:0.85rem; }

.preview-box { background:var(--bg-input); border:1px solid var(--border); border-radius:12px; padding:24px; margin-top:16px; overflow:hidden; }
.preview-header { display:flex; justify-content:space-between; align-items:center; padding-bottom:12px; border-bottom:1px solid var(--border); margin-bottom:16px; }
.preview-logo { font-weight:800; font-size:1.1rem; }
.preview-nav { display:flex; gap:16px; font-size:0.8rem; }
.preview-hero { text-align:center; padding:32px 0; }
.preview-hero h2 { font-size:1.5rem; font-weight:800; margin-bottom:8px; }
.preview-hero p { font-size:0.85rem; opacity:0.6; }
.preview-cards { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.preview-card { border-radius:8px; padding:16px; font-size:0.75rem; font-weight:600; }

.structure-tree { font-family:monospace; font-size:0.85rem; line-height:1.8; color:var(--text-muted); padding:16px; background:var(--bg-input); border-radius:8px; white-space:pre; }
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Crea Nuovo Tema</h1>
    <a href="<?= ocms_base_url() ?>/admin/themes" class="btn btn-secondary">Annulla</a>
</div>

<div class="wizard-container">
    <div class="wizard-steps">
        <div class="wizard-step active" data-step="1"><span class="step-num">1</span><span class="step-label">Info</span></div>
        <div class="wizard-step" data-step="2"><span class="step-num">2</span><span class="step-label">Colori</span></div>
        <div class="wizard-step" data-step="3"><span class="step-num">3</span><span class="step-label">Font & Stile</span></div>
        <div class="wizard-step" data-step="4"><span class="step-num">4</span><span class="step-label">Anteprima</span></div>
    </div>

    <form method="POST" action="<?= ocms_base_url() ?>/admin/themes/wizard" id="theme-form">
        <?= ocms_csrf_field() ?>

        <!-- STEP 1: Info -->
        <div class="wizard-panel active" data-panel="1">
            <div class="card">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Informazioni Tema</h3>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:20px;">
                    Queste informazioni appariranno nel theme.json e nella lista temi.
                </p>

                <div class="form-group">
                    <label>Nome Tema *</label>
                    <input type="text" name="name" id="theme-name" class="form-input" placeholder="es. Midnight Blue, Corporate Light" required>
                    <div class="form-hint">Verrà usato come ID del tema (convertito in slug)</div>
                </div>
                <div class="form-group">
                    <label>Descrizione</label>
                    <textarea name="description" id="theme-desc" class="form-textarea" rows="2" placeholder="Tema elegante con palette scura e accenti viola..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Autore</label><input type="text" name="author" class="form-input" placeholder="Il tuo nome"></div>
                    <div class="form-group"><label>Sito Autore</label><input type="text" name="author_url" class="form-input" placeholder="https://..."></div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Licenza</label>
                    <select name="license" class="form-select">
                        <option value="MIT">MIT (libera)</option>
                        <option value="GPL-3.0">GPL 3.0</option>
                        <option value="Proprietary">Proprietaria</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- STEP 2: Colori -->
        <div class="wizard-panel" data-panel="2">
            <div class="card">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Palette Colori</h3>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:20px;">
                    Scegli i 4 colori principali. Potrai modificarli dopo nel file CSS.<br>
                    <strong>Suggerimento:</strong> per un tema dark usa uno sfondo scuro (#0f172a) e testo chiaro (#f1f5f9).
                    Per un tema light inverti i valori.
                </p>

                <div class="color-picker-group">
                    <div class="color-picker-item">
                        <input type="color" name="color_primary" id="c-primary" value="#6366f1">
                        <div class="color-info">
                            <span class="color-label">Primario</span>
                            <span class="color-desc">Pulsanti, link, accenti. Scegli un colore vivace.</span>
                        </div>
                    </div>
                    <div class="color-picker-item">
                        <input type="color" name="color_secondary" id="c-secondary" value="#a78bfa">
                        <div class="color-info">
                            <span class="color-label">Secondario</span>
                            <span class="color-desc">Hover, gradienti. Complementare al primario.</span>
                        </div>
                    </div>
                    <div class="color-picker-item">
                        <input type="color" name="color_bg" id="c-bg" value="#0f172a">
                        <div class="color-info">
                            <span class="color-label">Sfondo</span>
                            <span class="color-desc">Sfondo principale del sito.</span>
                        </div>
                    </div>
                    <div class="color-picker-item">
                        <input type="color" name="color_text" id="c-text" value="#f1f5f9">
                        <div class="color-info">
                            <span class="color-label">Testo</span>
                            <span class="color-desc">Colore del testo. Deve contrastare con lo sfondo.</span>
                        </div>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <p style="font-size:0.8rem;font-weight:600;margin-bottom:8px;">Preset rapidi:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#6366f1','#a78bfa','#0f172a','#f1f5f9')">Indigo Dark</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#3b82f6','#60a5fa','#0c1222','#e2e8f0')">Blue Night</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#10b981','#34d399','#0d1117','#f0fdf4')">Emerald Dark</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#f59e0b','#fbbf24','#1a1a2e','#fef3c7')">Amber Dark</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#ef4444','#f87171','#1c1917','#fef2f2')">Red Dark</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#2563eb','#3b82f6','#ffffff','#1e293b')">Blue Light</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#059669','#10b981','#fafafa','#1f2937')">Green Light</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setColors('#7c3aed','#8b5cf6','#faf5ff','#1e1b4b')">Purple Light</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 3: Font & Stile -->
        <div class="wizard-panel" data-panel="3">
            <div class="card">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">Font & Stile Layout</h3>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:20px;">
                    Scegli il font da Google Fonts e lo stile del layout.
                </p>

                <label style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;display:block;">Font</label>

                <?php $fonts = [
                    'Inter' => 'Moderno e leggibile, ideale per interfacce',
                    'Plus Jakarta Sans' => 'Elegante e contemporaneo',
                    'DM Sans' => 'Pulito e geometrico',
                    'Outfit' => 'Arrotondato e amichevole',
                    'Space Grotesk' => 'Tecnico e futuristico',
                    'Playfair Display' => 'Serif editoriale, perfetto per blog',
                    'Merriweather' => 'Serif classico, ottima leggibilità',
                    'Roboto' => 'Il font più usato al mondo',
                ]; foreach ($fonts as $fname => $fdesc): ?>
                <label class="font-option">
                    <input type="radio" name="font" value="<?= $fname ?>" <?= $fname === 'Inter' ? 'checked' : '' ?>>
                    <div>
                        <span class="font-name"><?= $fname ?></span>
                        <span class="font-preview"><?= $fdesc ?></span>
                    </div>
                </label>
                <?php endforeach; ?>

                <div style="margin-top:24px;">
                    <label style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;display:block;">Stile Layout</label>
                    <select name="layout_style" class="form-select">
                        <option value="modern">Moderno (sticky header, animazioni hover)</option>
                        <option value="minimal">Minimale (pulito, senza fronzoli)</option>
                        <option value="editorial">Editoriale (centrato, tipografia grande)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- STEP 4: Anteprima -->
        <div class="wizard-panel" data-panel="4">
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:16px;">Anteprima Tema</h3>
                <div class="preview-box" id="preview-box">
                    <div class="preview-header">
                        <span class="preview-logo" id="p-logo">Il Mio Sito</span>
                        <div class="preview-nav"><span>Home</span> <span>Blog</span> <span>Contatti</span></div>
                    </div>
                    <div class="preview-hero">
                        <h2 id="p-hero-title">Benvenuto</h2>
                        <p>Il tuo nuovo sito è pronto</p>
                    </div>
                    <div class="preview-cards" id="p-cards"></div>
                </div>
            </div>

            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px;">File che verranno creati</h3>
                <div class="structure-tree" id="structure-tree"></div>
            </div>
        </div>

        <div class="wizard-nav">
            <button type="button" class="btn btn-secondary" id="btn-prev" style="visibility:hidden;">Indietro</button>
            <button type="button" class="btn btn-primary" id="btn-next">Avanti</button>
            <button type="submit" class="btn btn-primary" id="btn-create" style="display:none;">Crea Tema</button>
        </div>
    </form>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let cur = 1;
    const tot = 4;
    const steps = document.querySelectorAll('.wizard-step');
    const panels = document.querySelectorAll('.wizard-panel');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnCreate = document.getElementById('btn-create');

    function goTo(s) {
        if (cur === 1 && s > 1 && !document.getElementById('theme-name').value.trim()) { document.getElementById('theme-name').focus(); return; }
        cur = s;
        steps.forEach(el => { const n=+el.dataset.step; el.classList.remove('active','done'); if(n===cur) el.classList.add('active'); if(n<cur) el.classList.add('done'); });
        panels.forEach(p => p.classList.toggle('active', +p.dataset.panel === cur));
        btnPrev.style.visibility = cur > 1 ? 'visible' : 'hidden';
        btnNext.style.display = cur < tot ? '' : 'none';
        btnCreate.style.display = cur === tot ? '' : 'none';
        if (cur === 4) updatePreview();
    }
    btnNext.addEventListener('click', () => goTo(cur+1));
    btnPrev.addEventListener('click', () => goTo(cur-1));
    steps.forEach(s => s.addEventListener('click', () => { const n=+s.dataset.step; if(n<=cur+1) goTo(n); }));

    window.setColors = function(p,s,bg,t) {
        document.getElementById('c-primary').value = p;
        document.getElementById('c-secondary').value = s;
        document.getElementById('c-bg').value = bg;
        document.getElementById('c-text').value = t;
    };

    function updatePreview() {
        const name = document.getElementById('theme-name').value || 'Tema';
        const primary = document.getElementById('c-primary').value;
        const secondary = document.getElementById('c-secondary').value;
        const bg = document.getElementById('c-bg').value;
        const text = document.getElementById('c-text').value;
        const font = document.querySelector('input[name="font"]:checked')?.value || 'Inter';

        const box = document.getElementById('preview-box');
        box.style.background = bg;
        box.style.color = text;
        box.style.fontFamily = font + ', sans-serif';
        box.style.borderColor = text + '15';

        document.getElementById('p-logo').style.background = `linear-gradient(135deg, ${primary}, ${secondary})`;
        document.getElementById('p-logo').style.webkitBackgroundClip = 'text';
        document.getElementById('p-logo').style.webkitTextFillColor = 'transparent';

        document.querySelector('.preview-header').style.borderColor = text + '15';
        document.querySelector('.preview-nav').style.color = text + '80';

        const cards = document.getElementById('p-cards');
        cards.innerHTML = `
            <div class="preview-card" style="background:${primary}22;color:${primary};">Pagine</div>
            <div class="preview-card" style="background:${secondary}22;color:${secondary};">Articoli</div>
            <div class="preview-card" style="background:${text}11;color:${text}88;">Media</div>`;

        const slug = name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'');
        document.getElementById('structure-tree').textContent =
`themes/${slug}/
├── theme.json          ← Manifest (colori, font, autore)
├── README.md           ← Guida personalizzazione
├── assets/
│   ├── css/style.css   ← CSS con le tue variabili
│   ├── js/app.js       ← JavaScript
│   └── img/            ← Immagini
├── layouts/            ← Layout personalizzati
└── templates/
    ├── home.php        ← Homepage
    ├── page.php        ← Pagina generica
    ├── blog.php        ← Lista articoli
    ├── article.php     ← Articolo singolo
    └── 404.php         ← Errore 404`;
    }
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
