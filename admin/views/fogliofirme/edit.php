<?php
$isNew = empty($sheet);
$pageTitle = $isNew ? 'Nuovo evento' : 'Modifica: ' . ($sheet['name'] ?? '');
$activeMenu = 'fogliofirme';
ob_start();

$name = $sheet['name'] ?? '';
$slug = $sheet['slug'] ?? '';
$description = $sheet['description'] ?? '';
$eventDate = $sheet['event_date'] ?? '';
$accessCode = $sheet['access_code'] ?? '';
$sendEmail = !empty($sheet['send_email']);
$emailText = $sheet['email_text'] ?? '';
$autoClose = $sheet['auto_close_at'] ?? '';
$status = $sheet['status'] ?? 'open';
$publicUrl = !$isNew ? ocms_absolute_url('/firma/' . $slug) : '';
?>

<div class="page-header">
    <h1><?= $isNew ? 'Nuovo evento' : 'Modifica evento' ?></h1>
    <a href="<?= ocms_base_url() ?>/admin/fogliofirme" class="btn btn-secondary">&larr; Torna all'elenco</a>
</div>

<?php foreach (ocms_flash_get() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : 'success' ?>"><?= ocms_escape($f['message']) ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">
<div class="card">
    <form method="POST" action="<?= ocms_base_url() ?>/admin/fogliofirme/save">
        <?= ocms_csrf_field() ?>
        <input type="hidden" name="original_slug" value="<?= ocms_escape($slug) ?>">

        <div class="form-group">
            <label for="name">Nome evento *</label>
            <input type="text" id="name" name="name" class="form-input" required maxlength="200"
                   value="<?= ocms_escape($name) ?>" placeholder="Es. Collegio docenti 20 aprile 2026">
        </div>

        <div class="form-group">
            <label for="slug">Slug URL</label>
            <input type="text" id="slug" name="slug" class="form-input" maxlength="120"
                   value="<?= ocms_escape($slug) ?>" placeholder="Lascia vuoto per auto-generare">
            <small style="color:var(--text-muted);">URL pubblico: <code><?= ocms_escape(ocms_absolute_url('/firma/')) ?><strong><?= ocms_escape($slug ?: '[slug]') ?></strong></code></small>
        </div>

        <div class="form-group">
            <label for="description">Descrizione</label>
            <textarea id="description" name="description" class="form-input" rows="3"
                      placeholder="Cosa vedranno i firmatari sopra il form"><?= ocms_escape($description) ?></textarea>
        </div>

        <div class="form-group">
            <label for="event_date">Data evento</label>
            <input type="date" id="event_date" name="event_date" class="form-input" value="<?= ocms_escape($eventDate) ?>">
        </div>

        <div class="form-group">
            <label for="access_code">Codice di accesso (opzionale)</label>
            <input type="text" id="access_code" name="access_code" class="form-input" maxlength="60"
                   value="<?= ocms_escape($accessCode) ?>" placeholder="Lascia vuoto per firma libera">
            <small style="color:var(--text-muted);">Se presente, i partecipanti dovranno inserirlo prima di firmare. Lo generi e lo comunichi tu all'apertura dell'evento.</small>
        </div>

        <div class="form-group">
            <label for="auto_close_at">Chiusura automatica (opzionale)</label>
            <input type="datetime-local" id="auto_close_at" name="auto_close_at" class="form-input"
                   value="<?= ocms_escape($autoClose) ?>">
            <small style="color:var(--text-muted);">Dopo questa data/ora il form non accetta più firme.</small>
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="send_email" value="1" <?= $sendEmail ? 'checked' : '' ?>>
                <span>Invia email di conferma al firmatario</span>
            </label>
        </div>

        <div class="form-group">
            <label for="email_text">Testo aggiuntivo nell'email (opzionale)</label>
            <textarea id="email_text" name="email_text" class="form-input" rows="3"
                      placeholder="Es. indicazioni per raggiungere la sede, link al webinar…"><?= ocms_escape($emailText) ?></textarea>
        </div>

        <div class="form-group">
            <label for="status">Stato</label>
            <select id="status" name="status" class="form-input">
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Aperto (accetta firme)</option>
                <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Chiuso</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Salva</button>
    </form>
</div>

<?php if (!$isNew): ?>
<div class="card">
    <h3 style="margin-top:0;">QR code & link</h3>
    <p style="font-size:0.85rem;color:var(--text-muted);">Condividi o proietta questi elementi per raccogliere le firme.</p>
    <div style="text-align:center;margin:12px 0;">
        <img src="<?= ocms_escape(ocms_fogliofirme_qr_url($publicUrl, 260)) ?>" alt="QR code" style="max-width:100%;border:1px solid var(--border);border-radius:8px;padding:8px;background:#fff;">
    </div>
    <div style="margin-bottom:8px;">
        <label style="font-size:0.8rem;color:var(--text-muted);">Link pubblico</label>
        <input type="text" class="form-input" readonly value="<?= ocms_escape($publicUrl) ?>" onclick="this.select();document.execCommand('copy');" style="font-family:monospace;font-size:0.85rem;cursor:copy;">
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= ocms_base_url() ?>/firma/<?= ocms_escape($slug) ?>/show?k=<?= ocms_signed_token('firma-show:' . $slug) ?>" target="_blank" class="btn btn-primary btn-sm">Modalità proiezione</a>
        <a href="<?= ocms_escape(ocms_fogliofirme_qr_url($publicUrl, 800)) ?>" target="_blank" download="qr-<?= ocms_escape($slug) ?>.png" class="btn btn-secondary btn-sm">Scarica QR</a>
        <a href="<?= ocms_base_url() ?>/admin/fogliofirme/firme/<?= ocms_escape($slug) ?>" class="btn btn-secondary btn-sm">Vedi firme (<?= count($sheet['firme'] ?? []) ?>)</a>
    </div>
</div>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
