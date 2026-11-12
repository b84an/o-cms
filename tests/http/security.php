<?php
/**
 * Test end-to-end delle correzioni di sicurezza v2.
 * Uso: php8.3 tests/http/security.php [base] [docroot-dati]
 * Richiede un server avviato con tests/serve.sh (lavora su una COPIA dei dati).
 */
require __DIR__ . '/../lib.php';
$base = $argv[1] ?? 'http://127.0.0.1:8099/s';
$data = $argv[2] ?? '/tmp/ocms-test-8099/s/data';
$tmp = sys_get_temp_dir() . '/ocms-sec-' . getmypid();
@mkdir($tmp);

// ─── utenti di prova creati direttamente nella copia dei dati ───
$mk = function (string $u, string $role) use ($data) {
    file_put_contents("$data/users/$u.json", json_encode([
        'id' => bin2hex(random_bytes(8)), 'username' => $u, 'email' => "$u@example.test",
        'password' => password_hash('Password-Test-1', PASSWORD_DEFAULT), 'display_name' => $u,
        'role' => $role, 'active' => true, 'created_at' => date('c'),
    ]));
};
$mk('t_super', 'super_administrator');
$mk('t_admin', 'administrator');
$mk('t_editor', 'editor');

function csrf_from(string $html): string {
    return preg_match('/name="_csrf_token" value="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : '';
}
function login(string $base, string $user, string $jar): array {
    $r = http('GET', "$base/admin/login", ['cookiejar' => $jar]);
    $tok = csrf_from($r['body']);
    return http('POST', "$base/admin/login", ['cookiejar' => $jar, 'body' => http_build_query(['_csrf_token' => $tok, 'username' => $user, 'password' => 'Password-Test-1'])]);
}
function loc(array $r): string {
    return preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? $m[1] : '';
}
function admin_token(string $base, string $jar): string {
    $r = http('GET', "$base/admin/articles/new", ['cookiejar' => $jar]);
    return csrf_from($r['body']);
}

echo "== CSRF\n";
$jar0 = "$tmp/anon.txt";
$r = http('POST', "$base/admin/login", ['cookiejar' => $jar0, 'body' => 'username=t_super&password=Password-Test-1']);
$r2 = http('GET', "$base/admin/", ['cookiejar' => $jar0]);
t('login senza token CSRF (sessione nuova) rifiutato', str_contains(loc($r2), '/admin/login'), loc($r2));

echo "== Login e ruoli\n";
$jarS = "$tmp/super.txt"; $r = login($base, 't_super', $jarS);
t('super admin → /admin', str_ends_with(loc($r), '/admin'), loc($r));
$jarE = "$tmp/editor.txt"; $r = login($base, 't_editor', $jarE);
t('editor → /admin/articles (non più 403)', str_ends_with(loc($r), '/admin/articles'), loc($r));
$r = http('GET', "$base/admin/", ['cookiejar' => $jarE]);
t('editor su /admin viene rediretto, non 403', $r['code'] === 302, (string)$r['code']);

$jarA = "$tmp/admin.txt"; login($base, 't_admin', $jarA);
$tok = admin_token($base, $jarA);
$r = http('POST', "$base/admin/users/save", ['cookiejar' => $jarA, 'body' => http_build_query(['_csrf_token' => $tok, 'username' => 't_admin', 'email' => 'a@example.test', 'display_name' => 'x', 'role' => 'super_administrator', 'active' => 'on'])]);
$u = json_decode(file_get_contents("$data/users/t_admin.json"), true);
t('administrator non può promuoversi super_administrator', $u['role'] === 'administrator', $u['role']);
$r = http('POST', "$base/admin/users/save", ['cookiejar' => $jarA, 'body' => http_build_query(['_csrf_token' => $tok, 'username' => 't_super', 'email' => 's@example.test', 'display_name' => 'x', 'role' => 'administrator', 'password' => 'Hacked-12345'])]);
$u = json_decode(file_get_contents("$data/users/t_super.json"), true);
t('administrator non può modificare un super_administrator', password_verify('Password-Test-1', $u['password']) && $u['role'] === 'super_administrator');

// ruolo ricaricato a ogni richiesta
$mk('t_editor', 'registered');
$r = http('GET', "$base/admin/articles", ['cookiejar' => $jarE]);
t('editor declassato perde subito l\'accesso', $r['code'] === 403, (string)$r['code']);
$mk('t_editor', 'editor');

echo "== Form: le compilazioni sopravvivono al salvataggio\n";
file_put_contents("$data/forms/t-form.json", json_encode(['id' => 'form-id-1', 'name' => 'T form', 'slug' => 't-form', 'fields' => [], 'settings' => [], 'submissions' => [['id' => 's1', 'data' => ['a' => 'b']]]]));
$tokS = admin_token($base, $jarS);
$r = http('POST', "$base/admin/forms/save", ['cookiejar' => $jarS, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['_csrf_token' => $tokS, 'id' => 'form-id-1', 'name' => 'T form', 'fields' => [], 'settings' => []])]);
$f = json_decode(file_get_contents("$data/forms/t-form.json"), true);
t('submissions conservate', count($f['submissions'] ?? []) === 1, $r['body']);
$r = http('POST', "$base/admin/forms/save", ['cookiejar' => $jarS, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['_csrf_token' => $tokS, 'id' => 'form-id-1', 'name' => 'T form rinominato', 'fields' => [], 'settings' => []])]);
$f2 = @json_decode(@file_get_contents("$data/forms/t-form-rinominato.json"), true);
t('rinomina form: compilazioni spostate e vecchio file rimosso', count($f2['submissions'] ?? []) === 1 && !is_file("$data/forms/t-form.json"), $r['body']);

file_put_contents("$data/forms/t-legacy.json", json_encode(['id' => '', 'name' => 'T legacy', 'slug' => 't-legacy', 'fields' => [], 'settings' => [], 'submissions' => [['id' => 'a'], ['id' => 'b']]]));
$r = http('POST', "$base/admin/forms/save", ['cookiejar' => $jarS, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['_csrf_token' => $tokS, 'id' => '', 'original_slug' => 't-legacy', 'name' => 'T legacy', 'fields' => [], 'settings' => []])]);
$f3 = json_decode(file_get_contents("$data/forms/t-legacy.json"), true);
t('form vecchio con id vuoto si salva e tiene le compilazioni', str_contains($r['body'], '"success":true') && count($f3['submissions'] ?? []) === 2 && $f3['id'] !== '', $r['body']);
$r = http('POST', "$base/admin/forms/save", ['cookiejar' => $jarS, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['_csrf_token' => $tokS, 'id' => '', 'name' => 'T legacy', 'fields' => [], 'settings' => []])]);
t('form NUOVO con lo stesso nome di uno esistente: rifiutato', str_contains((string)(json_decode($r['body'], true)['error'] ?? ''), 'già un altro form'), $r['body']);

echo "== Upload\n";
file_put_contents("$tmp/x.php", "GIF89a<?php echo 'pwn'; ?>");
$r = http('POST', "$base/admin/media/upload", ['cookiejar' => $jarS, 'body' => ['_csrf_token' => $tokS, 'file' => new CURLFile("$tmp/x.php", 'image/gif', 'x.php')]]);
t('foto.php travestita da GIF rifiutata', $r['code'] === 400 && !glob(dirname($data) . '/uploads/images/x-*.php'), $r['body']);
file_put_contents("$tmp/y.gif", base64_decode('R0lGODlhAQABAAAAACw='));
$r = http('POST', "$base/admin/media/upload", ['cookiejar' => $jarS, 'body' => ['_csrf_token' => $tokS, 'file' => new CURLFile("$tmp/y.gif", 'image/gif', 'y.gif')]]);
t('GIF vera accettata', $r['code'] === 200, $r['code'] . ' ' . substr($r['body'], 0, 120));
t('core/ non raggiungibile dal browser', http('GET', "$base/core/installer-template.php")['code'] === 403);
t('PHP in uploads bloccato', http('GET', "$base/uploads/images/qualcosa.php")['code'] === 403);

echo "== Articoli: rinomina e slug duplicati\n";
$art = ['id' => 'art-1', 'title' => 'Prova rinomina', 'slug' => 't-art', 'content' => '<p>x</p>', 'status' => 'published', 'views' => 42, 'author' => 'ivan', 'created_at' => '2025-01-01T10:00:00+01:00', 'custom_field' => 'resto'];
file_put_contents("$data/articles/t-art.json", json_encode($art));
@mkdir("$data/revisions/t-art", 0755, true); file_put_contents("$data/revisions/t-art/2025-01-01_10-00-00.json", json_encode($art));
$post = ['_csrf_token' => $tokS, 'original_slug' => 't-art', 'slug' => 't-art-nuovo', 'title' => 'Prova rinomina', 'content' => '<p>y</p>', 'status' => 'published', 'id' => 'art-1', 'created_at' => ''];
http('POST', "$base/admin/articles/save", ['cookiejar' => $jarS, 'body' => http_build_query($post)]);
$n = @json_decode(@file_get_contents("$data/articles/t-art-nuovo.json"), true);
t('rinomina: visite conservate', ($n['views'] ?? null) === 42, json_encode($n['views'] ?? null));
t('rinomina: autore e data di creazione conservati', ($n['author'] ?? '') === 'ivan' && ($n['created_at'] ?? '') === '2025-01-01T10:00:00+01:00');
t('rinomina: campi sconosciuti conservati', ($n['custom_field'] ?? '') === 'resto');
t('rinomina: vecchio file rimosso', !is_file("$data/articles/t-art.json"));
t('rinomina: revisioni spostate (vecchia + nuova)', count(glob("$data/revisions/t-art-nuovo/*.json")) === 2);
file_put_contents("$data/articles/t-altro.json", json_encode(['id' => 'art-2', 'title' => 'Altro', 'slug' => 't-altro', 'content' => 'ORIGINALE', 'status' => 'draft']));
$post = ['_csrf_token' => $tokS, 'original_slug' => '', 'slug' => 't-altro', 'title' => 'Nuovo', 'content' => '<p>nuovo</p>', 'status' => 'draft', 'id' => '', 'created_at' => ''];
$r = http('POST', "$base/admin/articles/save", ['cookiejar' => $jarS, 'body' => http_build_query($post)]);
$o = json_decode(file_get_contents("$data/articles/t-altro.json"), true);
t('nuovo articolo con slug esistente non sovrascrive', $o['content'] === 'ORIGINALE' && is_file("$data/articles/t-altro-2.json"), loc($r));

echo "== Quiz\n";
file_put_contents("$data/quizzes/t-quiz.json", json_encode(['id' => 'q', 'title' => 'Q', 'slug' => 't-quiz', 'status' => 'published', 'settings' => ['access_code' => '1234', 'active' => true], 'questions' => [['text' => 'Uno?', 'correct' => 'Sì', 'wrong' => ['No']]]]));
$jarQ = "$tmp/q.txt";
$p = http('GET', "$base/quiz/t-quiz", ['cookiejar' => $jarQ]);
$qt = csrf_from($p['body']);
$r = http('POST', "$base/quiz/t-quiz/submit", ['cookiejar' => $jarQ, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['_csrf_token' => $qt, 'student' => ['name' => 'X'], 'answers' => ['Sì']])]);
t('invio quiz senza codice d\'accesso rifiutato', str_contains($r['body'], '"success":false'), $r['body']);
$r = http('POST', "$base/quiz/t-quiz/verify-code", ['cookiejar' => $jarQ, 'body' => http_build_query(['_csrf_token' => $qt, 'access_code' => '1234'])]);
$p = http('GET', "$base/quiz/t-quiz", ['cookiejar' => $jarQ]);
$r = http('POST', "$base/quiz/t-quiz/submit", ['cookiejar' => $jarQ, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['_csrf_token' => $qt, 'student' => ['name' => 'X', 'email' => 'x' . getmypid() . '@example.test'], 'answers' => [['array' => 'strano'], 'extra']])]);
t('con il codice l\'invio passa, risposte strane neutralizzate', str_contains($r['body'], '"success":true'), $r['body']);
$jarQ2 = "$tmp/q2.txt"; $p = http('GET', "$base/quiz/t-quiz", ['cookiejar' => $jarQ2]); $qt2 = csrf_from($p['body']);
for ($i = 0; $i < 9; $i++) http('POST', "$base/quiz/t-quiz/verify-code", ['cookiejar' => $jarQ2, 'body' => http_build_query(['_csrf_token' => $qt2, 'access_code' => sprintf('%04d', 5000 + $i)])]);
http('POST', "$base/quiz/t-quiz/verify-code", ['cookiejar' => $jarQ2, 'body' => http_build_query(['_csrf_token' => $qt2, 'access_code' => '1234'])]);
$p = http('GET', "$base/quiz/t-quiz", ['cookiejar' => $jarQ2]);
t('dopo 8 codici sbagliati anche quello giusto viene bloccato', str_contains($p['body'], 'access_code'), '');

echo "== FoglioFirme e API\n";
file_put_contents("$data/fogliofirme/t-firme.json", json_encode(['id' => 'ff', 'slug' => 't-firme', 'name' => 'F', 'status' => 'open', 'access_code' => '999', 'firme' => [['nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'm@example.test', 'signed_at' => date('c')]]]));
$r = http('GET', "$base/firma/t-firme/count");
t('conteggio pubblico senza nomi', str_contains($r['body'], '"count":1') && !str_contains($r['body'], 'Rossi'), $r['body']);
t('schermata /show riservata allo staff', http('GET', "$base/firma/t-firme/show")['code'] === 302);
$r = http('GET', "$base/firma/t-firme/show", ['cookiejar' => $jarS]);
t('/show visibile allo staff', $r['code'] === 200 && str_contains($r['body'], '999'));
$idx = http('GET', "$base/admin/fogliofirme", ['cookiejar' => $jarS]);
$k = preg_match('#/firma/t-firme/show\?k=([a-f0-9]+)#', $idx['body'], $mm) ? $mm[1] : '';
$r = http('GET', "$base/firma/t-firme/show?k=$k");
t('proiezione con link firmato dall\'admin, senza login', $k !== '' && $r['code'] === 200 && str_contains($r['body'], '999'), (string)$r['code']);
t('link firmato falso rifiutato', http('GET', "$base/firma/t-firme/show?k=deadbeef")['code'] === 302);
$r = http('GET', "$base/firma/t-firme/count?k=$k");
t('la proiezione vede l\'ultima firma', str_contains($r['body'], 'Rossi'), $r['body']);
$r = http('GET', "$base/api/config", ['cookiejar' => $jarS]);
t('/api/config senza chiavi IA né SMTP', $r['code'] === 200 && !str_contains($r['body'], '"api_key"') && !str_contains($r['body'], '"smtp"'), substr($r['body'], 0, 80));
$r = http('POST', "$base/api/articles", ['cookiejar' => $jarS, 'headers' => ['Content-Type: application/json'], 'body' => json_encode(['title' => 'csrf'])]);
t('API in scrittura con la sola sessione richiede X-CSRF-Token', $r['code'] === 403);

echo "== Form contatti\n";
$r = http('POST', "$base/contatto/invia", ['headers' => ['Content-Type: application/json'], 'body' => json_encode(['nome' => 'x', 'email' => 'a@example.test', 'messaggio' => 'y'])]);
t('form contatti senza Origin del sito rifiutato', $r['code'] === 403, $r['body']);

exec('rm -rf ' . escapeshellarg($tmp));
done();
