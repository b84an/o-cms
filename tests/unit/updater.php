<?php
// Collaudo dell'updater su una installazione finta, con URL file:// (niente rete)
require __DIR__ . '/../lib.php';
$root = sys_get_temp_dir() . '/ocms-upd-' . getmypid();
define('OCMS_ROOT', $root);
define('OCMS_VERSION', '2.0.0');
require dirname(__DIR__, 2) . '/core/Updater.php';
$w = function (string $p, string $c) { @mkdir(dirname($p), 0755, true); file_put_contents($p, $c); };
// installazione attuale
$w("$root/core/App.php", 'vecchio'); $w("$root/core/Obsoleto.php", 'da togliere');
$w("$root/admin/index.php", 'a'); $w("$root/index.php", 'i');
$w("$root/themes/flavor/custom.css", 'personalizzazione utente');
$w("$root/data/config.json", json_encode(['site_name' => 'X', 'maintenance_mode' => false]));
// release
$rel = "$root-rel/o-cms";
$w("$rel/core/App.php", 'nuovo'); $w("$rel/admin/index.php", 'a2'); $w("$rel/index.php", 'i2');
$w("$rel/assets/ocms/tokens.css", 'css'); $w("$rel/themes/flavor/style.css", 'tema');
$zipPath = "$root-rel/o-cms-2.1.0.zip";
$z = new ZipArchive(); $z->open($zipPath, ZipArchive::CREATE);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rel, FilesystemIterator::SKIP_DOTS)) as $f) {
    $z->addFile($f->getPathname(), substr($f->getPathname(), strlen("$root-rel/")));
}
$z->close();
file_put_contents("$zipPath.sha256", hash_file('sha256', $zipPath) . "  o-cms-2.1.0.zip\n");
file_put_contents("$zipPath.bad", str_repeat('0', 64) . "  o-cms-2.1.0.zip\n");

// le chiamate HTTP vengono sostituite da letture locali
class TestUpdater extends Updater {
    protected function httpGet(string $url): ?string { $p = substr($url, 7); return is_file($p) ? file_get_contents($p) : null; }
    protected function downloadFile(string $url, string $dest): bool { $p = substr($url, 7); return is_file($p) && copy($p, $dest) && filesize($dest) > 0; }
}
$u = new TestUpdater();
$r = $u->applyUpdate("file://$zipPath", 'v2.1.0', "file://$zipPath.bad", 'o-cms-2.1.0.zip');
t('checksum sbagliato → rifiutato', !$r['success'] && str_contains($r['message'], 'non corrispondente') && file_get_contents("$root/core/App.php") === 'vecchio', $r['message']);
$r = $u->applyUpdate("file://$zipPath", 'v2.1.0', null, 'o-cms-2.1.0.zip');
t('senza checksum → rifiutato', !$r['success'] && str_contains($r['message'], 'checksum') && file_get_contents("$root/core/App.php") === 'vecchio', $r['message']);
$r = $u->applyUpdate("file://$zipPath", 'v2.1.0', "file://$zipPath.sha256", 'o-cms-2.1.0.zip');
t('checksum giusto → aggiornato', $r['success'] && file_get_contents("$root/core/App.php") === 'nuovo', $r['message']);
t('file obsoleto del core rimosso', !is_file("$root/core/Obsoleto.php"));
t('file personalizzato del tema conservato', is_file("$root/themes/flavor/custom.css"));
t('nuova cartella assets/ocms installata', is_file("$root/assets/ocms/tokens.css"));
$cfg = json_decode(file_get_contents("$root/data/config.json"), true);
t('manutenzione spenta e config intatta', $cfg['maintenance_mode'] === false && $cfg['site_name'] === 'X');
// zip slip
$evil = "$root-rel/evil.zip"; $z = new ZipArchive(); $z->open($evil, ZipArchive::CREATE);
$z->addFromString('o-cms/core/App.php', 'x'); $z->addFromString('o-cms/../../evil.php', 'x'); $z->close();
file_put_contents("$evil.sha256", hash_file('sha256', $evil));
$r = $u->applyUpdate("file://$evil", 'v2.2.0', "file://$evil.sha256", 'evil.zip');
t('zip con ../ rifiutato', !$r['success'] && str_contains($r['message'], 'percorsi'), $r['message']);
// .htaccess: blocco personalizzazioni conservato
$cur = "RewriteEngine On\n# === PERSONALIZZAZIONI DEL SITO ===\nRewriteRule ^vecchio$ /nuovo [R=301,L]\n# === FINE PERSONALIZZAZIONI ===\nVECCHIE REGOLE\n";
$rel2 = "RewriteEngine On\nNUOVA\n# === PERSONALIZZAZIONI DEL SITO ===\n# vuoto\n# === FINE PERSONALIZZAZIONI ===\nNUOVE REGOLE\n";
$m = $u->mergeHtaccess($cur, $rel2);
t('htaccess: redirect conservati e regole nuove applicate', str_contains($m, 'RewriteRule ^vecchio$') && str_contains($m, 'NUOVE REGOLE') && !str_contains($m, 'VECCHIE REGOLE'));
t('htaccess senza blocco: non si tocca', $u->mergeHtaccess("RewriteRule ^x$ /y [R=301]", $rel2) === null);
exec('rm -rf ' . escapeshellarg($root) . ' ' . escapeshellarg("$root-rel"));
done();
