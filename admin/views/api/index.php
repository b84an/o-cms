<?php
$pageTitle = 'API';
$activeMenu = 'api';
$userRole = $app->auth->user()['role'] ?? 'registered';
$baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ocms_base_url();
ob_start();
?>

<div class="page-header">
    <h1>API REST</h1>
</div>

<!-- ═══ TOKEN MANAGEMENT ═══ -->
<div class="card" style="margin-bottom:24px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">I tuoi Token API</h3>
    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
        I token ereditano i permessi del tuo ruolo (<strong><?= ocms_escape(Auth::getRoleLabels()[$userRole] ?? $userRole) ?></strong>).
        Il token viene mostrato solo al momento della creazione — salvalo subito.
    </p>

    <!-- Crea token -->
    <div style="display:flex;gap:10px;margin-bottom:16px;">
        <input type="text" id="token-name" class="form-input" placeholder="Nome token (es. App Mobile, Script Import...)" style="flex:1;">
        <button type="button" class="btn btn-primary" onclick="createToken()">Genera Token</button>
    </div>

    <!-- Risultato creazione -->
    <div id="new-token-result" style="display:none;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);border-radius:var(--radius);padding:14px;margin-bottom:16px;">
        <div style="font-weight:600;color:var(--success);margin-bottom:6px;">Token creato! Copialo ora, non verrà più mostrato.</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <code id="new-token-value" style="flex:1;background:var(--bg);padding:10px 12px;border-radius:8px;font-size:0.85rem;word-break:break-all;"></code>
            <button type="button" class="btn btn-secondary btn-sm" onclick="copyToken()">Copia</button>
        </div>
    </div>

    <!-- Lista token esistenti -->
    <div class="table-wrapper">
        <table class="data-table" id="tokens-table">
            <thead>
                <tr><th>Nome</th><th>Token</th><th>Creato</th><th>Ultimo uso</th><th>Azioni</th></tr>
            </thead>
            <tbody>
                <?php if (empty($tokens)): ?>
                <tr id="no-tokens-row"><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">Nessun token creato</td></tr>
                <?php else: foreach ($tokens as $t): ?>
                <tr id="token-row-<?= ocms_escape($t['id']) ?>">
                    <td style="font-weight:600;"><?= ocms_escape($t['name']) ?></td>
                    <td><code style="font-size:0.8rem;color:var(--text-muted);"><?= ocms_escape($t['token_hint']) ?></code></td>
                    <td style="font-size:0.85rem;color:var(--text-muted);"><?= ocms_format_date($t['created_at'], 'd/m/Y H:i') ?></td>
                    <td style="font-size:0.85rem;color:var(--text-muted);"><?= $t['last_used'] ? ocms_format_date($t['last_used'], 'd/m/Y H:i') : 'Mai' ?></td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm" onclick="revokeToken('<?= ocms_escape($t['id']) ?>')">Revoca</button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ DOCUMENTAZIONE API ═══ -->
<div class="card" style="margin-bottom:24px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Autenticazione</h3>
    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:12px;">Tutte le richieste API richiedono un header <code>Authorization</code>:</p>
    <pre style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:0.85rem;overflow-x:auto;margin-bottom:12px;"><code>Authorization: Bearer <span style="color:var(--primary-light);">IL_TUO_TOKEN</span></code></pre>
    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:8px;"><strong>Esempio con curl:</strong></p>
    <pre style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:0.82rem;overflow-x:auto;"><code>curl -H "Authorization: Bearer IL_TUO_TOKEN" \
     <?= ocms_escape($baseUrl) ?>/admin/api/pages</code></pre>
    <p style="color:var(--text-muted);font-size:0.82rem;margin-top:10px;">Le risposte sono in formato JSON. In caso di errore: <code>{"error": "messaggio"}</code></p>
</div>

<?php
// Definizione endpoint API
$endpoints = [
    'Lettura (registered+)' => [
        ['GET', '/api/pages', 'Lista pagine pubblicate', 'registered'],
        ['GET', '/api/pages/{slug}', 'Dettaglio pagina pubblica', 'registered'],
        ['GET', '/api/articles', 'Lista articoli pubblicati', 'registered'],
        ['GET', '/api/articles/{slug}', 'Dettaglio articolo pubblicato', 'registered'],
        ['GET', '/api/categories', 'Lista categorie', 'registered'],
        ['GET', '/api/menus/{name}', 'Menu per nome', 'registered'],
        ['GET', '/api/media', 'Lista media', 'registered'],
        ['GET', '/api/config', 'Configurazione sito (senza dati sensibili)', 'registered'],
    ],
    'Articoli (publisher+)' => [
        ['POST', '/api/articles', 'Crea articolo', 'publisher', '{"title":"...","content":"...","status":"draft","excerpt":"","category":"","tags":[],"cover_image":"","gallery":[{"url":"/uploads/gallery/img.jpg","thumb":"/uploads/gallery/thumb-img.jpg"}],"publish_at":"2026-04-01T10:00:00+02:00","meta":{"title":"","description":""}}'],
        ['POST', '/api/articles/{slug}', 'Aggiorna articolo (publisher: solo propri)', 'publisher', '{"title":"...","content":"...","status":"published","publish_at":"","gallery":[]}'],
        ['POST', '/api/articles/{slug}/delete', 'Elimina articolo (publisher: solo propri)', 'publisher'],
    ],
    'Pagine (editor+)' => [
        ['POST', '/api/pages', 'Crea pagina', 'editor', '{"title":"...","content":"...","status":"draft","template":"default","layout":"none","meta":{"title":"","description":""}}'],
        ['POST', '/api/pages/{slug}', 'Aggiorna pagina', 'editor', '{"title":"...","content":"...","status":"published"}'],
        ['POST', '/api/pages/{slug}/delete', 'Elimina pagina', 'editor'],
    ],
    'Categorie (editor+)' => [
        ['POST', '/api/categories', 'Crea categoria', 'editor', '{"name":"...","description":"","parent":null}'],
        ['POST', '/api/categories/{slug}/delete', 'Elimina categoria', 'editor'],
    ],
    'Commenti (editor+)' => [
        ['GET', '/api/comments', 'Lista commenti (filtri: ?status=pending|approved|rejected|all&article=slug)', 'editor'],
        ['POST', '/api/comments/{id}/approve', 'Approva commento', 'editor'],
        ['POST', '/api/comments/{id}/reject', 'Rifiuta commento', 'editor'],
        ['POST', '/api/comments/{id}/delete', 'Elimina commento', 'administrator'],
    ],
    'Analytics (administrator+)' => [
        ['GET', '/api/analytics', 'Statistiche visite (filtro: ?days=7|30|90, max 365)', 'administrator'],
    ],
    'Utenti (administrator+)' => [
        ['GET', '/api/users', 'Lista utenti (senza password/token)', 'administrator'],
        ['GET', '/api/users/{username}', 'Dettaglio utente', 'administrator'],
    ],
    'Impostazioni (super_administrator)' => [
        ['GET', '/api/settings', 'Leggi configurazione completa', 'super_administrator'],
        ['POST', '/api/settings', 'Aggiorna configurazione', 'super_administrator', '{"site_name":"...","language":"it","maintenance_mode":false}'],
    ],
];

$roleColors = [
    'registered' => '#22c55e',
    'publisher' => '#3b82f6',
    'editor' => '#f59e0b',
    'administrator' => '#f97316',
    'super_administrator' => '#ef4444',
];
$roleLabels = Auth::getRoleLabels();
?>

<?php foreach ($endpoints as $section => $routes): ?>
<div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;"><?= ocms_escape($section) ?></h3>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr><th style="width:70px;">Metodo</th><th>Endpoint</th><th>Descrizione</th><th style="width:120px;">Ruolo Min.</th></tr>
            </thead>
            <tbody>
                <?php foreach ($routes as $ep):
                    $method = $ep[0];
                    $path = $ep[1];
                    $desc = $ep[2];
                    $role = $ep[3];
                    $body = $ep[4] ?? null;
                    $methodColor = $method === 'GET' ? '#22c55e' : '#3b82f6';
                    $roleColor = $roleColors[$role] ?? '#94a3b8';
                    $canAccess = false;
                    $hierarchy = ['registered'=>1,'publisher'=>2,'editor'=>3,'administrator'=>4,'super_administrator'=>5];
                    $canAccess = ($hierarchy[$userRole] ?? 0) >= ($hierarchy[$role] ?? 0);
                ?>
                <tr style="<?= !$canAccess ? 'opacity:0.45;' : '' ?>">
                    <td>
                        <span style="background:<?= $methodColor ?>;color:white;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:700;"><?= $method ?></span>
                    </td>
                    <td>
                        <code style="font-size:0.82rem;">/admin<?= ocms_escape($path) ?></code>
                        <?php if ($body): ?>
                        <details style="margin-top:6px;">
                            <summary style="font-size:0.75rem;color:var(--primary-light);cursor:pointer;">Body JSON</summary>
                            <pre style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:8px;font-size:0.78rem;margin-top:4px;white-space:pre-wrap;"><code><?= ocms_escape($body) ?></code></pre>
                        </details>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.85rem;"><?= ocms_escape($desc) ?></td>
                    <td>
                        <span style="color:<?= $roleColor ?>;font-size:0.8rem;font-weight:600;"><?= ocms_escape($roleLabels[$role] ?? $role) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Note -->
<div class="card">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px;">Note</h3>
    <ul style="color:var(--text-muted);font-size:0.85rem;line-height:1.8;padding-left:18px;">
        <li>I <strong>publisher</strong> possono creare/modificare/eliminare solo i <em>propri</em> articoli. Gli <strong>editor+</strong> possono gestire tutti.</li>
        <li>Le risposte di errore hanno codice HTTP appropriato (400, 401, 403, 404) e body <code>{"error":"..."}</code>.</li>
        <li>Per le operazioni POST, invia il body come <code>Content-Type: application/json</code>.</li>
        <li>I campi non specificati nell'aggiornamento restano invariati.</li>
        <li>Gli endpoint grigi non sono accessibili con il tuo ruolo attuale.</li>
        <li>Il campo <code>status</code> accetta <code>"draft"</code> o <code>"published"</code>.</li>
        <li>Il campo <code>publish_at</code> accetta formato ISO 8601 (es. <code>2026-04-01T10:00:00+02:00</code>). Lasciare vuoto per pubblicazione immediata.</li>
        <li>Il campo <code>gallery</code> è un array di oggetti con <code>url</code> e <code>thumb</code> (URL relativi a immagini caricate).</li>
        <li>Gli articoli con <code>publish_at</code> futuro non appaiono nella lista pubblica fino alla data impostata.</li>
        <li>I commenti vengono creati dagli utenti frontend e richiedono moderazione (status: pending → approved/rejected).</li>
        <li>L'endpoint analytics restituisce dati aggregati: visite giornaliere, pagine top, referrer, distribuzione oraria.</li>
    </ul>
</div>

<script>
var CSRF = '<?= ocms_csrf_token() ?>';
var BASE = '<?= ocms_base_url() ?>';

function createToken() {
    var name = document.getElementById('token-name').value.trim();
    if (!name) { alert('Inserisci un nome per il token'); return; }

    fetch(BASE + '/admin/api-tokens/create', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name: name, _csrf_token: CSRF})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('new-token-value').textContent = data.token;
            document.getElementById('new-token-result').style.display = 'block';
            document.getElementById('token-name').value = '';
            // Ricarica pagina dopo 1s per aggiornare la tabella
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            alert('Errore: ' + (data.error || 'sconosciuto'));
        }
    })
    .catch(function() { alert('Errore di connessione'); });
}

function copyToken() {
    var val = document.getElementById('new-token-value').textContent;
    navigator.clipboard.writeText(val).then(function() {
        var btn = document.querySelector('#new-token-result .btn');
        btn.textContent = 'Copiato!';
        setTimeout(function() { btn.textContent = 'Copia'; }, 2000);
    });
}

function revokeToken(id) {
    if (!confirm('Revocare questo token? Le applicazioni che lo usano smetteranno di funzionare.')) return;

    fetch(BASE + '/admin/api-tokens/revoke', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token_id: id, _csrf_token: CSRF})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var row = document.getElementById('token-row-' + id);
            if (row) row.remove();
            // Se non ci sono più righe, mostra placeholder
            var tbody = document.querySelector('#tokens-table tbody');
            if (!tbody.querySelector('tr')) {
                tbody.innerHTML = '<tr id="no-tokens-row"><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">Nessun token creato</td></tr>';
            }
        } else {
            alert('Errore: ' + (data.error || 'sconosciuto'));
        }
    })
    .catch(function() { alert('Errore di connessione'); });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
