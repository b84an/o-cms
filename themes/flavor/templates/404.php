<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Pagina non trovata</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/flavor/assets/css/style.css">
</head>
<body>
    <main class="site-main" style="display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;">
        <div>
            <h1 style="font-size:6rem;font-weight:800;opacity:0.2;margin-bottom:0;">404</h1>
            <p style="font-size:1.3rem;color:var(--text-muted);margin-bottom:24px;">Pagina non trovata</p>
            <a href="<?= ocms_base_url() ?>/" class="btn-primary">Torna alla Home</a>
        </div>
    </main>
</body>
</html>
