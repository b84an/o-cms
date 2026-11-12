<?php
/**
 * Test per ocms_sanitize_html().
 * Uso: php test_sanitize.php Helpers_orig.php
 *
 * Gruppo A: l'HTML legittimo delle infografiche deve sopravvivere (fallisce prima del fix).
 * Gruppo B: i vettori XSS devono restare bloccati (deve passare sempre, prima e dopo).
 */
if (!defined('OCMS_ROOT')) define('OCMS_ROOT', dirname(__DIR__, 2));
require_once $argv[1] ?? dirname(__DIR__, 2) . '/core/Helpers.php';

$pass = 0; $fail = 0; $failed = [];

function check(string $group, string $name, bool $ok) {
    global $pass, $fail, $failed;
    if ($ok) { $pass++; }
    else { $fail++; $failed[] = "[$group] $name"; }
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $name);
}

// ─────────────────────────────────────────── A: deve essere CONSERVATO
echo "\nA. HTML legittimo che deve sopravvivere\n";

$s = ocms_sanitize_html('<style>.x { fill: var(--text); }</style>');
check('A', 'blocco <style> conservato', strpos($s, '<style>') !== false);

$s = ocms_sanitize_html('<svg viewBox="0 0 10 10"><rect x="1" y="1"/><path d="M0,0 L5,5"/></svg>');
check('A', '<svg> con <rect> e <path> conservato',
    strpos($s, '<svg') !== false && strpos($s, '<rect') !== false && strpos($s, '<path') !== false);

$s = ocms_sanitize_html('<svg><defs><marker id="a"><path d="M0,0"/></marker></defs><text x="1">ciao</text></svg>');
check('A', '<defs>, <marker>, <text> conservati',
    strpos($s, '<defs') !== false && strpos($s, '<marker') !== false && strpos($s, '<text') !== false);

$s = ocms_sanitize_html('<svg><linearGradient id="g"><stop offset="0"/></linearGradient><clipPath id="c"/></svg>');
check('A', 'tag SVG camelCase conservati',
    stripos($s, '<linearGradient') !== false && stripos($s, '<stop') !== false && stripos($s, '<clipPath') !== false);

$s = ocms_sanitize_html('<svg><circle cx="1"/><ellipse rx="2"/><line x1="0"/><polyline points="0,0"/><polygon points="1,1"/><g><tspan>x</tspan></g><title>t</title></svg>');
check('A', 'circle/ellipse/line/polyline/polygon/g/tspan/title conservati',
    substr_count($s, '<') >= 9);

$s = ocms_sanitize_html('<p>testo <strong>grassetto</strong></p><h2>titolo</h2><blockquote>cit</blockquote>');
check('A', 'HTML normale non toccato (nessuna regressione)',
    strpos($s, '<p>') !== false && strpos($s, '<h2>') !== false && strpos($s, '<blockquote>') !== false);

// caso reale: il contenuto dell'articolo che si e' rotto
$revFile = '/mnt/ftp-ivanbertotto/s/data/revisions/il-pappagallo-e-morto-l-uccello-vola/2026-08-09_23-33-04.json';
if (file_exists($revFile)) {
    $real = json_decode(file_get_contents($revFile), true)['content'];
    $out  = ocms_sanitize_html($real);
    check('A', 'articolo reale: i 3 SVG sopravvivono', substr_count($out, '<svg') === 3);
    check('A', 'articolo reale: il blocco <style> sopravvive', strpos($out, '<style>') !== false);
    check('A', 'articolo reale: nessuna perdita oltre il 2%',
        strlen($out) >= strlen($real) * 0.98);
}

$s = ocms_sanitize_html('<img src="data:image/png;base64,iVBORw0KGgo=" alt="x">');
check('A', 'immagine PNG base64 conservata', strpos($s, 'data:image/png;base64,iVBORw0KGgo=') !== false);

$s = ocms_sanitize_html('<pre><code>button.on_click = handler</code></pre>');
check('A', 'testo "on_click = x" nei <pre> conservato', strpos($s, 'on_click = handler') !== false);

$s = ocms_sanitize_html('<p>Il comando onload=1 nel testo</p>');
check('A', 'parola "onload=" nel testo (fuori dai tag) conservata', strpos($s, 'onload=1') !== false);

$s = ocms_sanitize_html('<a href="https://esempio.it/a?b=1&amp;c=2" title="x">ok</a>');
check('A', 'link https intatto al byte', $s === '<a href="https://esempio.it/a?b=1&amp;c=2" title="x">ok</a>');

// ─────────────────────────────────────────── B: deve essere BLOCCATO
echo "\nB. Vettori XSS che devono restare bloccati\n";

$cases = [
    ['<script> nudo',                '<p>a</p><script>alert(1)</script>',                    '<script'],
    ['<script> dentro <svg>',        '<svg><script>alert(1)</script></svg>',                 '<script'],
    ['onload su <svg>',              '<svg onload="alert(1)"><rect/></svg>',                 'onload'],
    ['onerror su <img>',             '<img src="x" onerror="alert(1)">',                     'onerror'],
    ['onclick senza virgolette',     '<p onclick=alert(1)>x</p>',                            'onclick'],
    ['javascript: in href',          '<a href="javascript:alert(1)">x</a>',                  'javascript:'],
    ['javascript: in xlink:href',    '<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>', 'javascript:'],
    ['<foreignObject>',              '<svg><foreignObject><body>x</body></foreignObject></svg>', '<foreignObject'],
    ['<animate> su href',            '<svg><animate attributeName="href" values="javascript:alert(1)"/></svg>', '<animate'],
    ['<iframe>',                     '<iframe src="//evil"></iframe>',                       '<iframe'],
    ['<object>',                     '<object data="//evil"></object>',                      '<object'],
    ['@import dentro <style>',       '<style>@import url("//evil/x.css");</style>',          '@import'],
    ['expression() dentro <style>',  '<style>.a{width:expression(alert(1))}</style>',        'expression('],
    ['behavior: dentro <style>',     '<style>.a{behavior:url(#x)}</style>',                  'behavior:'],
    ['javascript: dentro <style>',   '<style>.a{background:url(javascript:alert(1))}</style>', 'javascript:'],
    ['<set> su href',                '<svg><set attributeName="href" to="javascript:alert(1)"/></svg>', '<set'],
    ['onerror con / come separatore',  '<img src="x"/onerror="alert(1)">',                   'onerror'],
    ['svg/onload',                    '<svg/onload=alert(1)>',                                'onload'],
    ['> dentro un attributo',          '<img alt=">" onerror=alert(1)>',                        'onerror'],
    ['javascript: con entità esadecimale', '<a href="jav&#x61;script:alert(1)">x</a>',          'jav&#x61;script'],
    ['javascript: con tab codificato', '<a href="java&#9;script:alert(1)">x</a>',              'java&#9;script'],
    ['javascript: con &colon;',         '<a href="javascript&colon;alert(1)">x</a>',             '&colon;'],
    ['entità senza punto e virgola',    '<a href="&#106&#97&#118&#97&#115&#99&#114&#105&#112&#116&#58alert(1)">x</a>', '&#106'],
    ['vbscript:',                      '<a href="vbscript:msgbox(1)">x</a>',                    'vbscript'],
    ['data: in href',                  '<a href="data:text/html,<script>alert(1)</script>">x</a>', 'data:'],
    ['xlink:href javascript',          '<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>', 'javascript'],
    ['img data:image/svg+xml',         '<img src="data:image/svg+xml;base64,PHN2Zz4=">',        'svg+xml'],
    ['virgolette non chiuse',          '<img src=x onerror=alert(1) title="',                   'onerror='],
    ['virgolette non chiuse + js',     '<a href=javascript:alert(1) title=\'',                    'javascript:'],
];
foreach ($cases as [$name, $in, $needle]) {
    check('B', $name . ' rimosso', stripos(ocms_sanitize_html($in), $needle) === false);
}

echo "\n" . str_repeat('─', 52) . "\n";
printf("passati: %d   falliti: %d\n", $pass, $fail);
if ($failed) { echo "\nFALLITI:\n"; foreach ($failed as $f) echo "  - $f\n"; }
exit($fail > 0 ? 1 : 0);
