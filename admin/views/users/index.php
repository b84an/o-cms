<?php
$pageTitle = 'Utenti';
$activeMenu = 'users';
ob_start();
?>

<div class="page-header">
    <h1>Utenti</h1>
    <a href="<?= ocms_base_url() ?>/admin/users/new" class="btn btn-primary">+ Nuovo Utente</a>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr><th>Utente</th><th>Email</th><th>Ruolo</th><th>Ultimo accesso</th><th>Azioni</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#a78bfa);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;flex-shrink:0;">
                                <?= strtoupper(mb_substr($u['display_name'] ?? $u['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;"><?= ocms_escape($u['display_name'] ?? $u['username']) ?></div>
                                <div style="font-size:0.8rem;color:var(--text-muted);">@<?= ocms_escape($u['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-muted);"><?= ocms_escape($u['email'] ?? '') ?></td>
                    <td>
                        <?php $labels = Auth::getRoleLabels(); ?>
                        <span class="badge badge-published"><?= ocms_escape($labels[$u['role']] ?? ucfirst($u['role'])) ?></span>
                        <?php if (empty($u['active'])): ?>
                            <span class="badge badge-draft" style="margin-left:4px;">Non attivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:0.85rem;">
                        <?= $u['last_login'] ? ocms_format_date($u['last_login']) : 'Mai' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:8px;">
                            <a href="<?= ocms_base_url() ?>/admin/users/edit/<?= ocms_escape($u['username']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                            <?php if ($u['username'] !== $app->auth->user()['username']): ?>
                            <form method="POST" action="<?= ocms_base_url() ?>/admin/users/delete/<?= ocms_escape($u['username']) ?>"
                                  onsubmit="return confirm('Eliminare questo utente?');" style="display:inline;">
                                <?= ocms_csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
