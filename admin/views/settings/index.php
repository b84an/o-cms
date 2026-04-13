<?php
$pageTitle = 'Impostazioni';
$activeMenu = 'settings';
$seo = $config['seo_global'] ?? ['robots' => "User-agent: *\nAllow: /", 'head_scripts' => '', 'body_scripts' => ''];
$smtp = $config['smtp'] ?? ['host'=>'','port'=>587,'encryption'=>'tls','username'=>'','password'=>'','from_email'=>'','from_name'=>''];
ob_start();
?>

<form method="POST" action="<?= ocms_base_url() ?>/admin/settings/save">
<div class="page-header">
    <h1>Impostazioni</h1>
    <button type="submit" class="btn btn-primary">Salva Impostazioni</button>
</div>
    <?= ocms_csrf_field() ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
        <!-- Colonna sinistra -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Generale</h3>
                <div class="form-group">
                    <label>Nome Sito</label>
                    <input type="text" name="site_name" class="form-input" value="<?= ocms_escape($config['site_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Descrizione Sito</label>
                    <textarea name="site_description" class="form-textarea" rows="2"><?= ocms_escape($config['site_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>URL Base</label>
                    <input type="text" name="site_url" class="form-input" value="<?= ocms_escape($config['site_url'] ?? '') ?>" placeholder="/s">
                    <div class="form-hint">Percorso base senza trailing slash</div>
                </div>
                <div class="form-group">
                    <label>Email Admin</label>
                    <input type="email" name="admin_email" class="form-input" value="<?= ocms_escape($config['admin_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="feature-option" style="padding:10px 14px;margin:0;">
                        <input type="checkbox" name="maintenance_mode" <?= !empty($config['maintenance_mode']) ? 'checked' : '' ?>>
                        <div class="feat-info">
                            <span class="feat-title">Modalità Manutenzione</span>
                            <span class="feat-desc" style="font-size:0.75rem;color:var(--text-muted);">Il sito mostra un avviso di manutenzione ai visitatori</span>
                        </div>
                    </label>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="feature-option" style="padding:10px 14px;margin:0;">
                        <input type="checkbox" name="registration_enabled" <?= !empty($config['registration_enabled']) ? 'checked' : '' ?>>
                        <div class="feat-info">
                            <span class="feat-title">Registrazione Utenti</span>
                            <span class="feat-desc" style="font-size:0.75rem;color:var(--text-muted);">Abilita la registrazione pubblica con attivazione via email</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Layout</h3>
                <div class="form-group">
                    <label>Larghezza Sito</label>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="number" name="site_width_value" id="site-width-value" class="form-input" style="width:120px;"
                            value="<?= ocms_escape($config['site_width_value'] ?? '90') ?>" min="1">
                        <select name="site_width_unit" id="site-width-unit" class="form-select" style="width:80px;">
                            <option value="%" <?= ($config['site_width_unit'] ?? '%') === '%' ? 'selected' : '' ?>>%</option>
                            <option value="px" <?= ($config['site_width_unit'] ?? '') === 'px' ? 'selected' : '' ?>>px</option>
                        </select>
                    </div>
                    <div class="form-hint">Larghezza massima del contenitore principale (default: 90%)</div>
                    <div id="site-width-preview" style="margin-top:8px;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:6px;font-family:monospace;font-size:0.82rem;color:var(--text-muted);">
                        Anteprima: max-width: <?= ocms_escape(($config['site_width_value'] ?? '90') . ($config['site_width_unit'] ?? '%')) ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Localizzazione</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Lingua</label>
                        <select name="language" class="form-select">
                            <option value="it" <?= ($config['language'] ?? '') === 'it' ? 'selected' : '' ?>>Italiano</option>
                            <option value="en" <?= ($config['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="fr" <?= ($config['language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="de" <?= ($config['language'] ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                            <option value="es" <?= ($config['language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fuso Orario</label>
                        <input type="text" name="timezone" class="form-input" value="<?= ocms_escape($config['timezone'] ?? 'Europe/Rome') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Formato Data</label>
                        <input type="text" name="date_format" class="form-input" value="<?= ocms_escape($config['date_format'] ?? 'd/m/Y') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Articoli per Pagina</label>
                        <input type="number" name="posts_per_page" class="form-input" value="<?= (int)($config['posts_per_page'] ?? 10) ?>" min="1" max="100">
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonna destra -->
        <div>
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Tema</h3>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Tema Attivo</label>
                    <select name="theme" class="form-select">
                        <?php
                        $themesDir = ocms_base_path() . '/themes';
                        foreach (scandir($themesDir) as $t) {
                            if ($t === '.' || $t === '..' || !is_dir($themesDir.'/'.$t)) continue;
                            $selected = ($config['theme'] ?? 'flavor') === $t ? 'selected' : '';
                            echo "<option value=\"".ocms_escape($t)."\" $selected>".ocms_escape(ucfirst($t))."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Email</h3>
                <?php $mailMethod = $smtp['method'] ?? 'smtp'; ?>
                <div class="form-group">
                    <label>Metodo di Invio</label>
                    <select name="smtp_method" class="form-select" id="mail-method" onchange="toggleSmtpFields()">
                        <option value="smtp" <?= $mailMethod === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                        <option value="php_mail" <?= $mailMethod === 'php_mail' ? 'selected' : '' ?>>PHP mail()</option>
                    </select>
                    <div class="form-hint">SMTP è consigliato per affidabilità. PHP mail() usa la configurazione del server.</div>
                </div>
                <div id="smtp-fields" style="<?= $mailMethod === 'php_mail' ? 'display:none;' : '' ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Host SMTP</label>
                            <input type="text" name="smtp_host" class="form-input" value="<?= ocms_escape($smtp['host'] ?? '') ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="form-group" style="max-width:100px;">
                            <label>Porta</label>
                            <input type="number" name="smtp_port" class="form-input" value="<?= (int)($smtp['port'] ?? 587) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Crittografia</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="tls" <?= ($smtp['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($smtp['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Nessuna</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Username SMTP</label>
                        <input type="text" name="smtp_username" class="form-input" value="<?= ocms_escape($smtp['username'] ?? '') ?>" placeholder="user@gmail.com">
                    </div>
                    <div class="form-group">
                        <label>Password SMTP</label>
                        <input type="password" name="smtp_password" class="form-input" value="<?= ocms_escape($smtp['password'] ?? '') ?>" placeholder="••••••••">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Email Mittente</label>
                        <input type="email" name="smtp_from_email" class="form-input" value="<?= ocms_escape($smtp['from_email'] ?? '') ?>" placeholder="noreply@sito.com">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Nome Mittente</label>
                        <input type="text" name="smtp_from_name" class="form-input" value="<?= ocms_escape($smtp['from_name'] ?? '') ?>" placeholder="Il Mio Sito">
                    </div>
                </div>
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="email" id="test-email-addr" class="form-input" placeholder="Email destinatario test" value="<?= ocms_escape($config['admin_email'] ?? '') ?>" style="flex:1;">
                        <button type="button" class="btn btn-secondary" id="test-email-btn" onclick="sendTestEmail()">Invia Test</button>
                    </div>
                    <div id="test-email-result" style="margin-top:8px;font-size:0.85rem;"></div>
                    <div class="form-hint" style="margin-top:4px;">Salva prima le impostazioni, poi invia il test per verificare la configurazione.</div>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">SEO Globale</h3>
                <div class="form-group">
                    <label>robots.txt</label>
                    <textarea name="robots" class="form-textarea" rows="4" style="font-family:monospace;font-size:0.85rem;"><?= ocms_escape($seo['robots'] ?? '') ?></textarea>
                </div>
                <div class="form-hint" style="margin-bottom:12px;">La sitemap viene generata automaticamente al salvataggio.</div>
            </div>

            <?php
            $aiConfig = $config['ai'] ?? [];
            $aiProvider = $aiConfig['provider'] ?? 'none';
            $aiKey = $aiConfig['api_key'] ?? '';
            $aiModel = $aiConfig['model'] ?? '';
            $aiInstructions = $aiConfig['instructions'] ?? $config['ai_instructions'] ?? '';
            $defaultInstructions = "Scrivi come un essere umano brillante, non come un'intelligenza artificiale. Il tono deve essere coinvolgente, chiaro, intelligente, con un filo di ironia sottile ma senza esagerare.";
            ?>
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><path d="M12 2a4 4 0 0 1 4 4c0 1.95-1.4 3.58-3.25 3.93L12 22"/><path d="M8 6a4 4 0 0 1 8 0"/><path d="M17 12.5c1.77.77 3 2.53 3 4.5a5 5 0 0 1-10 0"/><path d="M4 17a5 5 0 0 1 3-4.5"/></svg>
                    Intelligenza Artificiale
                </h3>
                <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                    Scegli un provider per generare contenuti dall'editor. Serve una API key del provider scelto.
                </p>

                <?php $aiCliScript = $config['ai_cli_script'] ?? ''; ?>
                <div class="form-group">
                    <label>Provider AI</label>
                    <select name="ai_provider" id="ai-provider" class="form-select" onchange="toggleAiFields()">
                        <option value="none" <?= $aiProvider==='none'?'selected':'' ?>>Nessuno (disattivato)</option>
                        <option value="cli" <?= $aiProvider==='cli'?'selected':'' ?>>Claude Code (locale via CLI)</option>
                        <option value="anthropic" <?= $aiProvider==='anthropic'?'selected':'' ?>>Claude (Anthropic API)</option>
                        <option value="openai" <?= $aiProvider==='openai'?'selected':'' ?>>GPT (OpenAI)</option>
                        <option value="google" <?= $aiProvider==='google'?'selected':'' ?>>Gemini (Google)</option>
                        <option value="mistral" <?= $aiProvider==='mistral'?'selected':'' ?>>Mistral</option>
                        <option value="groq" <?= $aiProvider==='groq'?'selected':'' ?>>Groq (LLaMA)</option>
                    </select>
                </div>

                <!-- Campi CLI -->
                <div id="ai-cli-fields" style="<?= $aiProvider==='cli'?'':'display:none;' ?>">
                    <div class="form-group">
                        <label>Path script CLI</label>
                        <input type="text" name="ai_cli_script" class="form-input" value="<?= ocms_escape($aiCliScript) ?>" placeholder="/home/user/bin/claude-generate.sh">
                        <div class="form-hint">Script bash che riceve un file con il prompt e stampa il risultato su stdout. Nessuna API key necessaria.</div>
                    </div>
                </div>

                <!-- Campi API -->
                <div id="ai-fields" style="<?= !in_array($aiProvider, ['none','cli'])?'':'display:none;' ?>">
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" name="ai_api_key" class="form-input" value="<?= $aiKey ? '' : '' ?>" placeholder="<?= $aiKey ? 'Chiave gia configurata (lascia vuoto per mantenerla)' : 'Inserisci la tua API key' ?>">
                        <?php if ($aiKey): ?>
                        <div class="form-hint" style="color:var(--success);">&#10003; API key configurata. Lascia vuoto per mantenerla, compila per cambiarla.</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Modello <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opzionale)</span></label>
                        <input type="text" name="ai_model" id="ai-model" class="form-input" value="<?= ocms_escape($aiModel) ?>" placeholder="">
                        <div class="form-hint" id="ai-model-hint">Lascia vuoto per usare il modello predefinito</div>
                    </div>
                </div>

                <!-- Stile (per tutti tranne none) -->
                <div id="ai-style-fields" style="<?= $aiProvider==='none'?'display:none;':'' ?>">
                    <div class="form-group">
                        <label>Stile di scrittura</label>
                        <textarea name="ai_instructions" class="form-textarea" rows="5" style="font-size:0.85rem;line-height:1.6;"
                                  placeholder="Descrivi come l'AI deve scrivere i contenuti..."><?= ocms_escape($aiInstructions ?: $defaultInstructions) ?></textarea>
                        <div class="form-hint">Queste istruzioni vengono inviate all'AI ogni volta che usi "Genera" nell'editor.</div>
                    </div>

                    <!-- Test connessione -->
                    <div class="form-group" style="margin-bottom:0;">
                        <div style="display:flex;gap:10px;align-items:center;">
                            <button type="button" id="btn-test-ai" class="btn btn-secondary btn-sm" onclick="testAiConnection()">Testa connessione AI</button>
                            <span id="ai-test-result" style="font-size:0.85rem;"></span>
                        </div>
                        <div class="form-hint" style="margin-top:6px;">Salva prima le impostazioni, poi testa. Invia un prompt di prova per verificare che tutto funzioni.</div>
                    </div>
                </div>
            </div>
            <script>
            function toggleAiFields() {
                const p = document.getElementById('ai-provider').value;
                const apiFields = document.getElementById('ai-fields');
                const cliFields = document.getElementById('ai-cli-fields');
                const styleFields = document.getElementById('ai-style-fields');
                const modelInput = document.getElementById('ai-model');
                const hint = document.getElementById('ai-model-hint');

                cliFields.style.display = p === 'cli' ? '' : 'none';
                apiFields.style.display = (!['none','cli'].includes(p)) ? '' : 'none';
                styleFields.style.display = p === 'none' ? 'none' : '';

                const defaults = {anthropic:'claude-sonnet-4-20250514',openai:'gpt-4o',google:'gemini-2.0-flash',mistral:'mistral-large-latest',groq:'llama-3.3-70b-versatile'};
                if (defaults[p]) {
                    modelInput.placeholder = defaults[p];
                    hint.textContent = 'Lascia vuoto per usare ' + defaults[p];
                }
            }

            async function testAiConnection() {
                const btn = document.getElementById('btn-test-ai');
                const result = document.getElementById('ai-test-result');
                btn.disabled = true;
                btn.textContent = 'Test in corso...';
                result.textContent = '';
                result.style.color = '';

                try {
                    const res = await fetch('<?= ocms_base_url() ?>/admin/settings/test-ai', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ _csrf_token: '<?= ocms_csrf_token() ?>' })
                    });
                    const data = await res.json();
                    if (data.success) {
                        result.textContent = '&#10003; ' + data.message;
                        result.style.color = 'var(--success, #22c55e)';
                        result.innerHTML = '&#10003; ' + data.message;
                    } else {
                        result.textContent = data.error || 'Errore sconosciuto';
                        result.style.color = 'var(--danger, #ef4444)';
                    }
                } catch (e) {
                    result.textContent = 'Errore di rete: ' + e.message;
                    result.style.color = 'var(--danger, #ef4444)';
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Testa connessione AI';
                }
            }
            </script>

            <div class="card">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Code Injection</h3>
                <div class="form-group">
                    <label>Script Head (analytics, meta, ecc.)</label>
                    <textarea name="head_scripts" class="form-textarea" rows="3" style="font-family:monospace;font-size:0.85rem;"
                              placeholder="<script>...</script>"><?= ocms_escape($seo['head_scripts'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Script Body (chat widget, ecc.)</label>
                    <textarea name="body_scripts" class="form-textarea" rows="3" style="font-family:monospace;font-size:0.85rem;"
                              placeholder="<script>...</script>"><?= ocms_escape($seo['body_scripts'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Salva Impostazioni</button>
    </div>
</form>

<script>
function toggleSmtpFields() {
    var method = document.getElementById('mail-method').value;
    document.getElementById('smtp-fields').style.display = method === 'smtp' ? '' : 'none';
}
function sendTestEmail() {
    var email = document.getElementById('test-email-addr').value.trim();
    var result = document.getElementById('test-email-result');
    var btn = document.getElementById('test-email-btn');
    if (!email) { result.innerHTML = '<span style="color:var(--error);">Inserisci un indirizzo email</span>'; return; }
    btn.disabled = true;
    btn.textContent = 'Invio...';
    result.innerHTML = '';
    fetch('<?= ocms_base_url() ?>/admin/settings/test-email', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({email: email, _csrf_token: '<?= ocms_csrf_token() ?>'})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            result.innerHTML = '<span style="color:var(--success);">Email inviata con successo a ' + email + '</span>';
        } else {
            result.innerHTML = '<span style="color:var(--error);">Errore: ' + (data.error || 'invio fallito') + '</span>';
        }
    })
    .catch(function() {
        result.innerHTML = '<span style="color:var(--error);">Errore di connessione</span>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = 'Invia Test';
    });
}

// Anteprima larghezza sito
(function() {
    const val = document.getElementById('site-width-value');
    const unit = document.getElementById('site-width-unit');
    const preview = document.getElementById('site-width-preview');
    if (!val || !unit || !preview) return;
    function update() {
        const v = val.value || '90';
        const u = unit.value || '%';
        preview.textContent = 'Anteprima: max-width: ' + v + u;
    }
    val.addEventListener('input', update);
    unit.addEventListener('change', update);
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
