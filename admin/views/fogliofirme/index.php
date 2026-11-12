<?php
$pageTitle = 'FoglioFirme';
$activeMenu = 'fogliofirme';
ob_start();
?>

<div class="page-header">
    <h1>FoglioFirme</h1>
    <a href="<?= ocms_base_url() ?>/admin/fogliofirme/new" class="btn btn-primary">+ Nuovo evento</a>
</div>

<?php foreach (ocms_flash_get() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : 'success' ?>"><?= ocms_escape($f['message']) ?></div>
<?php endforeach; ?>

<?php if (empty($sheets)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="icon">&#9997;</div>
            <h3>Nessun evento</h3>
            <p>Crea il tuo primo foglio firme per raccogliere presenze a riunioni, webinar, lezioni e corsi di formazione.</p>
            <a href="<?= ocms_base_url() ?>/admin/fogliofirme/new" class="btn btn-primary">Crea evento</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Data</th>
                        <th>Stato</th>
                        <th>Firme</th>
                        <th>Codice</th>
                        <th>Link</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sheets as $s): ?>
                    <?php
                        $publicUrl = ocms_absolute_url('/firma/' . $s['slug']);
                        $expired = ocms_fogliofirme_is_expired($s);
                        $open = ($s['status'] ?? 'open') === 'open' && !$expired;
                    ?>
                    <tr>
                        <td style="font-weight:600;">
                            <?= ocms_escape($s['name']) ?>
                            <?php if (!empty($s['description'])): ?>
                                <div style="font-size:0.8rem;color:var(--text-muted);font-weight:400;margin-top:2px;"><?= ocms_escape(ocms_truncate($s['description'], 80)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= ocms_escape($s['event_date'] ?? '') ?></td>
                        <td>
                            <?php if ($open): ?>
                                <span class="badge badge-published">Aperto</span>
                            <?php elseif ($expired): ?>
                                <span class="badge badge-draft">Scaduto</span>
                            <?php else: ?>
                                <span class="badge badge-draft">Chiuso</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-published"><?= count($s['firme'] ?? []) ?></span></td>
                        <td>
                            <?php if (!empty($s['access_code'])): ?>
                                <code style="font-size:0.8rem;"><?= ocms_escape($s['access_code']) ?></code>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= ocms_escape($publicUrl) ?>" target="_blank" style="font-size:0.8rem;" title="Apri link pubblico">Apri &rarr;</a>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="<?= ocms_base_url() ?>/admin/fogliofirme/firme/<?= ocms_escape($s['slug']) ?>" class="btn btn-primary btn-sm">Firme</a>
                                <a href="<?= ocms_base_url() ?>/admin/fogliofirme/edit/<?= ocms_escape($s['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                <a href="<?= ocms_base_url() ?>/firma/<?= ocms_escape($s['slug']) ?>/show?k=<?= ocms_signed_token('firma-show:' . $s['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm">Proietta</a>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/fogliofirme/toggle/<?= ocms_escape($s['slug']) ?>" style="display:inline;">
                                    <?= ocms_csrf_field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= $open ? 'Chiudi' : 'Riapri' ?></button>
                                </form>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/fogliofirme/delete/<?= ocms_escape($s['slug']) ?>" onsubmit="return confirm('Eliminare evento e tutte le firme?');" style="display:inline;">
                                    <?= ocms_csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                                </form>
                            </div>
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
