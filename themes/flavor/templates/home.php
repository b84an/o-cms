<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config['language'] ?? 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></title>
    <meta name="description" content="<?= ocms_escape($app->config['site_description'] ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/flavor/assets/css/style.css">
</head>
<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <main class="site-main">
        <section class="hero">
            <div class="container">
                <h1><?= isset($page) ? ocms_escape($page['title']) : 'Benvenuto' ?></h1>
                <div class="content">
                    <?= $page['content'] ?? '<p>Il tuo sito è pronto. Inizia a creare contenuti dal pannello admin.</p>' ?>
                </div>
            </div>
        </section>

        <?php $featuredList = $featured ?? []; ?>
        <?php if (!empty($featuredList)): ?>
        <section style="padding:0 0 80px;">
            <div class="container">
                <h2 style="font-size:1.6rem;font-weight:800;margin-bottom:24px;text-align:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    In evidenza
                </h2>
                <div class="articles-grid">
                    <?php foreach ($featuredList as $a):
                        $readTime = max(1, round(str_word_count(strip_tags($a['content'] ?? '')) / 200));
                    ?>
                    <article class="article-card">
                        <?php if (!empty($a['cover_image'])): ?>
                            <div class="article-cover" style="background-image:url('<?= ocms_base_url() . ocms_escape($a['cover_image']) ?>');"></div>
                        <?php endif; ?>
                        <div class="article-body">
                            <?php if (!empty($a['category'])): ?>
                                <span class="article-cat"><?= ocms_escape($a['category']) ?></span>
                            <?php endif; ?>
                            <h2><a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($a['slug']) ?>"><?= ocms_escape($a['title']) ?></a></h2>
                            <?php if (!empty($a['excerpt'])): ?>
                                <p><?= ocms_escape(ocms_truncate($a['excerpt'], 120)) ?></p>
                            <?php endif; ?>
                            <div class="article-meta" style="display:flex;align-items:center;justify-content:space-between;">
                                <span><?= ocms_format_date($a['created_at'], 'd M Y') ?> · <?= $readTime ?> min</span>
                                <a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($a['slug']) ?>" style="display:inline-block;padding:5px 14px;background:rgba(99,102,241,0.12);color:var(--primary-light);border-radius:6px;font-size:0.8rem;font-weight:600;text-decoration:none;">Leggi tutto &rarr;</a>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
