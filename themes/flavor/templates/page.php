<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config['language'] ?? 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ocms_escape($page['meta']['title'] ?? $page['title']) ?> — <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></title>
    <meta name="description" content="<?= ocms_escape($page['meta']['description'] ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/flavor/assets/css/style.css">
</head>
<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <main class="site-main">
        <article class="page-content">
            <div class="container">
                <h1><?= ocms_escape($page['title']) ?></h1>
                <div class="content">
                    <?= $page['content'] ?>
                </div>
            </div>
        </article>
    </main>

    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
