<?php
$pageTitle = 'Risultati: ' . ($quiz['title'] ?? 'Quiz');
$activeMenu = 'quizzes';

// Calcola statistiche
$totalResults = count($results);
$scores = array_column($results, 'score');
$mean = $totalResults ? round(array_sum($scores) / $totalResults, 1) : 0;
sort($scores);
$median = 0;
if ($totalResults > 0) {
    $mid = intdiv($totalResults, 2);
    $median = $totalResults % 2 === 0 ? round(($scores[$mid - 1] + $scores[$mid]) / 2, 1) : $scores[$mid];
}
$passed = count(array_filter($scores, fn($s) => $s >= 60));
$failed = $totalResults - $passed;

ob_start();
?>

<div class="page-header">
    <h1>Risultati: <?= ocms_escape($quiz['title']) ?></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= ocms_base_url() ?>/admin/quizzes/export-csv/<?= ocms_escape($quiz['slug']) ?>" class="btn btn-secondary">Esporta CSV</a>
        <a href="<?= ocms_base_url() ?>/admin/quizzes/edit/<?= ocms_escape($quiz['slug']) ?>" class="btn btn-secondary">Modifica Quiz</a>
        <a href="<?= ocms_base_url() ?>/admin/quizzes" class="btn btn-secondary">&larr; Lista Quiz</a>
    </div>
</div>

<!-- Statistiche -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;">
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--primary-light);"><?= $totalResults ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Risposte</div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--primary-light);"><?= $mean ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Media</div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--primary-light);"><?= $median ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Mediana</div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--success,#22c55e);"><?= $passed ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Sufficienti</div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:1.8rem;font-weight:800;color:var(--danger,#ef4444);"><?= $failed ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Insufficienti</div>
    </div>
</div>

<?php if (empty($results)): ?>
    <div class="card" style="text-align:center;padding:60px 20px;">
        <h3>Nessun risultato</h3>
        <p style="color:var(--text-muted);">Nessuno studente ha ancora completato questo quiz.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th class="sortable" data-col="0" data-type="string">Studente <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="1" data-type="string">Classe <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="2" data-type="string">Email <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="3" data-type="number">Voto <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="4" data-type="number">Corrette <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="5" data-type="number">Errate <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="6" data-type="number">Saltate <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="7" data-type="number">Tempo <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="8" data-type="string">Data <span class="sort-icon"></span></th>
                        <th class="sortable" data-col="9" data-type="string">Inviato <span class="sort-icon"></span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $r):
                    $scoreClass = ($r['score'] ?? 0) >= 60 ? 'published' : 'draft';
                    $mins = intdiv($r['time_seconds'] ?? 0, 60);
                    $secs = ($r['time_seconds'] ?? 0) % 60;
                ?>
                    <tr class="data-row" data-detail-id="<?= ocms_escape($r['id']) ?>">
                        <td style="font-weight:600;"><?= ocms_escape($r['student']['name'] ?? 'Anonimo') ?></td>
                        <td style="font-size:0.85rem;"><?= ocms_escape($r['student']['class'] ?? '') ?></td>
                        <td style="font-size:0.85rem;"><?= ocms_escape($r['student']['email'] ?? '') ?></td>
                        <td data-sort-value="<?= round($r['score'] ?? 0, 1) ?>">
                            <span class="badge badge-<?= $scoreClass ?>" style="font-size:0.9rem;font-weight:700;">
                                <?= round($r['score'] ?? 0, 1) ?>
                            </span>
                        </td>
                        <td style="font-family:monospace;color:var(--success,#22c55e);" data-sort-value="<?= $r['correct_count'] ?? 0 ?>"><?= $r['correct_count'] ?? 0 ?></td>
                        <td style="font-family:monospace;color:var(--danger,#ef4444);" data-sort-value="<?= $r['wrong_count'] ?? 0 ?>"><?= $r['wrong_count'] ?? 0 ?></td>
                        <td style="font-family:monospace;color:var(--text-muted);" data-sort-value="<?= $r['skipped_count'] ?? 0 ?>"><?= $r['skipped_count'] ?? 0 ?></td>
                        <td style="font-family:monospace;font-size:0.85rem;" data-sort-value="<?= $r['time_seconds'] ?? 0 ?>"><?= sprintf('%d:%02d', $mins, $secs) ?></td>
                        <td style="font-size:0.85rem;color:var(--text-muted);" data-sort-value="<?= ocms_escape($r['submitted_at'] ?? '') ?>"><?= ocms_format_date($r['submitted_at'] ?? '', 'd/m/Y H:i') ?></td>
                        <td style="font-size:0.85rem;" data-sort-value="<?= ocms_escape($r['email_sent_at'] ?? '') ?>">
                            <?php if (!empty($r['email_sent_at'])): ?>
                                <span style="color:var(--success,#22c55e);" title="<?= ocms_escape(ocms_format_date($r['email_sent_at'], 'd/m/Y H:i')) ?>">
                                    &#9989; <?= ocms_format_date($r['email_sent_at'], 'd/m/Y H:i') ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <button type="button" class="btn btn-secondary btn-sm toggle-detail" data-id="<?= ocms_escape($r['id']) ?>">Dettagli</button>
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/quizzes/delete-result/<?= ocms_escape($r['id']) ?>" onsubmit="return confirm('Eliminare questo risultato?');" style="margin:0;">
                                    <?= ocms_csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm">&times;</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr class="detail-row" id="detail-<?= ocms_escape($r['id']) ?>" style="display:none;">
                        <td colspan="11" style="padding:16px;background:var(--bg);">
                            <div style="display:grid;gap:8px;max-width:800px;">
                                <?php
                                $questions = $quiz['questions'] ?? [];
                                $answers = $r['answers'] ?? [];
                                foreach ($questions as $qi => $question):
                                    $studentAnswer = $answers[$qi] ?? null;
                                    $isCorrect = $studentAnswer === $question['correct'];
                                    $isSkipped = $studentAnswer === null || $studentAnswer === '';
                                    $statusColor = $isSkipped ? 'var(--text-muted)' : ($isCorrect ? 'var(--success,#22c55e)' : 'var(--danger,#ef4444)');
                                    $statusLabel = $isSkipped ? 'Saltata' : ($isCorrect ? 'Corretta' : 'Errata');
                                ?>
                                <div style="display:grid;grid-template-columns:30px 1fr 1fr 1fr;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:0.85rem;">
                                    <span style="font-weight:700;color:var(--text-muted);"><?= $qi + 1 ?></span>
                                    <span><?= ocms_escape($question['text']) ?></span>
                                    <span style="color:<?= $statusColor ?>;"><?= ocms_escape($studentAnswer ?: '—') ?></span>
                                    <span style="display:flex;justify-content:space-between;align-items:center;">
                                        <span style="color:var(--success,#22c55e);font-size:0.8rem;"><?= ocms_escape($question['correct']) ?></span>
                                        <span style="font-size:0.72rem;font-weight:600;color:<?= $statusColor ?>;"><?= $statusLabel ?></span>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($r['student']['email'])): ?>
                            <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);">
                                <form method="POST" action="<?= ocms_base_url() ?>/admin/quizzes/send-result/<?= ocms_escape($r['id']) ?>" onsubmit="return confirm('Inviare il report dettagliato a <?= ocms_escape($r['student']['email']) ?>?');" style="margin:0;display:inline;">
                                    <?= ocms_csrf_field() ?>
                                    <input type="hidden" name="quiz_slug" value="<?= ocms_escape($quiz['slug']) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                        Invia risultato a <?= ocms_escape($r['student']['email']) ?>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
.sortable { cursor:pointer; user-select:none; white-space:nowrap; }
.sortable:hover { color:var(--primary-light); }
.sort-icon::after { content:''; margin-left:4px; font-size:0.7rem; }
.sortable.asc .sort-icon::after { content:'\25B2'; }
.sortable.desc .sort-icon::after { content:'\25BC'; }
</style>
<script>
document.querySelectorAll('.toggle-detail').forEach(btn => {
    btn.addEventListener('click', function() {
        const row = document.getElementById('detail-' + this.dataset.id);
        if (row) {
            row.style.display = row.style.display === 'none' ? '' : 'none';
            this.textContent = row.style.display === 'none' ? 'Dettagli' : 'Chiudi';
        }
    });
});

// Ordinamento tabella
document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const col = parseInt(this.dataset.col);
        const type = this.dataset.type;
        const isAsc = this.classList.contains('asc');
        const dir = isAsc ? -1 : 1;

        // Reset icone
        table.querySelectorAll('.sortable').forEach(h => h.classList.remove('asc','desc'));
        this.classList.add(isAsc ? 'desc' : 'asc');

        // Raccogli coppie data-row + detail-row
        const pairs = [];
        const dataRows = tbody.querySelectorAll('tr.data-row');
        dataRows.forEach(row => {
            const detailId = row.dataset.detailId;
            const detailRow = document.getElementById('detail-' + detailId);
            pairs.push({ dataRow: row, detailRow: detailRow });
        });

        pairs.sort((a, b) => {
            const cellA = a.dataRow.children[col];
            const cellB = b.dataRow.children[col];
            let valA = cellA.dataset.sortValue !== undefined ? cellA.dataset.sortValue : cellA.textContent.trim();
            let valB = cellB.dataset.sortValue !== undefined ? cellB.dataset.sortValue : cellB.textContent.trim();

            if (type === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
                return (valA - valB) * dir;
            }
            return valA.localeCompare(valB, 'it') * dir;
        });

        pairs.forEach(p => {
            tbody.appendChild(p.dataRow);
            if (p.detailRow) tbody.appendChild(p.detailRow);
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
