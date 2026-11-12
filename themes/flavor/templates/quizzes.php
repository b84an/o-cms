<?php
require_once __DIR__ . '/_bootstrap.php';
$list = array_values(is_array($quizzes ?? null) ? $quizzes : []);
$flavor = [
    'title'       => 'Quiz',
    'description' => 'Quiz e verifiche online di ' . ($app->config['site_name'] ?? 'questo sito'),
    'seo'         => ['title' => 'Quiz', 'description' => $app->config['site_description'] ?? ''],
    'bodyClass'   => 'page-quizzes',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head">
        <h1 class="display">Quiz</h1>
        <p class="page-head-count"><?= count($list) === 1 ? '1 quiz disponibile' : count($list) . ' quiz disponibili' ?></p>
    </header>
<?php if (!$list): ?>
    <div class="empty-state ocms-glass"><p>Nessun quiz disponibile per ora.</p></div>
<?php else: ?>
    <ul class="quiz-list">
<?php foreach ($list as $q):
    $s = is_array($q['settings'] ?? null) ? $q['settings'] : [];
    $nq = count(is_array($q['questions'] ?? null) ? $q['questions'] : []);
    $time = (int) ($s['time_limit'] ?? ($q['time_limit'] ?? 0));
    $active = $s['active'] ?? true;
    $locked = !empty($s['access_code']); ?>
        <li class="quiz-item<?= $active ? '' : ' is-off' ?>">
            <div class="quiz-item-num"><span><?= $nq ?></span><small><?= $nq === 1 ? 'domanda' : 'domande' ?></small></div>
            <div class="quiz-item-body">
                <h2 class="card-title"><a href="<?= ocms_base_url() ?>/quiz/<?= rawurlencode((string) $q['slug']) ?>"><?= ocms_escape($q['title'] ?? '') ?></a></h2>
<?php if (!empty($q['description'])): ?>
                <p class="card-excerpt"><?= ocms_escape(ocms_truncate(flavor_plain($q['description']), 180)) ?></p>
<?php endif; ?>
                <ul class="quiz-facts">
                    <li><?= flavor_icon('timer', 15) ?><?= $time > 0 ? $time . ' minuti' : 'Senza limite di tempo' ?></li>
<?php if (!empty($s['penalty_mode'])): ?>
                    <li><?= flavor_icon('alert', 15) ?>Risposte errate penalizzate</li>
<?php endif; ?>
<?php if ($locked): ?>
                    <li><?= flavor_icon('lock', 15) ?>Serve il codice del docente</li>
<?php endif; ?>
<?php if (!$active): ?>
                    <li class="is-off-label">Non attivo</li>
<?php endif; ?>
                </ul>
            </div>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
