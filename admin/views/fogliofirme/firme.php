<?php
$pageTitle = 'Firme: ' . ($sheet['name'] ?? '');
$activeMenu = 'fogliofirme';
ob_start();
$firme = $sheet['firme'] ?? [];
?>

<div class="page-header">
    <h1>Firme: <?= ocms_escape($sheet['name']) ?></h1>
    <div style="display:flex;gap:8px;">
        <a href="<?= ocms_base_url() ?>/admin/fogliofirme/export/<?= ocms_escape($sheet['slug']) ?>" class="btn btn-primary">Esporta CSV</a>
        <a href="<?= ocms_base_url() ?>/admin/fogliofirme/edit/<?= ocms_escape($sheet['slug']) ?>" class="btn btn-secondary">Modifica evento</a>
        <a href="<?= ocms_base_url() ?>/admin/fogliofirme" class="btn btn-secondary">&larr; Elenco</a>
    </div>
</div>

<?php foreach (ocms_flash_get() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : 'success' ?>"><?= ocms_escape($f['message']) ?></div>
<?php endforeach; ?>

<div class="card">
    <p style="margin:0;font-size:0.9rem;color:var(--text-muted);">
        Totale firme: <strong><?= count($firme) ?></strong>
        <?php if (!empty($sheet['event_date'])): ?> &middot; Data evento: <strong><?= ocms_escape($sheet['event_date']) ?></strong><?php endif; ?>
        <?php if (!empty($sheet['access_code'])): ?> &middot; Codice: <code><?= ocms_escape($sheet['access_code']) ?></code><?php endif; ?>
    </p>
</div>

<?php if (empty($firme)): ?>
    <div class="card"><div class="empty-state"><h3>Nessuna firma ancora</h3><p>Condividi il link o proietta il QR e le firme compariranno qui in tempo reale.</p></div></div>
<?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Nome</th><th>Cognome</th><th>Email</th><th>Data firma</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($firme as $i => $f): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= ocms_escape($f['nome'] ?? '') ?></td>
                        <td><?= ocms_escape($f['cognome'] ?? '') ?></td>
                        <td><a href="mailto:<?= ocms_escape($f['email'] ?? '') ?>"><?= ocms_escape($f['email'] ?? '') ?></a></td>
                        <td><?= !empty($f['signed_at']) ? ocms_escape(ocms_format_date($f['signed_at'], 'd/m/Y H:i:s')) : '—' ?></td>
                        <td>
                            <form method="POST" action="<?= ocms_base_url() ?>/admin/fogliofirme/firme/<?= ocms_escape($sheet['slug']) ?>/delete/<?= ocms_escape($f['id'] ?? '') ?>" onsubmit="return confirm('Rimuovere questa firma?');" style="display:inline;">
                                <?= ocms_csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-sm">Rimuovi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
