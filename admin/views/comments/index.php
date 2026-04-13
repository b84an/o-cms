<?php
$pageTitle = 'Commenti';
$activeMenu = 'comments';

$filter = $filter ?? 'pending';
$pendingCount = $app->storage->count('comments', fn($c) => ($c['status'] ?? '') === 'pending');

ob_start();
?>

<div class="page-header">
    <h1>Commenti <?php if ($pendingCount > 0): ?><span style="background:var(--warning);color:#000;font-size:0.75rem;padding:2px 8px;border-radius:10px;font-weight:700;vertical-align:middle;margin-left:8px;"><?= $pendingCount ?></span><?php endif; ?></h1>
    <div style="display:flex;gap:8px;">
        <a href="<?= ocms_base_url() ?>/admin/comments?status=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">In attesa</a>
        <a href="<?= ocms_base_url() ?>/admin/comments?status=approved" class="btn <?= $filter === 'approved' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Approvati</a>
        <a href="<?= ocms_base_url() ?>/admin/comments?status=rejected" class="btn <?= $filter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Rifiutati</a>
        <a href="<?= ocms_base_url() ?>/admin/comments?status=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tutti</a>
    </div>
</div>

<?php if (empty($comments)): ?>
    <div class="empty-state card">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>Nessun commento</h3>
        <p>Non ci sono commenti <?= $filter !== 'all' ? 'con stato "' . ocms_escape($filter) . '"' : '' ?>.</p>
    </div>
<?php else: ?>
    <div id="comments-list">
        <?php foreach ($comments as $c):
            $gravatarHash = md5(strtolower(trim($c['author_email'] ?? '')));
            $statusClass = match($c['status'] ?? 'pending') {
                'approved' => 'badge-published',
                'rejected' => 'badge-draft',
                default => 'badge',
            };
            $statusLabel = match($c['status'] ?? 'pending') {
                'approved' => 'Approvato',
                'rejected' => 'Rifiutato',
                default => 'In attesa',
            };
            $statusBg = match($c['status'] ?? 'pending') {
                'approved' => 'rgba(34,197,94,0.12)',
                'rejected' => 'rgba(239,68,68,0.12)',
                default => 'rgba(245,158,11,0.12)',
            };
            $statusColor = match($c['status'] ?? 'pending') {
                'approved' => 'var(--success)',
                'rejected' => 'var(--error)',
                default => 'var(--warning)',
            };
        ?>
        <div class="card" style="margin-bottom:16px;transition:opacity .3s;" id="comment-<?= ocms_escape($c['id']) ?>">
            <div style="display:flex;gap:16px;">
                <!-- Gravatar -->
                <img src="https://www.gravatar.com/avatar/<?= $gravatarHash ?>?s=48&d=mp" alt=""
                     style="width:48px;height:48px;border-radius:50%;flex-shrink:0;border:2px solid var(--border);">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                        <strong style="font-size:0.95rem;"><?= ocms_escape($c['author_name']) ?></strong>
                        <?php if (!empty($c['author_email'])): ?>
                            <span style="font-size:0.75rem;color:var(--text-muted);"><?= ocms_escape($c['author_email']) ?></span>
                        <?php endif; ?>
                        <span style="background:<?= $statusBg ?>;color:<?= $statusColor ?>;padding:2px 8px;border-radius:6px;font-size:0.7rem;font-weight:600;"><?= $statusLabel ?></span>
                        <?php if (!empty($c['parent_id'])): ?>
                            <span style="font-size:0.7rem;color:var(--text-muted);font-style:italic;">risposta</span>
                        <?php endif; ?>
                    </div>
                    <p style="font-size:0.9rem;color:var(--text-muted);line-height:1.6;margin-bottom:10px;"><?= nl2br(ocms_escape($c['body'])) ?></p>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span style="font-size:0.75rem;color:var(--text-muted);"><?= ocms_format_date($c['created_at'], 'd/m/Y H:i') ?></span>
                        <a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($c['article_slug']) ?>" style="font-size:0.75rem;">
                            <?= ocms_escape($c['article_slug']) ?>
                        </a>
                        <span style="flex:1;"></span>
                        <?php if (($c['status'] ?? '') !== 'approved'): ?>
                        <button class="btn btn-sm" style="background:rgba(34,197,94,0.1);color:var(--success);border:1px solid rgba(34,197,94,0.3);padding:4px 12px;"
                                onclick="commentAction('approve','<?= ocms_escape($c['id']) ?>')">Approva</button>
                        <?php endif; ?>
                        <?php if (($c['status'] ?? '') !== 'rejected'): ?>
                        <button class="btn btn-sm" style="background:rgba(245,158,11,0.1);color:var(--warning);border:1px solid rgba(245,158,11,0.3);padding:4px 12px;"
                                onclick="commentAction('reject','<?= ocms_escape($c['id']) ?>')">Rifiuta</button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-danger" style="padding:4px 12px;"
                                onclick="commentAction('delete','<?= ocms_escape($c['id']) ?>')">Elimina</button>
                        <button class="btn btn-sm" style="background:rgba(59,130,246,0.1);color:var(--primary);border:1px solid rgba(59,130,246,0.3);padding:4px 12px;"
                                onclick="toggleReplyForm('<?= ocms_escape($c['id']) ?>')">Rispondi</button>
                    </div>
                    <!-- Form risposta admin -->
                    <div id="reply-form-<?= ocms_escape($c['id']) ?>" style="display:none;margin-top:12px;padding:12px;background:var(--bg-card-alt, rgba(255,255,255,0.03));border:1px solid var(--border);border-radius:8px;">
                        <textarea id="reply-body-<?= ocms_escape($c['id']) ?>" rows="3" placeholder="Scrivi la tua risposta..."
                            style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text);font-family:inherit;font-size:0.85rem;resize:vertical;margin-bottom:8px;"></textarea>
                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button class="btn btn-sm btn-secondary" style="padding:4px 12px;" onclick="toggleReplyForm('<?= ocms_escape($c['id']) ?>')">Annulla</button>
                            <button class="btn btn-sm btn-primary" style="padding:4px 12px;" onclick="sendReply('<?= ocms_escape($c['id']) ?>')">Invia risposta</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); ob_start(); ?>
<script>
const BASE = '<?= ocms_base_url() ?>';
const CSRF = '<?= ocms_escape(ocms_csrf_token()) ?>';

function toggleReplyForm(id) {
    const form = document.getElementById('reply-form-' + id);
    if (form.style.display === 'none') {
        document.querySelectorAll('[id^="reply-form-"]').forEach(f => f.style.display = 'none');
        form.style.display = 'block';
        form.querySelector('textarea').focus();
    } else {
        form.style.display = 'none';
    }
}

async function sendReply(parentId) {
    const textarea = document.getElementById('reply-body-' + parentId);
    const body = textarea.value.trim();
    if (!body) { textarea.focus(); return; }

    const btn = textarea.closest('[id^="reply-form-"]').querySelector('.btn-primary');
    btn.disabled = true;
    btn.textContent = 'Invio...';

    try {
        const res = await fetch(BASE + '/admin/comments/reply/' + parentId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf_token: CSRF, body: body })
        });
        const data = await res.json();
        if (data.success) {
            textarea.value = '';
            document.getElementById('reply-form-' + parentId).style.display = 'none';
            // Mostra conferma inline
            const card = document.getElementById('comment-' + parentId);
            const notice = document.createElement('div');
            notice.style.cssText = 'margin-top:8px;padding:8px 12px;background:rgba(34,197,94,0.1);color:var(--success);border-radius:6px;font-size:0.8rem;';
            notice.textContent = '✓ Risposta pubblicata';
            card.querySelector('[style*="flex:1"]').appendChild(notice);
            setTimeout(() => notice.remove(), 3000);
        } else {
            alert(data.error || 'Errore');
        }
    } catch (e) {
        alert('Errore nella richiesta');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Invia risposta';
    }
}

async function commentAction(action, id) {
    if (action === 'delete' && !confirm('Eliminare definitivamente questo commento?')) return;

    const el = document.getElementById('comment-' + id);
    el.style.opacity = '0.5';

    try {
        const res = await fetch(BASE + '/admin/comments/' + action + '/' + id, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf_token: CSRF })
        });
        const data = await res.json();
        if (data.success) {
            el.style.transition = 'all .3s';
            el.style.maxHeight = el.scrollHeight + 'px';
            requestAnimationFrame(() => {
                el.style.opacity = '0';
                el.style.maxHeight = '0';
                el.style.marginBottom = '0';
                el.style.padding = '0';
                el.style.overflow = 'hidden';
            });
            setTimeout(() => el.remove(), 350);
        }
    } catch (e) {
        el.style.opacity = '1';
        alert('Errore nella richiesta');
    }
}
</script>
<?php
$footerExtra = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
