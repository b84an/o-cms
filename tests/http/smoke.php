<?php
// Smoke test HTTP: php8.3 tests/http/smoke.php [base=http://127.0.0.1:8099/s]
require __DIR__ . '/../lib.php';
$base = $argv[1] ?? 'http://127.0.0.1:8099/s';
$dataDir = $argv[2] ?? null;
$paths = ['/', '/blog', '/lezioni', '/quiz', '/gallerie', '/search?q=rete', '/admin/login', '/pagina-che-non-esiste-xyz'];
if ($dataDir) {
    $arts = glob("$dataDir/articles/*.json");
    foreach (array_slice($arts, 0, 400) as $f) { $a = json_decode(file_get_contents($f), true); if (($a['status'] ?? '') === 'published') { $paths[] = '/blog/' . $a['slug']; } }
    foreach (glob("$dataDir/lessons/*.json") as $f) { $a = json_decode(file_get_contents($f), true); if (($a['status'] ?? '') === 'published') $paths[] = '/lezione/' . $a['slug']; }
    foreach (glob("$dataDir/quizzes/*.json") as $f) { $a = json_decode(file_get_contents($f), true); $paths[] = '/quiz/' . $a['slug']; }
    foreach (glob("$dataDir/pages/*.json") as $f) { $a = json_decode(file_get_contents($f), true); if (($a['status'] ?? '') === 'published' && $a['slug'] !== 'home') $paths[] = '/' . $a['slug']; }
}
$bad = 0;
foreach ($paths as $p) {
    $r = http('GET', $base . $p);
    $expect = str_contains($p, 'non-esiste') ? 404 : 200;
    $err = php_errors($r['body']);
    $ok = ($r['code'] === $expect || ($r['code'] === 302 && str_starts_with($p, '/quiz/'))) && $err === '';
    if (!$ok || count($paths) < 40) t("GET $p → {$r['code']}", $ok, $err);
    if (!$ok) $bad++;
}
t(count($paths) . " URL controllati, $bad problemi", $bad === 0);
t('data/ bloccata', http('GET', "$base/data/config.json")['code'] === 403);
done();
