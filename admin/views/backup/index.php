<?php
$pageTitle = 'Backup';
$activeMenu = 'backup';
ob_start();
?>

<div class="page-header">
    <h1>Backup & Ripristino</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
    <!-- 1. Backup dati -->
    <div class="card" style="background:linear-gradient(135deg,rgba(59,130,246,0.1),rgba(59,130,246,0.03));border-color:rgba(59,130,246,0.2);">
        <h3 style="font-size:0.95rem;font-weight:700;margin-bottom:6px;">Backup Dati</h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:16px;">
            Salva <strong>dati JSON</strong> e <strong>file caricati</strong>. Per ripristinare sulla stessa installazione.
        </p>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/backup/create">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="btn btn-secondary" style="width:100%;justify-content:center;" onclick="this.textContent='Creazione...';this.disabled=true;this.form.submit();">
                Backup Dati
            </button>
        </form>
    </div>

    <!-- 2. Backup completo + installer -->
    <div class="card" style="background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(139,92,246,0.05));border-color:rgba(99,102,241,0.2);">
        <h3 style="font-size:0.95rem;font-weight:700;margin-bottom:6px;">Backup Completo + Installer</h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:16px;">
            Salva <strong>tutto il CMS</strong> con dati, media e <strong>installer.php</strong> per migrare su un altro server.
        </p>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/backup/full">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;" onclick="this.textContent='Creazione...';this.disabled=true;this.form.submit();">
                Backup Completo
            </button>
        </form>
    </div>

    <!-- 3. Pacchetto distribuibile -->
    <div class="card" style="background:linear-gradient(135deg,rgba(34,197,94,0.1),rgba(34,197,94,0.03));border-color:rgba(34,197,94,0.2);">
        <h3 style="font-size:0.95rem;font-weight:700;margin-bottom:6px;">Pacchetto Distribuibile</h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:16px;">
            CMS <strong>vuoto</strong>, senza dati personali, con <strong>installer.php</strong>. Per distribuire O-CMS ad altri.
        </p>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/backup/distributable">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="btn btn-secondary" style="width:100%;justify-content:center;color:var(--success,#22c55e);border-color:rgba(34,197,94,0.3);" onclick="this.textContent='Creazione...';this.disabled=true;this.form.submit();">
                Crea Pacchetto
            </button>
        </form>
    </div>
</div>

<?php if (empty($backups)): ?>
    <div class="card"><div class="empty-state" style="padding:40px 20px;">
        <div class="icon">&#128190;</div><h3>Nessun backup</h3>
        <p>Crea il tuo primo backup per proteggere i tuoi dati.</p>
    </div></div>
<?php else: ?>
    <div class="card"><div class="table-wrapper"><table class="data-table">
        <thead><tr>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Dimensione</th>
            <th>Data</th>
            <th>Azioni</th>
        </tr></thead>
        <tbody>
        <?php foreach ($backups as $b):
            $size = $b['size'] < 1024*1024 ? round($b['size']/1024).' KB' : round($b['size']/(1024*1024),1).' MB';
            // Tipo in base al nome
            if (str_starts_with($b['name'], 'ocms-dist-')) {
                $type = 'Distribuibile';
                $typeClass = 'color:var(--success,#22c55e);';
            } elseif (str_starts_with($b['name'], 'ocms-full-')) {
                $type = 'Completo';
                $typeClass = 'color:var(--primary-light);';
            } else {
                $type = 'Dati';
                $typeClass = 'color:var(--text-muted);';
            }
        ?>
        <tr>
            <td style="font-weight:600;font-family:monospace;font-size:0.85rem;"><?= ocms_escape($b['name']) ?></td>
            <td><span style="font-size:0.8rem;font-weight:600;<?= $typeClass ?>"><?= $type ?></span></td>
            <td style="color:var(--text-muted);"><?= $size ?></td>
            <td style="color:var(--text-muted);font-size:0.85rem;"><?= ocms_format_date($b['date']) ?></td>
            <td><div style="display:flex;gap:8px;">
                <a href="<?= ocms_base_url() ?>/admin/backup/download/<?= ocms_escape($b['name']) ?>" class="btn btn-secondary btn-sm">Scarica</a>
                <?php if (!str_starts_with($b['name'], 'ocms-dist-')): ?>
                <form method="POST" action="<?= ocms_base_url() ?>/admin/backup/restore/<?= ocms_escape($b['name']) ?>" onsubmit="return confirm('ATTENZIONE: Ripristinare sovrascriver&agrave; tutti i dati attuali. Continuare?');" style="display:inline;">
                    <?= ocms_csrf_field() ?><button type="submit" class="btn btn-secondary btn-sm">Ripristina</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?= ocms_base_url() ?>/admin/backup/delete/<?= ocms_escape($b['name']) ?>" onsubmit="return confirm('Eliminare?');" style="display:inline;">
                    <?= ocms_csrf_field() ?><button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                </form>
            </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
