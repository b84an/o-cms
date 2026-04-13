<?php
$pageTitle = 'Layout Builder';
$activeMenu = 'layouts';
ob_start();
?>

<div class="page-header">
    <h1>Layout Builder</h1>
</div>

<div class="card" style="margin-bottom:24px;background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.06));border-color:rgba(99,102,241,0.2);">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:6px;">Come funziona</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);line-height:1.6;">
        Il <strong>Layout Base</strong> definisce header, contenuto e footer di tutte le pagine. Puoi creare layout aggiuntivi e assegnarli a pagine specifiche.
        Trascina moduli come testo, immagini, gallerie, video e molto altro nella griglia per costruire il tuo layout.
    </p>
</div>

<!-- Crea nuovo -->
<div class="card" style="margin-bottom:24px;">
    <form method="POST" action="<?= ocms_base_url() ?>/admin/layouts/create" style="display:flex;gap:12px;align-items:flex-end;">
        <?= ocms_csrf_field() ?>
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label>Nuovo Layout</label>
            <input type="text" name="name" class="form-input" placeholder="es. Homepage, Blog, Landing Page" required>
        </div>
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label>Descrizione</label>
            <input type="text" name="description" class="form-input" placeholder="Opzionale">
        </div>
        <button type="submit" class="btn btn-primary" style="height:fit-content;">+ Crea</button>
    </form>
</div>

<!-- Lista layout -->
<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:16px;">
    <?php foreach ($layouts as $l): ?>
    <div class="card" style="<?= $l['id'] === 'base' ? 'border-color:var(--primary);' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
            <div>
                <h3 style="font-size:1.05rem;font-weight:700;"><?= ocms_escape($l['name']) ?></h3>
                <?php if ($l['id'] === 'base'): ?>
                    <span class="badge badge-published" style="font-size:0.7rem;">Ereditato da tutte le pagine</span>
                <?php endif; ?>
            </div>
            <span style="font-size:0.75rem;color:var(--text-muted);font-family:monospace;"><?= ocms_escape($l['id']) ?></span>
        </div>
        <?php if (!empty($l['description'])): ?>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px;"><?= ocms_escape($l['description']) ?></p>
        <?php endif; ?>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:16px;">
            <?= count($l['sections'] ?? []) ?> sezioni
        </p>
        <div style="display:flex;gap:8px;">
            <a href="<?= ocms_base_url() ?>/admin/layouts/edit/<?= ocms_escape($l['id']) ?>" class="btn btn-primary btn-sm">Modifica</a>
            <?php if ($l['id'] !== 'base'): ?>
            <form method="POST" action="<?= ocms_base_url() ?>/admin/layouts/delete/<?= ocms_escape($l['id']) ?>" onsubmit="return confirm('Eliminare?');" style="display:inline;">
                <?= ocms_csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
