<?php
// Esegue tutti i test unitari: php8.3 tests/run.php
$fail = 0;
foreach (glob(__DIR__ . '/unit/*.php') as $f) {
    echo "== " . basename($f) . "\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($f), $rc);
    if ($rc) $fail++;
}
echo $fail ? "\nFALLITI: $fail file\n" : "\nTUTTI I TEST UNITARI OK\n";
exit($fail ? 1 : 0);
