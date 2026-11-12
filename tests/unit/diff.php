<?php
require __DIR__ . '/../lib.php';
define('OCMS_ROOT', dirname(__DIR__, 2));
require OCMS_ROOT . '/core/Helpers.php';
$l = ocms_html_to_lines('<h2>Titolo</h2><p>Uno &amp; due</p><script>x()</script><ul><li>a</li><li>b</li></ul>');
t('html → righe', $l === ['Titolo', 'Uno & due', 'a', 'b'], json_encode($l));
$d = ocms_line_diff(['a', 'b', 'c', 'd'], ['a', 'x', 'c', 'd', 'e']);
$ops = implode('', array_map(fn($x) => $x['op'] . $x['text'], $d));
t('diff corretto', $ops === '=a-b+x=c=d+e', $ops);
t('diff identico', count(array_filter(ocms_line_diff(['a'], ['a']), fn($x) => $x['op'] !== '=')) === 0);
$big = range(1, 3000); $big2 = $big; $big2[1500] = 'cambiata';
$d = ocms_line_diff(array_map('strval', $big), array_map('strval', $big2));
t('diff grande con prefisso/suffisso comuni', count(array_filter($d, fn($x) => $x['op'] !== '=')) === 2);
done();
