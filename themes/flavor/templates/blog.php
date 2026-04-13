<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config['language'] ?? 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog — <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/flavor/assets/css/style.css">
</head>
<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <main class="site-main">
        <section class="hero" style="padding:60px 0 40px;">
            <div class="container"><h1>Blog</h1></div>
        </section>
        <section style="padding:0 0 80px;">
            <div class="container">

                <?php
                $cats = $categories ?? [];
                $currentSort = $sort ?? 'date';
                $currentCat = $filterCat ?? '';
                ?>
                <?php if (!empty($cats)): ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:32px;flex-wrap:wrap;">
                    <a href="<?= ocms_base_url() ?>/blog?sort=<?= $currentSort ?>" style="padding:6px 16px;border-radius:20px;font-size:0.8rem;font-weight:600;<?= !$currentCat ? 'background:var(--primary);color:#fff;' : 'background:var(--bg-light);color:var(--text-muted);border:1px solid var(--border);' ?>text-decoration:none;">Tutti</a>
                    <?php foreach ($cats as $cat): ?>
                    <a href="<?= ocms_base_url() ?>/blog?cat=<?= ocms_escape($cat['slug']) ?>&sort=<?= $currentSort ?>" style="padding:6px 16px;border-radius:20px;font-size:0.8rem;font-weight:600;<?= $currentCat === $cat['slug'] ? 'background:var(--primary);color:#fff;' : 'background:var(--bg-light);color:var(--text-muted);border:1px solid var(--border);' ?>text-decoration:none;"><?= ocms_escape($cat['name']) ?></a>
                    <?php endforeach; ?>
                    <span style="flex:1;"></span>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <a href="<?= ocms_base_url() ?>/blog?sort=date<?= $currentCat ? '&cat='.$currentCat : '' ?>" title="Per data" style="padding:5px 10px;border-radius:6px;font-size:0.75rem;<?= $currentSort === 'date' ? 'background:var(--primary);color:#fff;' : 'background:var(--bg-light);color:var(--text-muted);border:1px solid var(--border);' ?>text-decoration:none;">Data</a>
                        <a href="<?= ocms_base_url() ?>/blog?sort=alpha<?= $currentCat ? '&cat='.$currentCat : '' ?>" title="Alfabetico" style="padding:5px 10px;border-radius:6px;font-size:0.75rem;<?= $currentSort === 'alpha' ? 'background:var(--primary);color:#fff;' : 'background:var(--bg-light);color:var(--text-muted);border:1px solid var(--border);' ?>text-decoration:none;">A-Z</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($articles)): ?>
                    <p style="color:var(--text-muted);text-align:center;">Nessun articolo pubblicato.</p>
                <?php else: ?>
                    <div class="articles-grid">
                        <?php foreach ($articles as $a):
                            $wordCount = str_word_count(strip_tags($a['content'] ?? ''));
                            $readTime = max(1, round($wordCount / 200));
                        ?>
                        <article class="article-card">
                            <?php if (!empty($a['cover_image'])): ?>
                                <div class="article-cover" style="background-image:url('<?= ocms_base_url() . ocms_escape($a['cover_image']) ?>');"></div>
                            <?php endif; ?>
                            <div class="article-body">
                                <?php if (!empty($a['category'])): ?>
                                    <span class="article-cat"><?= ocms_escape($a['category']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($a['featured'])): ?>
                                    <span style="display:inline-block;font-size:0.65rem;font-weight:700;color:#f59e0b;text-transform:uppercase;letter-spacing:0.5px;margin-left:8px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2" style="vertical-align:-1px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        In evidenza
                                    </span>
                                <?php endif; ?>
                                <h2><a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($a['slug']) ?>"><?= ocms_escape($a['title']) ?></a></h2>
                                <?php if (!empty($a['excerpt'])): ?>
                                    <p><?= ocms_escape(ocms_truncate($a['excerpt'], 120)) ?></p>
                                <?php endif; ?>
                                <div class="article-meta" style="display:flex;align-items:center;justify-content:space-between;">
                                    <span><?= ocms_format_date($a['created_at'], 'd M Y') ?> · <?= $readTime ?> min</span>
                                    <a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($a['slug']) ?>" style="display:inline-block;padding:5px 14px;background:rgba(99,102,241,0.12);color:var(--primary-light);border-radius:6px;font-size:0.8rem;font-weight:600;text-decoration:none;transition:background .15s;">Leggi tutto &rarr;</a>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>
</body>
</html>
