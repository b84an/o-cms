<?php
$pageTitle = $quiz ? 'Modifica Quiz' : 'Nuovo Quiz';
$activeMenu = 'quizzes';
ob_start();
?>

<form method="POST" action="<?= ocms_base_url() ?>/admin/quizzes/save" id="quiz-form">

<div class="page-header">
    <h1><?= $quiz ? 'Modifica Quiz' : 'Nuovo Quiz' ?></h1>
    <div style="display:flex;gap:10px;">
        <a href="<?= ocms_base_url() ?>/admin/quizzes" class="btn btn-secondary">&larr; Torna alla lista</a>
        <button type="submit" class="btn btn-primary">Salva Quiz</button>
    </div>
</div>
    <?= ocms_csrf_field() ?>
    <input type="hidden" name="id" value="<?= ocms_escape($quiz['id'] ?? '') ?>">
    <input type="hidden" name="original_slug" value="<?= ocms_escape($quiz['slug'] ?? '') ?>">

    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">
        <!-- Colonna principale -->
        <div>
            <div class="card" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label">Titolo del Quiz</label>
                    <input type="text" name="title" class="form-input" value="<?= ocms_escape($quiz['title'] ?? '') ?>" required placeholder="es. Verifica Reti e Protocolli">
                </div>

                <div class="form-group">
                    <label class="form-label">Slug (URL)</label>
                    <input type="text" name="slug" class="form-input" value="<?= ocms_escape($quiz['slug'] ?? '') ?>" placeholder="generato automaticamente dal titolo">
                </div>

                <div class="form-group">
                    <label class="form-label">Descrizione</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Descrizione opzionale del quiz"><?= ocms_escape($quiz['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Importa da CSV -->
            <div class="card" style="padding:24px;margin-top:20px;">
                <h3 style="margin-bottom:16px;">Importa domande da CSV</h3>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:12px;">
                    Formato: <code>Domanda;Risposta errata 1;Risposta errata 2;Risposta errata 3;Risposta corretta</code><br>
                    Delimitatori supportati: <code>;</code> <code>,</code> <code>TAB</code> <code>|</code>
                </p>
                <textarea id="csv-input" class="form-input" rows="6" placeholder="Incolla qui il CSV con le domande..."></textarea>
                <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
                    <button type="button" id="parse-csv-btn" class="btn btn-secondary">Importa CSV</button>
                    <span id="csv-status" style="font-size:0.85rem;color:var(--text-muted);"></span>
                </div>
            </div>

            <!-- Domande -->
            <div class="card" style="padding:24px;margin-top:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3>Domande <span id="question-count" style="font-size:0.85rem;color:var(--text-muted);font-weight:400;">(<?= count($quiz['questions'] ?? []) ?>)</span></h3>
                    <button type="button" id="add-question-btn" class="btn btn-primary btn-sm">+ Aggiungi domanda</button>
                </div>

                <div id="questions-container">
                    <?php if (!empty($quiz['questions'])): ?>
                        <?php foreach ($quiz['questions'] as $i => $q): ?>
                        <div class="quiz-question-block" data-index="<?= $i ?>">
                            <div class="qq-header">
                                <span class="qq-number">#<?= $i + 1 ?></span>
                                <button type="button" class="btn btn-danger btn-sm qq-remove" title="Rimuovi">&times;</button>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Domanda</label>
                                <input type="text" name="questions[<?= $i ?>][text]" class="form-input qq-text" value="<?= ocms_escape($q['text']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="color:var(--success,#22c55e);">Risposta corretta</label>
                                <input type="text" name="questions[<?= $i ?>][correct]" class="form-input qq-correct" value="<?= ocms_escape($q['correct']) ?>" required style="border-color:rgba(34,197,94,0.3);">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Risposte errate</label>
                                <?php foreach ($q['wrong'] as $wi => $w): ?>
                                <input type="text" name="questions[<?= $i ?>][wrong][]" class="form-input qq-wrong" value="<?= ocms_escape($w) ?>" required style="margin-bottom:6px;border-color:rgba(239,68,68,0.2);">
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="card" style="padding:20px;">
                <h3 style="margin-bottom:16px;">Pubblicazione</h3>
                <div class="form-group">
                    <label class="form-label">Stato</label>
                    <select name="status" class="form-input">
                        <option value="draft" <?= ($quiz['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Bozza</option>
                        <option value="published" <?= ($quiz['status'] ?? '') === 'published' ? 'selected' : '' ?>>Pubblicato</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Salva Quiz</button>
            </div>

            <div class="card" style="padding:20px;margin-top:16px;">
                <h3 style="margin-bottom:16px;">Impostazioni</h3>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="settings[penalty_mode]" value="1" <?= !empty($quiz['settings']['penalty_mode']) ? 'checked' : '' ?>>
                        Modalit&agrave; con penalit&agrave;
                    </label>
                    <p style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">Corretta +2, Errata -1, Saltata 0</p>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="settings[randomize]" value="1" <?= ($quiz['settings']['randomize'] ?? true) ? 'checked' : '' ?>>
                        Randomizza domande e risposte
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Tempo limite (minuti, 0 = nessuno)</label>
                    <input type="number" name="settings[time_limit]" class="form-input" value="<?= intval($quiz['settings']['time_limit'] ?? 0) ?>" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="settings[require_auth]" value="1" <?= !empty($quiz['settings']['require_auth']) ? 'checked' : '' ?>>
                        Richiedi autenticazione
                    </label>
                    <p style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">Solo utenti registrati O-CMS possono compilare</p>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label class="form-label">
                        <input type="checkbox" name="settings[active]" value="1" <?= ($quiz['settings']['active'] ?? true) ? 'checked' : '' ?>>
                        Quiz attivo
                    </label>
                    <p style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">Se disattivato, gli studenti vedranno "Quiz non attivo"</p>
                </div>
            </div>

            <div class="card" style="padding:20px;margin-top:16px;">
                <h3 style="margin-bottom:12px;">Codice di Accesso</h3>
                <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:10px;">
                    Gli studenti devono inserire questo codice per accedere al quiz (anche senza login).
                </p>
                <input type="text" name="settings[access_code]" class="form-input" id="access-code-field"
                       value="<?= ocms_escape($quiz['settings']['access_code'] ?? '') ?>"
                       readonly
                       style="text-align:center;font-size:1.4rem;letter-spacing:0.3em;font-weight:700;font-family:monospace;">
                <button type="button" class="btn btn-secondary btn-sm" style="width:100%;margin-top:8px;"
                        onclick="document.getElementById('access-code-field').value = String(Math.floor(Math.random()*10000)).padStart(4,'0');">
                    Rigenera codice
                </button>
            </div>

            <?php if ($quiz): ?>
            <div class="card" style="padding:20px;margin-top:16px;">
                <h3 style="margin-bottom:12px;">Link</h3>
                <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;">Link diretto al quiz:</p>
                <input type="text" class="form-input" value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ocms_base_url() ?>/quiz/<?= ocms_escape($quiz['slug']) ?>" readonly onclick="this.select();" style="font-size:0.8rem;">
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<style>
.quiz-question-block{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;position:relative;}
.qq-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.qq-number{font-weight:700;font-size:0.9rem;color:var(--primary-light);}
.qq-remove{font-size:1.2rem;line-height:1;padding:2px 8px;}
</style>

<script>
(function() {
    const container = document.getElementById('questions-container');
    const countEl = document.getElementById('question-count');

    function updateCount() {
        const n = container.querySelectorAll('.quiz-question-block').length;
        countEl.textContent = '(' + n + ')';
    }

    function reindex() {
        container.querySelectorAll('.quiz-question-block').forEach((block, i) => {
            block.dataset.index = i;
            block.querySelector('.qq-number').textContent = '#' + (i + 1);
            block.querySelector('.qq-text').name = 'questions[' + i + '][text]';
            block.querySelector('.qq-correct').name = 'questions[' + i + '][correct]';
            block.querySelectorAll('.qq-wrong').forEach(w => {
                w.name = 'questions[' + i + '][wrong][]';
            });
        });
        updateCount();
    }

    function createQuestionBlock(text, correct, wrong) {
        const i = container.querySelectorAll('.quiz-question-block').length;
        const div = document.createElement('div');
        div.className = 'quiz-question-block';
        div.dataset.index = i;
        const esc = s => {
            const t = document.createElement('span');
            t.textContent = s;
            return t.innerHTML;
        };
        div.innerHTML = `
            <div class="qq-header">
                <span class="qq-number">#${i + 1}</span>
                <button type="button" class="btn btn-danger btn-sm qq-remove" title="Rimuovi">&times;</button>
            </div>
            <div class="form-group">
                <label class="form-label">Domanda</label>
                <input type="text" name="questions[${i}][text]" class="form-input qq-text" value="${esc(text)}" required>
            </div>
            <div class="form-group">
                <label class="form-label" style="color:var(--success,#22c55e);">Risposta corretta</label>
                <input type="text" name="questions[${i}][correct]" class="form-input qq-correct" value="${esc(correct)}" required style="border-color:rgba(34,197,94,0.3);">
            </div>
            <div class="form-group">
                <label class="form-label">Risposte errate</label>
                ${wrong.map(w => `<input type="text" name="questions[${i}][wrong][]" class="form-input qq-wrong" value="${esc(w)}" required style="margin-bottom:6px;border-color:rgba(239,68,68,0.2);">`).join('')}
            </div>
        `;
        container.appendChild(div);
        updateCount();
    }

    // Add question
    document.getElementById('add-question-btn').addEventListener('click', function() {
        createQuestionBlock('', '', ['', '', '']);
        container.lastElementChild.querySelector('.qq-text').focus();
    });

    // Remove question (delegated)
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('qq-remove')) {
            e.target.closest('.quiz-question-block').remove();
            reindex();
        }
    });

    // CSV Parser
    document.getElementById('parse-csv-btn').addEventListener('click', function() {
        const raw = document.getElementById('csv-input').value.trim();
        if (!raw) return;

        const statusEl = document.getElementById('csv-status');

        // Detect delimiter
        const delimiters = [';', ',', '\t', '|'];
        let bestDelim = ';', bestScore = 0;
        const sampleLines = raw.split('\n').slice(0, 20).filter(l => l.trim());
        for (const d of delimiters) {
            let score = 0;
            for (const line of sampleLines) {
                const parts = parseCSVLine(line, d);
                if (parts.length === 5) score++;
            }
            if (score > bestScore) { bestScore = score; bestDelim = d; }
        }

        const lines = raw.split('\n').filter(l => l.trim());
        let added = 0, errors = 0;

        for (let li = 0; li < lines.length; li++) {
            const parts = parseCSVLine(lines[li], bestDelim);
            if (parts.length !== 5) {
                // skip header or malformed
                if (li === 0 && parts.some(p => /domanda|question/i.test(p))) continue;
                errors++;
                continue;
            }
            const [text, w1, w2, w3, correct] = parts.map(s => s.trim());
            if (!text || !correct) { errors++; continue; }
            createQuestionBlock(text, correct, [w1, w2, w3]);
            added++;
        }

        reindex();
        statusEl.textContent = added + ' domande importate' + (errors ? ', ' + errors + ' righe ignorate' : '');
        statusEl.style.color = errors ? 'var(--warning)' : 'var(--success,#22c55e)';
        document.getElementById('csv-input').value = '';
    });

    function parseCSVLine(line, delimiter) {
        const parts = [];
        let current = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (inQuotes) {
                if (ch === '"') {
                    if (i + 1 < line.length && line[i + 1] === '"') {
                        current += '"'; i++;
                    } else {
                        inQuotes = false;
                    }
                } else {
                    current += ch;
                }
            } else {
                if (ch === '"') {
                    inQuotes = true;
                } else if (ch === delimiter) {
                    parts.push(current);
                    current = '';
                } else {
                    current += ch;
                }
            }
        }
        parts.push(current);
        return parts;
    }

    updateCount();
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
