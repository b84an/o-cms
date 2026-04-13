<?php
$isNew = ($form === null);
$pageTitle = $isNew ? 'Nuovo Form' : 'Modifica Form';
$activeMenu = 'forms';
$form = $form ?? ['id'=>'','name'=>'','slug'=>'','fields'=>[],'settings'=>['notify_email'=>'','success_message'=>'Grazie! Il modulo è stato inviato.','submit_label'=>'Invia'],'submissions'=>[],'created_at'=>''];
ob_start();
?>
<style>
.field-list { min-height: 40px; }
.field-item { background:var(--bg-input); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px; margin-bottom:10px; }
.field-item-header { display:flex; align-items:center; gap:10px; }
.field-item-header .drag { cursor:grab; color:var(--text-muted); }
.field-item-header .field-name { font-weight:600; font-size:0.9rem; flex:1; }
.field-config { display:none; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }
.field-config.open { display:block; }
.field-types { display:flex; flex-wrap:wrap; gap:8px; }
.field-type-btn { padding:8px 14px; background:var(--bg-input); border:1px solid var(--border); border-radius:6px; cursor:pointer; font-size:0.85rem; font-weight:500; color:var(--text); transition:all 0.15s; font-family:var(--font); }
.field-type-btn:hover { border-color:var(--primary); }
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1><?= $pageTitle ?></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= ocms_base_url() ?>/admin/forms" class="btn btn-secondary">Annulla</a>
        <button type="button" class="btn btn-primary" id="btn-save">Salva Form</button>
    </div>
</div>

<div class="editor-layout">
    <div class="editor-main">
        <div class="card" style="margin-bottom:20px;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Nome Form</label>
                <input type="text" id="form-name" class="form-input" placeholder="es. Contattaci, Richiedi Preventivo" value="<?= ocms_escape($form['name']) ?>" required>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Campi</h3>
            <div class="field-list" id="field-list"></div>
            <div style="margin-top:12px;">
                <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;">Aggiungi campo:</p>
                <div class="field-types">
                    <button class="field-type-btn" data-type="text">Testo</button>
                    <button class="field-type-btn" data-type="email">Email</button>
                    <button class="field-type-btn" data-type="textarea">Area Testo</button>
                    <button class="field-type-btn" data-type="select">Select</button>
                    <button class="field-type-btn" data-type="checkbox">Checkbox</button>
                    <button class="field-type-btn" data-type="radio">Radio</button>
                    <button class="field-type-btn" data-type="number">Numero</button>
                    <button class="field-type-btn" data-type="tel">Telefono</button>
                    <button class="field-type-btn" data-type="date">Data</button>
                    <button class="field-type-btn" data-type="file">File</button>
                </div>
            </div>
        </div>

        <?php if (!$isNew): ?>
        <div class="card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px;">Embed</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:12px;">Inserisci questo shortcode nel contenuto di una pagina o articolo:</p>
            <pre style="background:var(--bg-input);padding:12px;border-radius:8px;font-size:0.95rem;overflow-x:auto;cursor:pointer;user-select:all;">[form:<?= ocms_escape($form['slug']) ?>]</pre>
            <p style="font-size:0.75rem;color:var(--text-muted);margin-top:8px;">Il form verrà renderizzato automaticamente con tutti i campi, captcha e protezione CSRF.</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="editor-sidebar">
        <div class="card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Impostazioni</h3>
            <div class="form-group">
                <label>Email Notifica</label>
                <input type="email" id="notify-email" class="form-input" placeholder="admin@esempio.com" value="<?= ocms_escape($form['settings']['notify_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Messaggio Successo</label>
                <textarea id="success-msg" class="form-textarea" rows="2"><?= ocms_escape($form['settings']['success_message'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Testo Pulsante</label>
                <input type="text" id="submit-label" class="form-input" value="<?= ocms_escape($form['settings']['submit_label'] ?? 'Invia') ?>">
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const CSRF = '<?= ocms_csrf_token() ?>';
    const BASE = '<?= ocms_base_url() ?>';
    let fields = <?= json_encode($form['fields'], JSON_UNESCAPED_UNICODE) ?>;
    const formId = '<?= ocms_escape($form['id']) ?>';
    const createdAt = '<?= ocms_escape($form['created_at']) ?>';

    function uid() { return 'f' + Math.random().toString(36).substr(2,8); }

    function renderFields() {
        const list = document.getElementById('field-list');
        list.innerHTML = '';
        fields.forEach((f, i) => {
            const div = document.createElement('div');
            div.className = 'field-item';
            const typeLabels = {text:'Testo',email:'Email',textarea:'Area Testo',select:'Select',checkbox:'Checkbox',radio:'Radio',number:'Numero',tel:'Telefono',date:'Data',file:'File'};
            div.innerHTML = `
                <div class="field-item-header">
                    <span class="drag">&#9776;</span>
                    <span class="field-name">${esc(f.label || f.name)}</span>
                    <span class="badge badge-published" style="font-size:0.7rem;">${typeLabels[f.type]||f.type}</span>
                    <button style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem;" class="btn-toggle">&#9660;</button>
                    <button style="background:none;border:none;color:var(--error);cursor:pointer;font-size:1rem;" class="btn-remove">&#10005;</button>
                </div>
                <div class="field-config">
                    <div class="form-row" style="margin-bottom:10px;">
                        <div class="form-group" style="margin-bottom:0;"><label>Label</label><input type="text" class="form-input fc-label" value="${esc(f.label)}"></div>
                        <div class="form-group" style="margin-bottom:0;"><label>Name (ID)</label><input type="text" class="form-input fc-name" value="${esc(f.name)}"></div>
                    </div>
                    <div class="form-row" style="margin-bottom:10px;">
                        <div class="form-group" style="margin-bottom:0;"><label>Placeholder</label><input type="text" class="form-input fc-placeholder" value="${esc(f.placeholder||'')}"></div>
                        <div class="form-group" style="margin-bottom:0;"><label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" class="fc-required" ${f.required?'checked':''}> Obbligatorio</label></div>
                    </div>
                    ${['select','radio','checkbox'].includes(f.type) ? `<div class="form-group" style="margin-bottom:0;"><label>Opzioni (una per riga)</label><textarea class="form-textarea fc-options" rows="3">${esc((f.options||[]).join('\\n'))}</textarea></div>` : ''}
                </div>`;

            div.querySelector('.btn-toggle').onclick = () => div.querySelector('.field-config').classList.toggle('open');
            div.querySelector('.btn-remove').onclick = () => { fields.splice(i,1); renderFields(); };

            const update = () => {
                f.label = div.querySelector('.fc-label').value;
                f.name = div.querySelector('.fc-name').value;
                f.placeholder = div.querySelector('.fc-placeholder')?.value || '';
                f.required = div.querySelector('.fc-required')?.checked || false;
                const opts = div.querySelector('.fc-options');
                if (opts) f.options = opts.value.split('\\n').filter(Boolean);
                div.querySelector('.field-name').textContent = f.label || f.name;
            };
            div.querySelectorAll('input,textarea').forEach(el => el.addEventListener('input', update));
            list.appendChild(div);
        });
    }

    document.querySelectorAll('.field-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.type;
            const name = type + '_' + uid();
            fields.push({ type, name, label: btn.textContent, placeholder: '', required: false, options: [] });
            renderFields();
        });
    });

    document.getElementById('btn-save').addEventListener('click', async () => {
        const name = document.getElementById('form-name').value.trim();
        if (!name) { document.getElementById('form-name').focus(); return; }
        const btn = document.getElementById('btn-save');
        btn.disabled = true; btn.textContent = 'Salvataggio...';
        try {
            const res = await fetch(BASE + '/admin/forms/save', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    _csrf_token: CSRF, id: formId, name, fields, created_at: createdAt,
                    settings: {
                        notify_email: document.getElementById('notify-email').value,
                        success_message: document.getElementById('success-msg').value,
                        submit_label: document.getElementById('submit-label').value,
                    }
                })
            });
            const data = await res.json();
            if (data.success) window.location.href = BASE + '/admin/forms/edit/' + data.slug;
            else alert(data.error);
        } catch(e) { alert(e.message); }
        finally { btn.disabled = false; btn.textContent = 'Salva Form'; }
    });

    function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    renderFields();
});
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
