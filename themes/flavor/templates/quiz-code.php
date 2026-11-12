<?php
require_once __DIR__ . '/_bootstrap.php';
$s = is_array($quiz['settings'] ?? null) ? $quiz['settings'] : [];
$nq = count(is_array($quiz['questions'] ?? null) ? $quiz['questions'] : []);
$time = (int) ($s['time_limit'] ?? 0);
$numeric = ctype_digit((string) ($s['access_code'] ?? ''));
$flashes = ocms_flash_get();
$flavor = [
    'title'     => $quiz['title'] ?? 'Quiz',
    'robots'    => 'noindex,nofollow',
    'seo'       => false,
    'bodyClass' => 'page-quiz page-quiz-code',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap narrow-center">
    <section class="gate ocms-glass" aria-labelledby="gate-title">
        <span class="gate-ico"><?= flavor_icon('lock', 26) ?></span>
        <h1 id="gate-title" class="gate-title"><?= ocms_escape($quiz['title'] ?? 'Quiz') ?></h1>
        <p class="muted">Per iniziare inserisci il codice di accesso comunicato dal docente.</p>
        <?= flavor_flashes($flashes) ?>
        <form method="post" action="<?= ocms_base_url() ?>/quiz/<?= rawurlencode((string) $quiz['slug']) ?>/verify-code" autocomplete="off" class="gate-form">
            <input type="hidden" name="_csrf_token" value="<?= ocms_escape(ocms_csrf_token()) ?>">
            <label for="access_code">Codice di accesso</label>
            <input type="text" id="access_code" name="access_code" class="code-input" required maxlength="64" autofocus
                   inputmode="<?= $numeric ? 'numeric' : 'text' ?>" autocapitalize="off" spellcheck="false">
            <button type="submit" class="btn btn-primary btn-block">Entra nel quiz</button>
        </form>
        <ul class="quiz-facts quiz-facts-center">
            <li><?= flavor_icon('list', 15) ?><?= $nq === 1 ? '1 domanda' : $nq . ' domande' ?></li>
            <li><?= flavor_icon('timer', 15) ?><?= $time > 0 ? $time . ' minuti' : 'Senza limite di tempo' ?></li>
        </ul>
    </section>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
