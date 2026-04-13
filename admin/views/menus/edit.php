<?php
$isNew = ($menu === null);
$pageTitle = $isNew ? 'Nuovo Menu' : 'Modifica Menu';
$activeMenu = 'menus';

$menu = $menu ?? [
    'name' => '',
    'label' => '',
    'items' => [],
];

ob_start();
?>
<style>
    .menu-builder { display: grid; grid-template-columns: 1fr 300px; gap: 24px; align-items: start; }
    .menu-builder-main { min-width: 0; }

    /* Items list */
    .menu-items-list { min-height: 60px; }
    .menu-item {
        background: var(--bg-input);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        margin-bottom: 8px;
        transition: box-shadow 0.15s;
    }
    .menu-item.dragging { opacity: 0.5; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .menu-item-header {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px; cursor: pointer; user-select: none;
    }
    .menu-item-drag {
        cursor: grab; color: var(--text-muted); font-size: 1.1rem;
        display: flex; align-items: center; padding: 2px;
    }
    .menu-item-drag:active { cursor: grabbing; }
    .menu-item-title { flex: 1; font-weight: 600; font-size: 0.9rem; }
    .menu-item-title.unpublished { opacity: 0.4; text-decoration: line-through; }
    .menu-item-type { font-size: 0.75rem; color: var(--text-muted); margin-right: 8px; }
    .menu-item-pub {
        display: flex; align-items: center; gap: 4px; margin-right: 4px;
        font-size: 0.72rem; cursor: pointer; user-select: none;
        padding: 2px 8px; border-radius: 10px; font-weight: 600; transition: all 0.2s;
    }
    .menu-item-pub.on { background: rgba(34,197,94,0.15); color: #22c55e; }
    .menu-item-pub.off { background: rgba(239,68,68,0.12); color: #ef4444; }
    .menu-item-toggle {
        background: none; border: none; color: var(--text-muted);
        cursor: pointer; font-size: 1rem; padding: 4px; transition: transform 0.2s;
    }
    .menu-item-toggle.open { transform: rotate(180deg); }
    .menu-item-body {
        display: none; padding: 0 14px 14px;
        border-top: 1px solid var(--border);
    }
    .menu-item-body.open { display: block; padding-top: 14px; }
    .menu-item-body .form-group { margin-bottom: 12px; }
    .menu-item-body .form-group:last-child { margin-bottom: 0; }
    .menu-item-body .form-input { padding: 8px 10px; font-size: 0.85rem; }
    .menu-item-body .form-select { padding: 8px 10px; font-size: 0.85rem; }

    .menu-item-actions {
        display: flex; gap: 8px; margin-top: 12px; padding-top: 12px;
        border-top: 1px solid var(--border);
    }

    /* Children (nested) */
    .menu-item-children {
        margin-left: 28px; min-height: 4px;
    }
    .menu-item-children .menu-item { border-left: 2px solid var(--primary); }
    .menu-item-children .menu-item-children .menu-item { border-left-color: #a78bfa; }

    /* Add panel */
    .add-panel h3 { font-size: 1rem; font-weight: 700; margin-bottom: 16px; }
    .add-panel .page-list { max-height: 300px; overflow-y: auto; margin-bottom: 16px; }
    .add-panel .page-item {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 10px; border-radius: 6px;
        cursor: pointer; font-size: 0.85rem; transition: background 0.1s;
    }
    .add-panel .page-item:hover { background: var(--bg-hover); }
    .add-panel .page-item input[type="checkbox"] { accent-color: var(--primary); }
    .add-panel .separator { border-top: 1px solid var(--border); margin: 16px 0; }

    .drop-indicator {
        height: 3px; background: var(--primary); border-radius: 2px;
        margin: 4px 0; display: none;
    }

    @media (max-width: 1024px) { .menu-builder { grid-template-columns: 1fr; } }
</style>

<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1><?= $isNew ? 'Nuovo Menu' : 'Modifica Menu: ' . ocms_escape($menu['label']) ?></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= ocms_base_url() ?>/admin/menus" class="btn btn-secondary">Annulla</a>
        <button type="button" class="btn btn-primary" id="btn-save-menu">Salva Menu</button>
    </div>
</div>

<!-- Nome / Etichetta -->
<div class="card" style="margin-bottom:24px;">
    <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
            <label>Nome (identificativo)</label>
            <input type="text" id="menu-name" class="form-input" placeholder="es. main, footer, sidebar"
                   value="<?= ocms_escape($menu['name']) ?>" <?= !$isNew ? 'readonly style="opacity:0.6;"' : '' ?>>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Etichetta</label>
            <input type="text" id="menu-label" class="form-input" placeholder="es. Menu Principale"
                   value="<?= ocms_escape($menu['label']) ?>">
        </div>
    </div>
</div>

<div class="menu-builder">
    <!-- MAIN: Lista voci menu -->
    <div class="menu-builder-main">
        <div class="card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Struttura Menu</h3>
            <div class="menu-items-list" id="menu-items">
                <!-- Popolato via JS -->
            </div>
            <div id="empty-state" class="empty-state" style="padding:40px 20px;display:none;">
                <div class="icon">&#9776;</div>
                <h3>Menu vuoto</h3>
                <p>Aggiungi voci dal pannello a destra</p>
            </div>
        </div>
    </div>

    <!-- SIDEBAR: Aggiungi voci -->
    <div class="editor-sidebar">
        <!-- Aggiungi da pagine -->
        <div class="card add-panel">
            <h3>Aggiungi Pagine</h3>
            <div class="page-list" id="pages-list">
                <?php if (empty($pages)): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem;">Nessuna pagina pubblicata</p>
                <?php else: ?>
                    <?php foreach ($pages as $p): ?>
                    <label class="page-item">
                        <input type="checkbox" value="<?= ocms_escape($p['slug']) ?>" data-title="<?= ocms_escape($p['title']) ?>">
                        <?= ocms_escape($p['title']) ?>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;" id="btn-add-pages">
                + Aggiungi Selezionate
            </button>
        </div>

        <!-- Aggiungi link custom -->
        <div class="card add-panel">
            <h3>Link Personalizzato</h3>
            <div class="form-group">
                <label>URL</label>
                <input type="text" id="custom-url" class="form-input" placeholder="https://... o /percorso">
            </div>
            <div class="form-group">
                <label>Etichetta</label>
                <input type="text" id="custom-label" class="form-input" placeholder="Testo del link">
            </div>
            <button type="button" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;" id="btn-add-custom">
                + Aggiungi Link
            </button>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const CSRF = '<?= ocms_csrf_token() ?>';
    const BASE = '<?= ocms_base_url() ?>';
    const isNew = <?= $isNew ? 'true' : 'false' ?>;
    const originalName = '<?= ocms_escape($menu['name']) ?>';

    // Stato menu
    let menuItems = <?= json_encode($menu['items'], JSON_UNESCAPED_UNICODE) ?>;

    // ─── RENDER ───
    function uuid() {
        return 'xxxx-xxxx'.replace(/x/g, () => ((Math.random()*16)|0).toString(16));
    }

    function renderItems(items, container) {
        container.innerHTML = '';
        if (!items.length) {
            document.getElementById('empty-state').style.display = 'block';
            return;
        }
        document.getElementById('empty-state').style.display = 'none';
        items.forEach((item, idx) => {
            container.appendChild(createItemElement(item, idx, items));
        });
        initDragDrop();
    }

    function createItemElement(item, idx, parentArray) {
        const div = document.createElement('div');
        div.className = 'menu-item';
        div.dataset.id = item.id;
        div.draggable = true;

        const isPage = item.url.startsWith('/') && !item.url.startsWith('//') && !item.url.startsWith('/#');
        const typeLabel = item.url.startsWith('http') ? 'Link esterno' : 'Pagina';

        const pub = item.published !== false;
        div.innerHTML = `
            <div class="menu-item-header">
                <span class="menu-item-drag">&#9776;</span>
                <span class="menu-item-title ${pub ? '' : 'unpublished'}">${escHtml(item.label)}</span>
                <span class="menu-item-type">${typeLabel}</span>
                <span class="menu-item-pub ${pub ? 'on' : 'off'}" title="Clicca per ${pub ? 'nascondere' : 'pubblicare'}">${pub ? 'Visibile' : 'Nascosto'}</span>
                <button class="menu-item-toggle" title="Espandi">&#9660;</button>
            </div>
            <div class="menu-item-body">
                <div class="form-group">
                    <label>Etichetta</label>
                    <input type="text" class="form-input item-label" value="${escAttr(item.label)}">
                </div>
                <div class="form-group">
                    <label>URL</label>
                    <input type="text" class="form-input item-url" value="${escAttr(item.url)}">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Target</label>
                        <select class="form-select item-target">
                            <option value="_self" ${item.target==='_self'?'selected':''}>Stessa finestra</option>
                            <option value="_blank" ${item.target==='_blank'?'selected':''}>Nuova finestra</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Icona (classe CSS)</label>
                        <input type="text" class="form-input item-icon" value="${escAttr(item.icon || '')}" placeholder="es. icon-home">
                    </div>
                </div>
                <div class="menu-item-actions">
                    <button type="button" class="btn btn-secondary btn-sm btn-add-child">+ Sotto-voce</button>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-item" style="margin-left:auto;">Rimuovi</button>
                </div>
            </div>
            <div class="menu-item-children"></div>
        `;

        // Toggle expand
        const toggle = div.querySelector('.menu-item-toggle');
        const body = div.querySelector('.menu-item-body');
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            body.classList.toggle('open');
            toggle.classList.toggle('open');
        });

        // Toggle pubblicazione
        div.querySelector('.menu-item-pub').addEventListener('click', (e) => {
            e.stopPropagation();
            item.published = item.published === false ? true : false;
            renderAll();
        });

        // Sync changes
        div.querySelector('.item-label').addEventListener('input', (e) => {
            item.label = e.target.value;
            div.querySelector('.menu-item-title').textContent = e.target.value;
        });
        div.querySelector('.item-url').addEventListener('input', (e) => { item.url = e.target.value; });
        div.querySelector('.item-target').addEventListener('change', (e) => { item.target = e.target.value; });
        div.querySelector('.item-icon').addEventListener('input', (e) => { item.icon = e.target.value; });

        // Add child
        div.querySelector('.btn-add-child').addEventListener('click', () => {
            if (!item.children) item.children = [];
            item.children.push({ id: uuid(), label: 'Nuova voce', url: '#', target: '_self', icon: '', published: true, children: [] });
            renderAll();
        });

        // Remove
        div.querySelector('.btn-remove-item').addEventListener('click', () => {
            parentArray.splice(idx, 1);
            renderAll();
        });

        // Render children
        if (item.children && item.children.length) {
            const childContainer = div.querySelector('.menu-item-children');
            item.children.forEach((child, cIdx) => {
                childContainer.appendChild(createItemElement(child, cIdx, item.children));
            });
        }

        return div;
    }

    function renderAll() {
        renderItems(menuItems, document.getElementById('menu-items'));
    }

    // ─── DRAG & DROP ───
    let draggedItem = null;
    let draggedData = null;
    let draggedParent = null;

    function initDragDrop() {
        document.querySelectorAll('.menu-item').forEach(el => {
            el.addEventListener('dragstart', onDragStart);
            el.addEventListener('dragend', onDragEnd);
            el.addEventListener('dragover', onDragOver);
            el.addEventListener('drop', onDrop);
        });
    }

    function findItemById(items, id) {
        for (let i = 0; i < items.length; i++) {
            if (items[i].id === id) return { array: items, index: i, item: items[i] };
            if (items[i].children && items[i].children.length) {
                const found = findItemById(items[i].children, id);
                if (found) return found;
            }
        }
        return null;
    }

    function onDragStart(e) {
        e.stopPropagation();
        draggedItem = this;
        draggedItem.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.id);
    }

    function onDragEnd(e) {
        e.stopPropagation();
        if (draggedItem) draggedItem.classList.remove('dragging');
        document.querySelectorAll('.menu-item').forEach(el => el.style.borderTop = '');
        draggedItem = null;
    }

    function onDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!draggedItem || draggedItem === this) return;

        document.querySelectorAll('.menu-item').forEach(el => el.style.borderTop = '');

        const rect = this.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        if (e.clientY < midY) {
            this.style.borderTop = '3px solid var(--primary)';
        } else {
            this.style.borderTop = '';
        }
    }

    function onDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        const fromId = e.dataTransfer.getData('text/plain');
        const toId = this.dataset.id;
        if (fromId === toId) return;

        const from = findItemById(menuItems, fromId);
        const to = findItemById(menuItems, toId);
        if (!from || !to) return;

        // Rimuovi dalla posizione originale
        const [movedItem] = from.array.splice(from.index, 1);

        // Ricalcola posizione target
        const toRefresh = findItemById(menuItems, toId);
        if (!toRefresh) {
            // Fallback: metti in fondo
            menuItems.push(movedItem);
        } else {
            const rect = this.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                toRefresh.array.splice(toRefresh.index, 0, movedItem);
            } else {
                toRefresh.array.splice(toRefresh.index + 1, 0, movedItem);
            }
        }

        renderAll();
    }

    // ─── ADD PAGES ───
    document.getElementById('btn-add-pages').addEventListener('click', () => {
        const checkboxes = document.querySelectorAll('#pages-list input[type="checkbox"]:checked');
        checkboxes.forEach(cb => {
            menuItems.push({
                id: uuid(),
                label: cb.dataset.title,
                url: '/' + cb.value,
                target: '_self',
                icon: '',
                published: true,
                children: []
            });
            cb.checked = false;
        });
        renderAll();
    });

    // ─── ADD CUSTOM LINK ───
    document.getElementById('btn-add-custom').addEventListener('click', () => {
        const url = document.getElementById('custom-url').value.trim();
        const label = document.getElementById('custom-label').value.trim();
        if (!url || !label) return;

        menuItems.push({
            id: uuid(),
            label: label,
            url: url,
            target: url.startsWith('http') ? '_blank' : '_self',
            icon: '',
            published: true,
            children: []
        });
        document.getElementById('custom-url').value = '';
        document.getElementById('custom-label').value = '';
        renderAll();
    });

    // ─── SAVE ───
    document.getElementById('btn-save-menu').addEventListener('click', async () => {
        const name = document.getElementById('menu-name').value.trim();
        const label = document.getElementById('menu-label').value.trim();
        if (!name) { alert('Inserisci un nome per il menu'); return; }

        const btn = document.getElementById('btn-save-menu');
        btn.disabled = true;
        btn.textContent = 'Salvataggio...';

        try {
            const res = await fetch(BASE + '/admin/menus/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    _csrf_token: CSRF,
                    name: name,
                    label: label || name,
                    original_name: originalName,
                    items: menuItems
                })
            });

            const data = await res.json();
            if (data.success) {
                // Redirect alla modifica con il nuovo nome
                window.location.href = BASE + '/admin/menus/edit/' + data.name;
            } else {
                alert(data.error || 'Errore nel salvataggio');
            }
        } catch (err) {
            alert('Errore di rete: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salva Menu';
        }
    });

    // ─── UTILS ───
    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function escAttr(s) { return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

    // Init
    renderAll();
});
</script>

<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
