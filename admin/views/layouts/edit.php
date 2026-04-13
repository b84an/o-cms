<?php
$pageTitle = 'Layout: ' . ($layout['name'] ?? '');
$activeMenu = 'layouts';
ob_start();
?>
<link rel="stylesheet" href="<?= ocms_base_url() ?>/admin/assets/css/layout-builder.css">
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <div>
        <a href="<?= ocms_base_url() ?>/admin/layouts" style="font-size:0.8rem;color:var(--text-muted);">← Layout Builder</a>
        <h1 style="margin-top:4px;"><?= ocms_escape($layout['name']) ?></h1>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-secondary" id="btn-preview">Anteprima</button>
        <button class="btn btn-primary" id="btn-save">Salva Layout</button>
    </div>
</div>

<div class="lb-container">
    <!-- CANVAS -->
    <div class="lb-canvas" id="lb-canvas">
        <!-- Sezioni renderizzate via JS -->
    </div>

    <!-- PANNELLO MODULI -->
    <div class="lb-sidebar">
        <div class="lb-panel">
            <h3 class="lb-panel-title">Aggiungi Sezione</h3>
            <button class="lb-add-section-btn" id="btn-add-section">+ Nuova Sezione</button>
        </div>

        <div class="lb-panel">
            <h3 class="lb-panel-title">Moduli</h3>
            <p class="lb-hint">Trascina un modulo dentro una colonna</p>
            <div class="lb-modules-grid" id="modules-palette">
                <div class="lb-module-item" draggable="true" data-type="heading"><span class="lb-mod-icon">H</span><span>Titolo</span></div>
                <div class="lb-module-item" draggable="true" data-type="text"><span class="lb-mod-icon">T</span><span>Testo</span></div>
                <div class="lb-module-item" draggable="true" data-type="richtext"><span class="lb-mod-icon">&#9998;</span><span>Rich Text</span></div>
                <div class="lb-module-item" draggable="true" data-type="image"><span class="lb-mod-icon">&#128247;</span><span>Immagine</span></div>
                <div class="lb-module-item" draggable="true" data-type="gallery"><span class="lb-mod-icon">&#127748;</span><span>Galleria</span></div>
                <div class="lb-module-item" draggable="true" data-type="video"><span class="lb-mod-icon">&#9654;</span><span>Video</span></div>
                <div class="lb-module-item" draggable="true" data-type="button"><span class="lb-mod-icon">&#128280;</span><span>Pulsante</span></div>
                <div class="lb-module-item" draggable="true" data-type="card"><span class="lb-mod-icon">&#9632;</span><span>Card</span></div>
                <div class="lb-module-item" draggable="true" data-type="articles"><span class="lb-mod-icon">&#128196;</span><span>Articoli</span></div>
                <div class="lb-module-item" draggable="true" data-type="html"><span class="lb-mod-icon">&lt;/&gt;</span><span>HTML</span></div>
                <div class="lb-module-item" draggable="true" data-type="spacer"><span class="lb-mod-icon">&#8597;</span><span>Spaziatore</span></div>
                <div class="lb-module-item" draggable="true" data-type="divider"><span class="lb-mod-icon">—</span><span>Divisore</span></div>
                <div class="lb-module-item" draggable="true" data-type="logo"><span class="lb-mod-icon">&#9733;</span><span>Logo</span></div>
                <div class="lb-module-item" draggable="true" data-type="menu"><span class="lb-mod-icon">&#9776;</span><span>Menu</span></div>
                <div class="lb-module-item" draggable="true" data-type="content"><span class="lb-mod-icon">&#128221;</span><span>Contenuto</span></div>
                <div class="lb-module-item" draggable="true" data-type="icon"><span class="lb-mod-icon">&#11088;</span><span>Icona</span></div>
                <div class="lb-module-item" draggable="true" data-type="social"><span class="lb-mod-icon">&#128279;</span><span>Social</span></div>
                <div class="lb-module-item" draggable="true" data-type="breadcrumb"><span class="lb-mod-icon">&#8250;</span><span>Breadcrumb</span></div>
            </div>
        </div>

        <!-- Pannello impostazioni modulo (mostrato al click) -->
        <div class="lb-panel" id="module-settings-panel" style="display:none;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <h3 class="lb-panel-title" id="settings-title">Impostazioni</h3>
                <button style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.1rem;" id="btn-close-settings">✕</button>
            </div>
            <div id="settings-form"></div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="preview-modal" style="display:none;position:fixed;inset:0;z-index:2000;background:var(--bg);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 24px;background:var(--bg-card);border-bottom:1px solid var(--border);">
        <span style="font-weight:600;">Anteprima Layout</span>
        <button style="background:none;border:none;color:var(--text);cursor:pointer;font-size:1.2rem;" onclick="document.getElementById('preview-modal').style.display='none'">✕</button>
    </div>
    <iframe id="preview-frame" style="width:100%;height:calc(100vh - 50px);border:none;background:var(--bg);"></iframe>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {

const CSRF = '<?= ocms_csrf_token() ?>';
const BASE = '<?= ocms_base_url() ?>';
const layoutId = '<?= ocms_escape($layout['id']) ?>';
let sections = <?= json_encode($layout['sections'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
let selectedModule = null;

function uid() { return 'id-' + Math.random().toString(36).substr(2, 9); }

// ═══ RENDER ═══

function renderCanvas() {
    const canvas = document.getElementById('lb-canvas');
    canvas.innerHTML = '';

    if (!sections.length) {
        canvas.innerHTML = '<div class="lb-empty">Nessuna sezione. Clicca "Nuova Sezione" per iniziare.</div>';
        return;
    }

    sections.forEach((section, si) => {
        const sEl = document.createElement('div');
        sEl.className = 'lb-section';
        sEl.dataset.index = si;

        // Header sezione
        sEl.innerHTML = `
            <div class="lb-section-header">
                <span class="lb-section-tag">&lt;${esc(section.tag || 'section')}&gt;</span>
                <span class="lb-section-name">${esc(section.id)}</span>
                <div class="lb-section-actions">
                    <button class="lb-btn-icon" title="Aggiungi riga" onclick="addRow(${si})">+ Riga</button>
                    <button class="lb-btn-icon" title="Impostazioni" onclick="editSection(${si})">⚙</button>
                    <button class="lb-btn-icon lb-btn-danger" title="Rimuovi" onclick="removeSection(${si})">✕</button>
                </div>
            </div>
        `;

        // Righe
        const rowsContainer = document.createElement('div');
        rowsContainer.className = 'lb-rows';

        (section.rows || []).forEach((row, ri) => {
            const rEl = document.createElement('div');
            rEl.className = 'lb-row';
            rEl.innerHTML = `
                <div class="lb-row-header">
                    <span class="lb-row-label">Riga ${ri + 1}</span>
                    <div>
                        <button class="lb-btn-icon" title="Aggiungi colonna" onclick="addColumn(${si},${ri})">+ Col</button>
                        <button class="lb-btn-icon" title="Layout colonne" onclick="promptColumns(${si},${ri})">&#9638;</button>
                        <button class="lb-btn-icon lb-btn-danger" onclick="removeRow(${si},${ri})">✕</button>
                    </div>
                </div>
            `;

            const colsContainer = document.createElement('div');
            colsContainer.className = 'lb-columns';
            const totalW = (row.columns || []).reduce((a, c) => a + (c.width || 12), 0);

            (row.columns || []).forEach((col, ci) => {
                const cEl = document.createElement('div');
                cEl.className = 'lb-column';
                cEl.style.flex = (col.width || 12) + '';
                cEl.dataset.section = si;
                cEl.dataset.row = ri;
                cEl.dataset.col = ci;

                // Drop zone
                cEl.addEventListener('dragover', onDragOver);
                cEl.addEventListener('dragleave', onDragLeave);
                cEl.addEventListener('drop', onDrop);

                const header = document.createElement('div');
                header.className = 'lb-col-header';
                header.innerHTML = `<span>${col.width || 12}/${totalW}</span><button class="lb-btn-icon lb-btn-danger" onclick="removeColumn(${si},${ri},${ci})">✕</button>`;
                cEl.appendChild(header);

                // Moduli nella colonna
                (col.modules || []).forEach((mod, mi) => {
                    const mEl = document.createElement('div');
                    mEl.className = 'lb-module' + (selectedModule && selectedModule.si===si && selectedModule.ri===ri && selectedModule.ci===ci && selectedModule.mi===mi ? ' selected' : '');
                    mEl.draggable = true;
                    mEl.dataset.modIndex = mi;
                    mEl.dataset.section = si;
                    mEl.dataset.row = ri;
                    mEl.dataset.col = ci;
                    mEl.innerHTML = `
                        <div class="lb-module-header">
                            <span class="lb-module-type">${esc(mod.type)}</span>
                            <span class="lb-module-label">${getModuleLabel(mod)}</span>
                            <div class="lb-module-actions">
                                <button class="lb-btn-icon" onclick="editModule(${si},${ri},${ci},${mi})">⚙</button>
                                <button class="lb-btn-icon lb-btn-danger" onclick="removeModule(${si},${ri},${ci},${mi})">✕</button>
                            </div>
                        </div>
                    `;
                    mEl.addEventListener('click', () => editModule(si, ri, ci, mi));
                    mEl.addEventListener('dragstart', onModuleDragStart);
                    cEl.appendChild(mEl);
                });

                // Placeholder se vuota
                if (!(col.modules || []).length) {
                    const ph = document.createElement('div');
                    ph.className = 'lb-drop-placeholder';
                    ph.textContent = 'Trascina modulo qui';
                    cEl.appendChild(ph);
                }

                colsContainer.appendChild(cEl);
            });

            rEl.appendChild(colsContainer);
            rowsContainer.appendChild(rEl);
        });

        sEl.appendChild(rowsContainer);
        canvas.appendChild(sEl);
    });
}

function getModuleLabel(mod) {
    const s = mod.settings || {};
    switch(mod.type) {
        case 'heading': return esc(s.text || 'Titolo');
        case 'text': return esc((s.text || 'Testo...').substring(0, 40));
        case 'image': return s.src ? '🖼 ' + esc(s.src.split('/').pop()) : 'Immagine';
        case 'button': return esc(s.text || 'Pulsante');
        case 'html': return '&lt;/&gt; Custom HTML';
        case 'content': return '📄 Contenuto Pagina';
        case 'logo': return '⭐ Logo Sito';
        case 'menu': return '☰ Menu: ' + esc(s.menu || 'main');
        case 'video': return '▶ Video';
        case 'gallery': return '🖼 Galleria (' + (s.images || []).length + ')';
        case 'articles': return '📰 Articoli (' + (s.count || 3) + ')';
        case 'spacer': return '↕ ' + (s.height || 40) + 'px';
        case 'card': return '▪ Card: ' + esc(s.title || '');
        default: return mod.type;
    }
}

// ═══ SEZIONI ═══

window.addRow = (si) => { sections[si].rows.push({ id: uid(), columns: [{ id: uid(), width: 12, modules: [] }] }); renderCanvas(); };
window.removeRow = (si, ri) => { sections[si].rows.splice(ri, 1); renderCanvas(); };
window.removeSection = (si) => { if(confirm('Rimuovere questa sezione?')) { sections.splice(si, 1); renderCanvas(); } };
window.addColumn = (si, ri) => { sections[si].rows[ri].columns.push({ id: uid(), width: 6, modules: [] }); renderCanvas(); };
window.removeColumn = (si, ri, ci) => { sections[si].rows[ri].columns.splice(ci, 1); renderCanvas(); };
window.removeModule = (si, ri, ci, mi) => { sections[si].rows[ri].columns[ci].modules.splice(mi, 1); renderCanvas(); closeSettings(); };

window.promptColumns = (si, ri) => {
    const presets = {'1': [12], '2': [6,6], '3': [4,4,4], '4': [3,3,3,3], '8+4': [8,4], '4+8': [4,8], '3+6+3': [3,6,3]};
    const choice = prompt('Layout colonne:\n1 = piena\n2 = due uguali\n3 = tre uguali\n4 = quattro\n8+4 / 4+8 / 3+6+3', '2');
    if (!choice || !presets[choice]) return;
    const row = sections[si].rows[ri];
    const oldModules = row.columns.flatMap(c => c.modules || []);
    row.columns = presets[choice].map((w, i) => ({ id: uid(), width: w, modules: i === 0 ? oldModules : [] }));
    renderCanvas();
};

document.getElementById('btn-add-section').addEventListener('click', () => {
    const id = prompt('ID sezione (es: hero, cta, features):', 'section-' + (sections.length + 1));
    if (!id) return;
    sections.push({
        id: id, type: 'section', tag: 'section', class: '', fullWidth: false, bgColor: '', bgImage: '',
        rows: [{ id: uid(), columns: [{ id: uid(), width: 12, modules: [] }] }]
    });
    renderCanvas();
});

window.editSection = (si) => {
    const s = sections[si];
    const panel = document.getElementById('module-settings-panel');
    const form = document.getElementById('settings-form');
    document.getElementById('settings-title').textContent = 'Sezione: ' + s.id;
    selectedModule = null;
    form.innerHTML = `
        <div class="form-group"><label>ID</label><input class="form-input" value="${esc(s.id)}" onchange="sections[${si}].id=this.value;renderCanvas()"></div>
        <div class="form-group"><label>Tag HTML</label>
            <select class="form-select" onchange="sections[${si}].tag=this.value">
                ${['section','header','footer','main','div','aside','nav'].map(t=>`<option ${s.tag===t?'selected':''}>${t}</option>`).join('')}
            </select>
        </div>
        <div class="form-group"><label>Classe CSS</label><input class="form-input" value="${esc(s.class||'')}" onchange="sections[${si}].class=this.value"></div>
        <div class="form-group"><label>Colore Sfondo</label><input type="color" class="form-input" value="${s.bgColor||'#0f172a'}" onchange="sections[${si}].bgColor=this.value" style="height:40px;padding:4px;"></div>
        <div class="form-group"><label>Immagine Sfondo (URL)</label><input class="form-input" value="${esc(s.bgImage||'')}" onchange="sections[${si}].bgImage=this.value"></div>
        <div class="form-group"><label><input type="checkbox" ${s.fullWidth?'checked':''} onchange="sections[${si}].fullWidth=this.checked"> Larghezza piena</label></div>
    `;
    panel.style.display = 'block';
};

// ═══ MODULI — SETTINGS ═══

window.editModule = (si, ri, ci, mi) => {
    selectedModule = { si, ri, ci, mi };
    const mod = sections[si].rows[ri].columns[ci].modules[mi];
    const s = mod.settings || {};
    const panel = document.getElementById('module-settings-panel');
    const form = document.getElementById('settings-form');
    document.getElementById('settings-title').textContent = mod.type;

    let html = '';
    const inp = (label, key, val, type='text') => `<div class="form-group"><label>${label}</label><input type="${type}" class="form-input settings-field" data-key="${key}" value="${esc(val||'')}"${type==='color'?' style="height:40px;padding:4px;"':''}></div>`;
    const sel = (label, key, val, opts) => `<div class="form-group"><label>${label}</label><select class="form-select settings-field" data-key="${key}">${opts.map(o=>`<option value="${o}" ${val===o?'selected':''}>${o}</option>`).join('')}</select></div>`;
    const area = (label, key, val, rows=3) => `<div class="form-group"><label>${label}</label><textarea class="form-textarea settings-field" data-key="${key}" rows="${rows}">${esc(val||'')}</textarea></div>`;

    switch (mod.type) {
        case 'heading':
            html = inp('Testo','text',s.text) + sel('Tag','tag',s.tag||'h2',['h1','h2','h3','h4','h5','h6']) + sel('Allineamento','align',s.align||'left',['left','center','right']) + inp('Colore','color',s.color||'','color');
            break;
        case 'text':
            html = area('Testo','text',s.text) + sel('Allineamento','align',s.align||'left',['left','center','right']);
            break;
        case 'richtext':
            html = area('HTML','html',s.html,6);
            break;
        case 'image':
            html = inp('Percorso immagine','src',s.src) + inp('Alt text','alt',s.alt) + inp('Larghezza','width',s.width||'100%') + inp('Raggio bordo (px)','radius',s.radius||'12') + inp('Link (opzionale)','link',s.link);
            break;
        case 'gallery':
            html = area('Immagini (una per riga, formato: percorso|alt)','_gallery_text',(s.images||[]).map(i=>i.src+'|'+(i.alt||'')).join('\n'),5)
                 + inp('Colonne','columns',s.columns||3,'number') + inp('Gap (px)','gap',s.gap||'12') + inp('Raggio bordo','radius',s.radius||'8');
            break;
        case 'video':
            html = inp('URL (YouTube, Vimeo o file)','url',s.url);
            break;
        case 'button':
            html = inp('Testo','text',s.text||'Click') + inp('URL','url',s.url||'#') + sel('Stile','style',s.style||'primary',['primary','secondary']) + sel('Allineamento','align',s.align||'left',['left','center','right']) + sel('Target','target',s.target||'_self',['_self','_blank']);
            break;
        case 'spacer':
            html = inp('Altezza (px)','height',s.height||'40','number');
            break;
        case 'divider':
            html = inp('Colore','color',s.color||'','color') + inp('Larghezza','width',s.width||'100%') + inp('Margine (px)','margin',s.margin||'24','number');
            break;
        case 'html':
            html = area('Codice HTML','code',s.code,8);
            break;
        case 'logo':
            html = inp('Immagine logo (URL, vuoto=nome sito)','image',s.image) + inp('Altezza (px)','height',s.height||'40');
            break;
        case 'menu':
            html = inp('Nome menu','menu',s.menu||'main');
            break;
        case 'card':
            html = inp('Titolo','title',s.title) + area('Testo','text',s.text) + inp('Immagine','image',s.image) + inp('Link','link',s.link);
            break;
        case 'icon':
            html = inp('Emoji/Icona','emoji',s.emoji||'⭐') + inp('Dimensione (px)','size',s.size||'48','number') + sel('Allineamento','align',s.align||'center',['left','center','right']);
            break;
        case 'articles':
            html = inp('Numero articoli','count',s.count||3,'number') + inp('Colonne','columns',s.columns||3,'number');
            break;
        case 'social':
            html = area('Link social (uno per riga: icona|url)','_social_text',(s.links||[]).map(l=>(l.icon||l.label)+'|'+l.url).join('\n'),4);
            break;
        default:
            html = '<p style="color:var(--text-muted);font-size:0.85rem;">Nessuna impostazione per questo modulo.</p>';
    }

    form.innerHTML = html;
    panel.style.display = 'block';

    // Bind changes
    form.querySelectorAll('.settings-field').forEach(el => {
        el.addEventListener('input', () => updateModuleSettings(si, ri, ci, mi));
        el.addEventListener('change', () => updateModuleSettings(si, ri, ci, mi));
    });
    renderCanvas();
};

function updateModuleSettings(si, ri, ci, mi) {
    const mod = sections[si].rows[ri].columns[ci].modules[mi];
    const form = document.getElementById('settings-form');
    const s = {};
    form.querySelectorAll('.settings-field').forEach(el => {
        const key = el.dataset.key;
        s[key] = el.type === 'number' ? parseInt(el.value) || 0 : el.value;
    });

    // Parsing speciali
    if (s._gallery_text !== undefined) {
        s.images = s._gallery_text.split('\n').filter(Boolean).map(line => {
            const [src, alt] = line.split('|');
            return { src: src.trim(), alt: (alt || '').trim() };
        });
        delete s._gallery_text;
    }
    if (s._social_text !== undefined) {
        s.links = s._social_text.split('\n').filter(Boolean).map(line => {
            const [icon, url] = line.split('|');
            return { icon: (icon||'').trim(), label: (icon||'').trim(), url: (url||'#').trim() };
        });
        delete s._social_text;
    }

    mod.settings = { ...(mod.settings || {}), ...s };
    renderCanvas();
}

function closeSettings() {
    document.getElementById('module-settings-panel').style.display = 'none';
    selectedModule = null;
    renderCanvas();
}
document.getElementById('btn-close-settings').onclick = closeSettings;

// ═══ DRAG & DROP ═══

let dragType = null; // 'new' or 'move'
let dragData = null;

// Dalla palette
document.querySelectorAll('#modules-palette .lb-module-item').forEach(el => {
    el.addEventListener('dragstart', e => {
        dragType = 'new';
        dragData = { type: el.dataset.type };
        e.dataTransfer.effectAllowed = 'copy';
        el.classList.add('dragging');
    });
    el.addEventListener('dragend', () => { el.classList.remove('dragging'); dragType = null; });
});

// Da canvas (riordino)
function onModuleDragStart(e) {
    e.stopPropagation();
    dragType = 'move';
    dragData = {
        si: +this.dataset.section, ri: +this.dataset.row,
        ci: +this.dataset.col, mi: +this.dataset.modIndex
    };
    e.dataTransfer.effectAllowed = 'move';
    this.classList.add('dragging');
    setTimeout(() => this.style.opacity = '0.3', 0);
}

function onDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.add('drag-over');
}
function onDragLeave(e) {
    this.classList.remove('drag-over');
}
function onDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');

    const si = +this.dataset.section;
    const ri = +this.dataset.row;
    const ci = +this.dataset.col;
    const col = sections[si].rows[ri].columns[ci];

    if (dragType === 'new') {
        const defaults = getModuleDefaults(dragData.type);
        col.modules.push({ id: uid(), type: dragData.type, settings: defaults });
    } else if (dragType === 'move') {
        const srcCol = sections[dragData.si].rows[dragData.ri].columns[dragData.ci];
        const [moved] = srcCol.modules.splice(dragData.mi, 1);
        col.modules.push(moved);
    }

    dragType = null;
    dragData = null;
    renderCanvas();
}

function getModuleDefaults(type) {
    const d = {
        heading: { text: 'Titolo', tag: 'h2', align: 'left', color: '' },
        text: { text: 'Il tuo testo qui...', align: 'left' },
        richtext: { html: '<p>Contenuto rich text</p>' },
        image: { src: '', alt: '', width: '100%', radius: '12', link: '' },
        gallery: { images: [], columns: 3, gap: '12', radius: '8' },
        video: { url: '' },
        button: { text: 'Scopri di più', url: '#', style: 'primary', align: 'left', target: '_self' },
        spacer: { height: '40' },
        divider: { color: '', width: '100%', margin: '24' },
        html: { code: '' },
        logo: { image: '', height: '40' },
        menu: { menu: 'main' },
        content: {},
        breadcrumb: { separator: '›' },
        social: { links: [], size: '20' },
        card: { title: 'Card', text: 'Descrizione', image: '', link: '' },
        icon: { emoji: '⭐', size: '48', align: 'center' },
        articles: { count: 3, columns: 3 },
    };
    return d[type] || {};
}

// ═══ SAVE ═══

document.getElementById('btn-save').addEventListener('click', async () => {
    const btn = document.getElementById('btn-save');
    btn.disabled = true; btn.textContent = 'Salvataggio...';
    try {
        const res = await fetch(BASE + '/admin/layouts/save', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                _csrf_token: CSRF,
                id: layoutId,
                name: '<?= ocms_escape($layout['name']) ?>',
                description: '<?= ocms_escape($layout['description'] ?? '') ?>',
                sections: sections
            })
        });
        const data = await res.json();
        if (data.success) { btn.textContent = 'Salvato!'; setTimeout(() => { btn.textContent = 'Salva Layout'; btn.disabled = false; }, 1500); }
        else { alert(data.error); btn.disabled = false; btn.textContent = 'Salva Layout'; }
    } catch(e) { alert(e.message); btn.disabled = false; btn.textContent = 'Salva Layout'; }
});

// ═══ PREVIEW ═══

document.getElementById('btn-preview').addEventListener('click', async () => {
    const res = await fetch(BASE + '/admin/layouts/preview', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ sections })
    });
    const html = await res.text();
    const frame = document.getElementById('preview-frame');
    const theme = '<?= ocms_escape($app->config['theme'] ?? 'flavor') ?>';
    const fullHtml = `<!DOCTYPE html><html><head>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="${BASE}/themes/${theme}/assets/css/style.css">
        <style>body{background:var(--bg,#0f172a);color:var(--text,#f1f5f9);}</style>
    </head><body>${html}</body></html>`;
    frame.srcdoc = fullHtml;
    document.getElementById('preview-modal').style.display = 'block';
});

function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

// Init
renderCanvas();
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
