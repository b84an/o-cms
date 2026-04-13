<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config['language'] ?? 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricerca<?= $query ? ': ' . ocms_escape($query) : '' ?> — <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/flavor/assets/css/style.css">
    <style>
        .search-page { padding: 60px 0 80px; }
        .search-form-big { position:relative; margin-bottom:40px; }
        .search-form-big input {
            width:100%; padding:18px 20px 18px 52px;
            background:var(--bg-light); border:1px solid var(--border);
            border-radius:14px; color:var(--text); font-size:1.15rem; font-family:inherit; outline:none;
            transition:border-color 0.2s, box-shadow 0.2s;
        }
        .search-form-big input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,0.1); }
        .search-form-big .icon { position:absolute; left:18px; top:50%; transform:translateY(-50%); opacity:0.4; font-size:1.2rem; }
        .search-stats { color:var(--text-muted); margin-bottom:24px; font-size:0.95rem; }
        .search-result {
            display:block; padding:24px; background:var(--bg-light);
            border:1px solid var(--border); border-radius:14px;
            margin-bottom:14px; text-decoration:none; color:var(--text);
            transition:border-color 0.2s, transform 0.15s, box-shadow 0.15s;
        }
        .search-result:hover { border-color:rgba(99,102,241,0.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.15); color:var(--text); }
        .search-result .type-badge {
            display:inline-block; padding:3px 10px; border-radius:6px;
            font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
            background:rgba(99,102,241,0.1); color:var(--primary-light); margin-bottom:10px;
        }
        .search-result h2 { font-size:1.15rem; font-weight:700; margin-bottom:8px; line-height:1.4; }
        .search-result h2 mark, .search-result .snippet mark {
            background:rgba(99,102,241,0.2); color:var(--primary-light); border-radius:3px; padding:1px 3px;
        }
        .search-result .snippet { font-size:0.9rem; color:var(--text-muted); line-height:1.6; }
        .search-result .date { font-size:0.8rem; color:var(--text-muted); margin-top:10px; opacity:0.7; }
        .search-empty { text-align:center; padding:80px 0; }
        .search-empty .icon { font-size:3rem; opacity:0.15; margin-bottom:16px; }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <main class="site-main">
        <div class="search-page">
            <div class="container">
                <h1 style="font-size:2.2rem;font-weight:800;margin-bottom:28px;">Ricerca</h1>

                <form method="GET" action="<?= ocms_base_url() ?>/search" class="search-form-big">
                    <span class="icon">&#128269;</span>
                    <input type="text" name="q" value="<?= ocms_escape($query) ?>" placeholder="Cerca pagine, articoli..." autofocus>
                </form>

                <?php if ($query): ?>
                    <?php if (!empty($result['results'])): ?>
                        <p class="search-stats">
                            <strong><?= $result['total'] ?></strong> risultat<?= $result['total'] === 1 ? 'o' : 'i' ?>
                            per "<strong><?= ocms_escape($query) ?></strong>"
                        </p>

                        <?php foreach ($result['results'] as $r): ?>
                        <a href="<?= ocms_escape($r['url'] ?? '#') ?>" class="search-result">
                            <span class="type-badge"><?= ocms_escape($r['type_label'] ?? $r['type']) ?></span>
                            <h2><?= $r['highlights']['title'] ?? ocms_escape($r['title']) ?></h2>
                            <?php if (!empty($r['highlights']['excerpt'])): ?>
                                <div class="snippet"><?= $r['highlights']['excerpt'] ?></div>
                            <?php elseif (!empty($r['highlights']['content'])): ?>
                                <div class="snippet"><?= $r['highlights']['content'] ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['date'])): ?>
                                <div class="date"><?= ocms_format_date($r['date'], 'd M Y') ?></div>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div class="search-empty">
                            <div class="icon">&#128269;</div>
                            <p style="font-size:1.1rem;margin-bottom:8px;">Nessun risultato per "<strong><?= ocms_escape($query) ?></strong>"</p>
                            <p style="color:var(--text-muted);">Prova con parole diverse o controlla l'ortografia.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="search-empty">
                        <div class="icon">&#128269;</div>
                        <p style="color:var(--text-muted);">Digita qualcosa per cercare nel sito.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
