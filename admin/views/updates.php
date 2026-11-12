<?php
$pageTitle = 'Aggiornamenti';
$activeMenu = 'updates';

/** @var array $status */
/** @var array $preflight */
/** @var array $logs */

$current = $status['current'];
$latest = $status['latest'];
$available = $status['update_available'];
$error = $status['error'];
$checkedAt = $status['checked_at'] ?? null;
$fromCache = $status['from_cache'] ?? false;
$canApply = $available && $preflight['ok'] && !empty($status['download_url']);

ob_start();
?>

<div class="page-header">
    <h1>Aggiornamenti</h1>
</div>

<?php if ($error && !$latest): ?>
    <div class="flash-message error" style="margin-bottom:20px;">
        Impossibile contattare GitHub: <?= ocms_escape($error) ?>.
    </div>
<?php elseif ($error && $latest): ?>
    <div class="flash-message warning" style="margin-bottom:20px;">
        Ultimo controllo non riuscito (<?= ocms_escape($error) ?>). Mostrate informazioni dalla cache.
    </div>
<?php endif; ?>

<!-- Version card -->
<div class="card" style="padding:24px;margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
        <div>
            <div style="font-size:0.875rem;color:var(--text-muted);margin-bottom:4px;">Versione installata</div>
            <div style="font-size:1.75rem;font-weight:700;"><?= ocms_escape($current) ?></div>
        </div>

        <div style="flex:1;min-width:200px;text-align:center;">
            <?php if ($available): ?>
                <span style="display:inline-block;background:#16a34a;color:white;padding:6px 14px;border-radius:20px;font-size:0.875rem;font-weight:600;">
                    Aggiornamento disponibile
                </span>
            <?php elseif ($latest): ?>
                <span style="display:inline-block;background:#64748b;color:white;padding:6px 14px;border-radius:20px;font-size:0.875rem;font-weight:600;">
                    Sei aggiornato
                </span>
            <?php endif; ?>
        </div>

        <div style="text-align:right;">
            <div style="font-size:0.875rem;color:var(--text-muted);margin-bottom:4px;">Ultima versione</div>
            <div style="font-size:1.75rem;font-weight:700;">
                <?= $latest ? ocms_escape($latest) : '—' ?>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="font-size:0.875rem;color:var(--text-muted);">
            <?php if ($checkedAt): ?>
                Ultimo controllo: <?= date('d/m/Y H:i', $checkedAt) ?>
                <?= $fromCache ? '(cache)' : '' ?>
            <?php else: ?>
                Nessun controllo eseguito
            <?php endif; ?>
        </div>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/updates/check" style="margin:0;">
            <?= ocms_csrf_field() ?>
            <button type="submit" class="btn">Controlla ora</button>
        </form>
    </div>
</div>

<!-- Preflight -->
<div class="card" style="padding:24px;margin-bottom:20px;">
    <h3 style="margin-top:0;">Verifica di compatibilità</h3>
    <?php if ($preflight['ok']): ?>
        <div style="color:#15803d;font-weight:600;margin-bottom:8px;">✓ Tutti i controlli superati</div>
    <?php else: ?>
        <div style="color:#991b1b;font-weight:600;margin-bottom:8px;">✗ Problemi rilevati:</div>
        <ul style="color:#991b1b;margin:0 0 12px 20px;">
            <?php foreach ($preflight['issues'] as $issue): ?>
                <li><?= ocms_escape($issue) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($preflight['warnings'])): ?>
        <div style="color:#92400e;font-weight:600;margin-bottom:8px;">⚠ Avvisi:</div>
        <ul style="color:#92400e;margin:0 0 12px 20px;">
            <?php foreach ($preflight['warnings'] as $w): ?>
                <li><?= ocms_escape($w) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <details style="margin-top:12px;">
        <summary style="cursor:pointer;color:var(--text-muted);font-size:0.9rem;">Cosa viene controllato</summary>
        <ul style="margin:8px 0 0 20px;color:var(--text-muted);font-size:0.875rem;">
            <li>PHP &ge; 8.0 ed estensione ZIP disponibile</li>
            <li>Possibilità di scaricare da GitHub (curl o allow_url_fopen)</li>
            <li>Almeno 10 MB di spazio libero su disco</li>
            <li>Permessi di scrittura su <code>core/</code>, <code>admin/</code>, <code>themes/flavor/</code>, <code>index.php</code>, <code>.htaccess</code></li>
        </ul>
    </details>
</div>

<!-- Apply update -->
<?php if ($available): ?>
<div class="card" style="padding:24px;margin-bottom:20px;<?= $canApply ? 'background:#f0fdf4;border:1px solid #86efac;' : 'background:#fff7ed;border:1px solid #fdba74;' ?>">
    <h3 style="margin-top:0;<?= $canApply ? 'color:#15803d;' : 'color:#9a3412;' ?>">
        Installa <?= ocms_escape($latest) ?>
    </h3>

    <p style="margin-bottom:12px;">
        Il pacchetto verrà scaricato da GitHub e applicato automaticamente.
        Vengono aggiornati solo: <code>core/</code>, <code>admin/</code>, <code>themes/flavor/</code>,
        <code>index.php</code>, <code>install.php</code>, <code>.htaccess</code> e i file di documentazione.
    </p>
    <p style="margin-bottom:16px;">
        <strong>Non vengono mai toccati:</strong> <code>data/</code> (contenuti), <code>uploads/</code> (media),
        <code>extensions/</code>, temi personalizzati diversi da <code>flavor</code>.
    </p>
    <p style="margin-bottom:16px;color:var(--text-muted);font-size:0.875rem;">
        Prima dell'applicazione viene creato un backup automatico in <code>data/backups/</code>.
        Se qualcosa fallisce, il rollback è automatico. Durante l'aggiornamento il sito va in modalità manutenzione.
    </p>

    <?php if ($canApply): ?>
        <form method="POST" action="<?= ocms_base_url() ?>/admin/updates/apply"
              onsubmit="return confirm('Applicare l\'aggiornamento a <?= ocms_escape($latest) ?>? Il sito andrà in manutenzione durante l\'operazione.');"
              style="margin:0;">
            <?= ocms_csrf_field() ?>
            <input type="hidden" name="target_version" value="<?= ocms_escape($latest) ?>">
            <button type="submit" class="btn btn-primary">
                Installa aggiornamento
            </button>
            <?php if (!empty($status['release_url'])): ?>
                <a href="<?= ocms_escape($status['release_url']) ?>" target="_blank" rel="noopener" style="margin-left:12px;color:var(--text-muted);">
                    Apri su GitHub →
                </a>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div style="color:#9a3412;font-weight:600;">
            Aggiornamento non disponibile: correggi i problemi di compatibilità sopra.
        </div>
        <?php if (!empty($status['release_url'])): ?>
            <a href="<?= ocms_escape($status['release_url']) ?>" target="_blank" rel="noopener" style="display:inline-block;margin-top:12px;color:var(--text-muted);">
                Apri release su GitHub →
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Changelog -->
<?php if (!empty($status['changelog'])): ?>
<div class="card" style="padding:24px;margin-bottom:20px;">
    <h3 style="margin-top:0;">
        Changelog
        <?php if ($latest): ?>
            <span style="font-weight:400;color:var(--text-muted);font-size:0.9rem;">— <?= ocms_escape($latest) ?></span>
        <?php endif; ?>
        <?php if (!empty($status['published_at'])): ?>
            <span style="font-weight:400;color:var(--text-muted);font-size:0.85rem;float:right;">
                <?= date('d/m/Y', strtotime($status['published_at'])) ?>
            </span>
        <?php endif; ?>
    </h3>
    <div style="background:var(--bg-secondary,#f8fafc);padding:16px;border-radius:8px;white-space:pre-wrap;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:0.875rem;line-height:1.6;max-height:500px;overflow-y:auto;"><?= ocms_escape($status['changelog']) ?></div>
</div>
<?php endif; ?>

<!-- Logs -->
<?php if (!empty($logs)): ?>
<div class="card" style="padding:24px;">
    <h3 style="margin-top:0;">Log aggiornamenti</h3>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid var(--border);text-align:left;">
                <th style="padding:8px 4px;font-weight:600;font-size:0.875rem;">File</th>
                <th style="padding:8px 4px;font-weight:600;font-size:0.875rem;">Data</th>
                <th style="padding:8px 4px;font-weight:600;font-size:0.875rem;text-align:right;">Dimensione</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($logs, 0, 10) as $log): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:8px 4px;">
                        <a href="<?= ocms_base_url() ?>/admin/updates/log/<?= ocms_escape($log['name']) ?>" target="_blank">
                            <?= ocms_escape($log['name']) ?>
                        </a>
                    </td>
                    <td style="padding:8px 4px;color:var(--text-muted);font-size:0.875rem;">
                        <?= date('d/m/Y H:i', $log['mtime']) ?>
                    </td>
                    <td style="padding:8px 4px;color:var(--text-muted);font-size:0.875rem;text-align:right;">
                        <?= number_format($log['size'] / 1024, 1) ?> KB
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="margin-top:24px;color:var(--text-muted);font-size:0.85rem;">
    Sorgente: GitHub <code><?= Updater::GITHUB_OWNER ?>/<?= Updater::GITHUB_REPO ?></code>.
    Cache del check: 6 ore. I backup pre-aggiornamento sono in <code>data/backups/</code>.
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
