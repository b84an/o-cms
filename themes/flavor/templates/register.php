<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config['language'] ?? 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione — <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --bg: #0f172a;
            --bg-card: #1e293b;
            --bg-input: #334155;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #475569;
            --success: #22c55e;
            --error: #ef4444;
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(ellipse at 30% 50%, rgba(99,102,241,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 70% 50%, rgba(139,92,246,0.06) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
            z-index: -1;
        }

        @keyframes bgShift {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-5%, 5%) rotate(3deg); }
        }

        .register-container {
            width: 100%;
            max-width: 460px;
            padding: 20px;
            margin: 40px auto;
        }

        .register-card {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        .register-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .register-logo h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-light), #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .register-logo p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        .form-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .captcha-box {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            text-align: center;
            margin-bottom: 18px;
        }

        .captcha-question {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-light);
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .captcha-box input {
            width: 120px;
            padding: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 1.1rem;
            text-align: center;
            outline: none;
        }

        .captcha-box input:focus {
            border-color: var(--primary);
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.35);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .flash-message {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 18px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .flash-message.error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
        }

        .flash-message.success {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #86efac;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: var(--primary-light);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-logo">
                <h1><?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></h1>
                <p>Crea il tuo account</p>
            </div>

            <?php foreach (ocms_flash_get() as $flash): ?>
                <div class="flash-message <?= ocms_escape($flash['type']) ?>">
                    <?= ocms_escape($flash['message']) ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="<?= ocms_base_url() ?>/register">
                <?= ocms_csrf_field() ?>

                <div class="form-group">
                    <label for="display_name">Nome Completo</label>
                    <input type="text" id="display_name" name="display_name" placeholder="Mario Rossi" required>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="mario-rossi" required
                           pattern="[a-z0-9_-]+" title="Solo lettere minuscole, numeri, - e _">
                    <div class="form-hint">Solo lettere minuscole, numeri, trattini e underscore</div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="mario@esempio.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Minimo 6 caratteri" required minlength="6">
                </div>

                <div class="captcha-box">
                    <div class="captcha-question"><?= ocms_escape($captcha['question']) ?></div>
                    <input type="text" name="captcha" placeholder="?" required autocomplete="off">
                    <div class="form-hint" style="margin-top:8px;">Risolvi l'operazione per dimostrare che non sei un robot</div>
                </div>

                <button type="submit" class="btn-register">Registrati</button>
            </form>

            <div class="login-link">
                <a href="<?= ocms_base_url() ?>/admin/login">Hai già un account? Accedi</a>
            </div>
        </div>
    </div>
</body>
</html>
