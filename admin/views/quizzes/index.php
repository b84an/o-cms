<?php
$pageTitle = 'Quiz';
$activeMenu = 'quizzes';
ob_start();
?>

<div class="page-header">
    <h1>Quiz</h1>
    <a href="<?= ocms_base_url() ?>/admin/quizzes/new" class="btn btn-primary">+ Nuovo Quiz</a>
</div>

<?php if (empty($quizzes)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <p style="font-size:2rem;margin-bottom:12px;">&#10004;</p>
        <h3>Nessun quiz</h3>
        <p style="color:var(--text-muted);margin-bottom:20px;">Crea il tuo primo quiz con domande a risposta multipla.</p>
        <a href="<?= ocms_base_url() ?>/admin/quizzes/new" class="btn btn-primary">Crea Quiz</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Domande</th>
                        <th>Risultati</th>
                        <th>Stato</th>
                        <th>Creato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $q):
                    $resultCount = $app->storage->count('quiz-results', fn($r) => ($r['quiz_slug'] ?? '') === $q['slug']);
                ?>
                    <tr>
                        <td>
                            <a href="<?= ocms_base_url() ?>/admin/quizzes/edit/<?= ocms_escape($q['slug']) ?>" style="font-weight:600;color:var(--text);">
                                <?= ocms_escape($q['title']) ?>
                            </a>
                            <div style="font-size:0.75rem;color:var(--text-muted);">/quiz/<?= ocms_escape($q['slug']) ?></div>
                        </td>
                        <td style="font-family:monospace;font-size:0.85rem;"><?= count($q['questions'] ?? []) ?></td>
                        <td>
                            <?php if ($resultCount > 0): ?>
                                <a href="<?= ocms_base_url() ?>/admin/quizzes/results/<?= ocms_escape($q['slug']) ?>" style="font-family:monospace;font-size:0.85rem;color:var(--primary-light);">
                                    <?= $resultCount ?> risposte
                                </a>
                            <?php else: ?>
                                <span style="font-family:monospace;font-size:0.85rem;color:var(--text-muted);">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= ($q['status'] ?? 'draft') === 'published' ? 'published' : 'draft' ?>">
                                <?= ($q['status'] ?? 'draft') === 'published' ? 'Pubblicato' : 'Bozza' ?>
                            </span>
                            <?php if (isset($q['settings']['active']) && !$q['settings']['active']): ?>
                                <span class="badge badge-draft" style="margin-left:4px;">Non attivo</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem;color:var(--text-muted);"><?= ocms_format_date($q['created_at'], 'd/m/Y') ?></td>
                        <td>
                            <div style="display:flex;gap:8px;justify-content:flex-end;">
                                <?php if (($q['status'] ?? '') === 'published'): ?>
                                <a href="<?= ocms_base_url() ?>/quiz/<?= ocms_escape($q['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Visualizza">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php endif; ?>
                                <a href="<?= ocms_base_url() ?>/admin/quizzes/results/<?= ocms_escape($q['slug']) ?>" class="btn btn-secondary btn-sm" title="Risultati">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                </a>
                                <a href="<?= ocms_base_url() ?>/admin/quizzes/edit/<?= ocms_escape($q['slug']) ?>" class="btn btn-secondary btn-sm">Modifica</a>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/quizzes/delete/<?= ocms_escape($q['slug']) ?>" onsubmit="return confirm('Eliminare questo quiz e tutti i risultati?');" style="margin:0;">
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
