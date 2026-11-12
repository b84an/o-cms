<?php
require __DIR__ . '/../lib.php';
define('OCMS_ROOT', dirname(__DIR__, 2));
require OCMS_ROOT . '/core/Helpers.php';
require OCMS_ROOT . '/core/JsonStorage.php';
$base = sys_get_temp_dir() . '/ocms-storage-' . getmypid();
@mkdir($base, 0755, true);
$s = new JsonStorage($base);

t('save + find', $s->save('things', 'a', ['x' => 1]) && $s->find('things', 'a') === ['x' => 1]);
t('nessun .tmp rimasto', glob("$base/things/*.tmp") === []);
t('path traversal nello slug neutralizzato', $s->save('things', '../../evil', ['y' => 1]) && is_file("$base/things/evil.json"));
t('writeFile non esce da data/', $s->writeFile('../../fuori.json', ['z' => 1]) && is_file("$base/fuori.json"));
$r = $s->update('things', 'nuovo', fn($d) => ['n' => ($d['n'] ?? 0) + 1]);
t('update crea se manca', $r === ['n' => 1]);
t('update con null non scrive', $s->update('things', 'mai', fn($d) => null) === null && !is_file("$base/things/mai.json"));

// concorrenza: 20 processi aggiungono un elemento ciascuno
$s->save('things', 'lista', ['items' => []]);
$pids = [];
for ($i = 0; $i < 20; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        $st = new JsonStorage($base);
        $st->update('things', 'lista', function ($d) use ($i) { usleep(random_int(0, 3000)); $d['items'][] = $i; return $d; });
        exit(0);
    }
    $pids[] = $pid;
}
foreach ($pids as $p) pcntl_waitpid($p, $st);
$final = $s->find('things', 'lista');
t('20 update concorrenti → 20 elementi', count($final['items'] ?? []) === 20, 'trovati ' . count($final['items'] ?? []));
exec('rm -rf ' . escapeshellarg($base));
done();
