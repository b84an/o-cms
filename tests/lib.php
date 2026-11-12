<?php
$GLOBALS['__fail'] = 0; $GLOBALS['__n'] = 0;
function t(string $name, bool $ok, string $info = ''): void {
    $GLOBALS['__n']++;
    if ($ok) { echo "  ok   $name\n"; } else { $GLOBALS['__fail']++; echo "  FAIL $name" . ($info ? " — $info" : '') . "\n"; }
}
function done(): never {
    $n = $GLOBALS['__n']; $f = $GLOBALS['__fail'];
    echo "\n" . ($n - $f) . "/$n superati\n";
    exit($f ? 1 : 0);
}
function http(string $method, string $url, array $opt = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    if (isset($opt['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['body']);
    if (isset($opt['headers'])) curl_setopt($ch, CURLOPT_HTTPHEADER, $opt['headers']);
    if (isset($opt['cookiejar'])) { curl_setopt($ch, CURLOPT_COOKIEJAR, $opt['cookiejar']); curl_setopt($ch, CURLOPT_COOKIEFILE, $opt['cookiejar']); }
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'headers' => substr((string)$raw, 0, $hs), 'body' => substr((string)$raw, $hs)];
}
function php_errors(string $body): string {
    return preg_match('/(?:<b>)?(?:Fatal error|Warning|Notice|Deprecated|Parse error)(?:<\/b>)?:\s.{0,200}? on line|Uncaught \w+.{0,160}/s', $body, $m) ? $m[0] : '';
}
