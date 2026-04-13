<?php
$isNew = ($user === null);
$pageTitle = $isNew ? 'Nuovo Utente' : 'Modifica Utente';
$activeMenu = 'users';
$user = $user ?? ['username'=>'','email'=>'','display_name'=>'','role'=>'registered','active'=>true,'created_at'=>''];
ob_start();
?>

<div class="page-header">
    <h1><?= $pageTitle ?></h1>
    <a href="<?= ocms_base_url() ?>/admin/users" class="btn btn-secondary">← Utenti</a>
</div>

<div style="max-width:600px;">
    <form method="POST" action="<?= ocms_base_url() ?>/admin/users/save">
        <?= ocms_csrf_field() ?>
        <?php if ($isNew): ?><input type="hidden" name="is_new" value="1"><?php endif; ?>

        <div class="card" style="margin-bottom:20px;">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" class="form-input" value="<?= ocms_escape($user['username']) ?>"
                       <?= !$isNew ? 'readonly style="opacity:0.6;"' : '' ?> required pattern="[a-z0-9_-]+" placeholder="solo lettere minuscole, numeri, - _">
            </div>
            <div class="form-group">
                <label>Nome Visualizzato</label>
                <input type="text" name="display_name" class="form-input" value="<?= ocms_escape($user['display_name'] ?? '') ?>" placeholder="Nome completo">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-input" value="<?= ocms_escape($user['email'] ?? '') ?>" placeholder="email@esempio.com">
            </div>
            <div class="form-group">
                <label><?= $isNew ? 'Password *' : 'Nuova Password (lascia vuoto per non cambiare)' ?></label>
                <input type="password" name="password" class="form-input" <?= $isNew ? 'required' : '' ?> minlength="4" placeholder="<?= $isNew ? 'Minimo 4 caratteri' : 'Lascia vuoto per mantenere' ?>">
            </div>
            <div class="form-group">
                <label>Ruolo</label>
                <select name="role" class="form-select">
                    <?php foreach (Auth::getRoleLabels() as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($user['role'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (($user['role'] ?? '') === 'publisher'): ?>
                    <div class="form-hint" style="margin-top:4px;">Il ruolo Publisher deve essere validato da un Editor o superiore.</div>
                <?php endif; ?>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="feature-option" style="padding:10px 14px;margin:0;">
                    <input type="checkbox" name="active" <?= !empty($user['active']) ? 'checked' : '' ?>>
                    <div class="feat-info">
                        <span class="feat-title">Account Attivo</span>
                        <span class="feat-desc" style="font-size:0.75rem;color:var(--text-muted);">Deseleziona per disabilitare l'accesso</span>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Crea Utente' : 'Salva Modifiche' ?></button>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
