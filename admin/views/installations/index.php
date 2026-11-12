<?php
$pageTitle = 'Installazioni O-CMS';
$activeMenu = 'installations';

ob_start();
?>

<div class="page-header">
    <h1>Installazioni O-CMS</h1>
    <span style="color:var(--text-muted);font-size:0.875rem;"><?= count($installations) ?> installazione/i registrate</span>
</div>

<?php if (empty($installations)): ?>
<div class="card" style="padding:40px;text-align:center;">
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:16px;">
        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
    </svg>
    <h3 style="color:var(--text-muted);margin-bottom:8px;">Nessuna installazione registrata</h3>
    <p style="color:var(--text-muted);font-size:0.875rem;">Quando qualcuno installa O-CMS, il ping apparira qui.</p>
</div>
<?php else: ?>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Dominio</th>
                    <th>Versione CMS</th>
                    <th>PHP</th>
                    <th>Data installazione</th>
                    <th>Ricevuto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installations as $inst): ?>
                <tr>
                    <td>
                        <a href="https://<?= ocms_escape($inst['domain']) ?>" target="_blank" rel="noopener" style="color:var(--primary);text-decoration:none;font-weight:600;">
                            <?= ocms_escape($inst['domain']) ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-left:4px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </a>
                    </td>
                    <td><span style="background:var(--primary);color:white;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;"><?= ocms_escape($inst['cms_version'] ?? '?') ?></span></td>
                    <td style="color:var(--text-muted);"><?= ocms_escape($inst['php_version'] ?? '?') ?></td>
                    <td style="color:var(--text-muted);font-size:0.85rem;"><?= ocms_escape($inst['timestamp'] ?? '-') ?></td>
                    <td style="color:var(--text-muted);font-size:0.85rem;"><?= ocms_escape($inst['received_at'] ?? '-') ?></td>
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
