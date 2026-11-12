<?php
/**
 * Quiz interattivo.
 * Sicurezza: al browser NON arriva mai la risposta corretta. Le opzioni di ogni
 * domanda (corretta + errate) vengono mescolate qui sul server; il punteggio lo
 * calcola la rotta POST /quiz/{slug}/submit confrontando il testo dell'opzione
 * scelta con quello corretto, per indice di domanda nell'ordine originale.
 */
require_once __DIR__ . '/_bootstrap.php';

// Impostazioni: i quiz più vecchi le hanno al primo livello invece che in "settings"
$s = is_array($quiz['settings'] ?? null) ? $quiz['settings'] : [];
foreach (['time_limit', 'randomize', 'penalty_mode'] as $k) {
    if (!array_key_exists($k, $s) && array_key_exists($k, $quiz)) $s[$k] = $quiz[$k];
}
$timeLimitMin = max(0, (int) ($s['time_limit'] ?? 0));
$randomize    = ($s['randomize'] ?? true) !== false;
$penalty      = !empty($s['penalty_mode']);
$requireEmail = !empty($s['require_email'] ?? ($quiz['collect_email'] ?? false));
$active       = (bool) ($quizActive ?? true);
$user         = (isset($loggedUser) && is_array($loggedUser)) ? $loggedUser : null;
$userName     = $user ? (string) (($user['display_name'] ?? '') ?: ($user['username'] ?? '')) : '';

$shuffle = static function (array $a): array {
    for ($i = count($a) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
    }
    return $a;
};

$clientQuestions = [];
foreach (array_values(is_array($quiz['questions'] ?? null) ? $quiz['questions'] : []) as $i => $q) {
    if (!is_array($q)) continue;
    $opts = [];
    if (isset($q['correct']) && is_scalar($q['correct'])) $opts[] = (string) $q['correct'];
    foreach ((array) ($q['wrong'] ?? []) as $w) {
        if (is_scalar($w)) $opts[] = (string) $w;
    }
    $opts = array_values(array_unique(array_filter($opts, fn($o) => $o !== '')));
    $clientQuestions[] = ['i' => $i, 'text' => (string) ($q['text'] ?? ''), 'options' => $shuffle($opts)];
}
$nq = count($clientQuestions);

$quizData = [
    'slug'         => (string) $quiz['slug'],
    'timeLimit'    => $timeLimitMin * 60,
    'randomize'    => $randomize,
    'requireEmail' => $requireEmail,
    'loggedName'   => $userName,
    'submitUrl'    => ocms_base_url() . '/quiz/' . rawurlencode((string) $quiz['slug']) . '/submit',
    'emailUrl'     => ocms_base_url() . '/quiz/' . rawurlencode((string) $quiz['slug']) . '/check-email',
    'csrf'         => ocms_csrf_token(),
    'questions'    => $clientQuestions,
];

$flavor = [
    'title'     => $quiz['title'] ?? 'Quiz',
    'robots'    => 'noindex,nofollow',
    'seo'       => false,
    'bodyClass' => 'page-quiz',
    'minimal'   => true,
];
$flavorScripts = $active && $nq > 0 ? ['js/quiz.js'] : [];
include __DIR__ . '/_header.php';
?>
<div class="wrap quiz-wrap">
    <header class="quiz-head">
        <h1 class="display"><?= ocms_escape($quiz['title'] ?? 'Quiz') ?></h1>
<?php if (!empty($quiz['description'])): ?>
        <p class="dek"><?= ocms_escape(flavor_plain($quiz['description'])) ?></p>
<?php endif; ?>
        <ul class="quiz-facts">
            <li><?= flavor_icon('list', 15) ?><?= $nq === 1 ? '1 domanda' : $nq . ' domande' ?></li>
            <li><?= flavor_icon('timer', 15) ?><?= $timeLimitMin > 0 ? $timeLimitMin . ' minuti' : 'Senza limite di tempo' ?></li>
<?php if ($penalty): ?>
            <li><?= flavor_icon('alert', 15) ?>Corretta +2, errata −1, saltata 0</li>
<?php endif; ?>
        </ul>
    </header>

<?php if (!$active): ?>
    <section class="quiz-panel ocms-glass quiz-off" role="status">
        <span class="gate-ico"><?= flavor_icon('lock', 26) ?></span>
        <h2>Il quiz non è attivo</h2>
        <p class="muted">Il docente non ha ancora aperto questo quiz, oppure l'ha già chiuso. Riprova quando ti viene indicato.</p>
    </section>
<?php elseif ($nq === 0): ?>
    <section class="quiz-panel ocms-glass" role="status"><p>Questo quiz non contiene ancora domande.</p></section>
<?php else: ?>
    <noscript><div class="notice notice-error">Per svolgere il quiz serve JavaScript attivo nel browser.</div></noscript>

    <section class="quiz-panel ocms-glass" id="quiz-start" aria-labelledby="quiz-start-title">
        <h2 id="quiz-start-title">Prima di iniziare</h2>
        <div class="notice notice-info" id="quiz-recovery" hidden>
            <?= flavor_icon('check') ?>
            <div>
                <p><strong>Hai un quiz in corso su questo dispositivo.</strong> Puoi riprendere da dove eri rimasto: le risposte date sono salvate.</p>
                <div class="actions">
                    <button type="button" class="btn btn-primary btn-sm" data-quiz-recover>Riprendi</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-quiz-discard>Ricomincia da capo</button>
                </div>
            </div>
        </div>
        <form id="quiz-start-form" class="quiz-start-form" novalidate>
<?php if ($user): ?>
            <p class="muted">Accesso come <strong><?= ocms_escape($userName) ?></strong></p>
<?php else: ?>
            <div class="field">
                <label for="student-name">Cognome e nome</label>
                <input type="text" id="student-name" name="name" required maxlength="120" autocomplete="name" placeholder="Rossi Maria">
            </div>
<?php endif; ?>
            <div class="field">
                <label for="student-class">Classe</label>
                <input type="text" id="student-class" name="class" required maxlength="40" placeholder="3B" autocomplete="off">
            </div>
<?php if ($requireEmail): ?>
            <div class="field">
                <label for="student-email">Email</label>
                <input type="email" id="student-email" name="email" required maxlength="200" autocomplete="email" placeholder="nome@esempio.it">
            </div>
<?php endif; ?>
            <p class="field-error" id="quiz-start-error" role="alert" hidden></p>
            <button type="submit" class="btn btn-primary btn-block" id="quiz-start-btn">Inizia il quiz</button>
            <p class="form-note">Le risposte vengono salvate su questo dispositivo mentre procedi: se la pagina si chiude, puoi riprendere.</p>
        </form>
    </section>

    <section id="quiz-run" class="quiz-run" hidden aria-label="Domande del quiz">
        <div class="quiz-bar ocms-glass">
            <div class="quiz-bar-row">
                <span class="quiz-timer" id="quiz-timer" aria-label="Tempo"><?= flavor_icon('timer', 16) ?><span>0:00</span></span>
                <span class="quiz-count" id="quiz-count">Domanda 1 di <?= $nq ?></span>
                <span class="quiz-answered" id="quiz-answered">0 risposte</span>
            </div>
            <div class="quiz-progress" aria-hidden="true"><span id="quiz-progress-fill"></span></div>
        </div>
        <p class="sr-only" id="quiz-time-alert" role="status" aria-live="polite"></p>

        <form class="quiz-question ocms-glass" id="quiz-question" novalidate>
            <fieldset>
                <legend id="quiz-q-legend"></legend>
                <div class="quiz-options" id="quiz-options"></div>
            </fieldset>
        </form>

        <div class="quiz-nav">
            <button type="button" class="btn btn-ghost" id="quiz-prev"><?= flavor_icon('left', 16) ?>Precedente</button>
            <button type="button" class="btn btn-primary" id="quiz-next">Successiva<?= flavor_icon('right', 16) ?></button>
            <button type="button" class="btn btn-primary btn-finish" id="quiz-finish">Termina e consegna</button>
        </div>

        <nav class="quiz-map" aria-label="Vai alla domanda">
            <ol id="quiz-map"></ol>
        </nav>
    </section>

    <section id="quiz-done" class="quiz-panel ocms-glass quiz-done" hidden aria-live="polite">
        <div id="quiz-sending" class="quiz-sending" hidden><span class="spinner" aria-hidden="true"></span><p>Invio delle risposte in corso…</p></div>
        <div id="quiz-error" class="notice notice-error" hidden role="alert">
            <?= flavor_icon('alert') ?>
            <div>
                <p><strong>Le risposte non sono ancora state consegnate.</strong> <span id="quiz-error-msg"></span></p>
                <p class="muted">Sono salvate su questo dispositivo: non chiudere la pagina e riprova. Se il problema continua, avvisa il docente.</p>
                <div class="actions"><button type="button" class="btn btn-primary btn-sm" id="quiz-retry">Riprova l'invio</button></div>
            </div>
        </div>
        <div id="quiz-result" hidden>
            <h2 class="result-title">Quiz consegnato</h2>
            <p class="muted" id="quiz-result-who"></p>
            <div class="score-ring" id="quiz-score-ring" style="--p: 0">
                <span class="score-num" id="quiz-score">0</span><span class="score-max">/ 100</span>
            </div>
            <dl class="result-stats">
                <div class="is-ok"><dt>Corrette</dt><dd id="quiz-r-ok">0</dd></div>
                <div class="is-ko"><dt>Errate</dt><dd id="quiz-r-ko">0</dd></div>
                <div><dt>Saltate</dt><dd id="quiz-r-skip">0</dd></div>
                <div><dt>Tempo</dt><dd id="quiz-r-time">0:00</dd></div>
            </dl>
            <a class="btn btn-ghost" href="<?= ocms_base_url() ?>/quiz">Torna ai quiz</a>
        </div>
    </section>

    <dialog class="dialog ocms-glass" id="quiz-confirm" aria-labelledby="quiz-confirm-title">
        <h2 id="quiz-confirm-title">Consegnare il quiz?</h2>
        <p id="quiz-confirm-text"></p>
        <div class="actions">
            <button type="button" class="btn btn-ghost" value="cancel" data-dialog-close>Continua a rispondere</button>
            <button type="button" class="btn btn-primary" id="quiz-confirm-ok">Consegna</button>
        </div>
    </dialog>

    <script type="application/json" id="quiz-data"><?= json_encode($quizData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
