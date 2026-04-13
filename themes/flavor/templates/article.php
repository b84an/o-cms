<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config['language'] ?? 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ocms_escape($article['meta']['title'] ?? $article['title']) ?> — <?= ocms_escape($app->config['site_name'] ?? 'O-CMS') ?></title>
    <meta name="description" content="<?= ocms_escape($article['meta']['description'] ?? $article['excerpt'] ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/flavor/assets/css/style.css">
    <style>
        /* Galleria Lightbox */
        .ocms-gallery { display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin:32px 0; }
        .ocms-gallery-item { aspect-ratio:4/3;border-radius:10px;overflow:hidden;cursor:pointer;border:1px solid var(--border);transition:transform .2s,box-shadow .2s; }
        .ocms-gallery-item:hover { transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.3); }
        .ocms-gallery-item img { width:100%;height:100%;object-fit:cover; }
        .ocms-lightbox { position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;backdrop-filter:blur(8px); }
        .ocms-lightbox.active { display:flex; }
        .ocms-lightbox img { max-width:90vw;max-height:85vh;border-radius:12px;box-shadow:0 0 60px rgba(0,0,0,.5); }
        .ocms-lb-close { position:absolute;top:20px;right:24px;width:40px;height:40px;border-radius:50%;border:none;background:rgba(255,255,255,.1);color:#fff;font-size:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s; }
        .ocms-lb-close:hover { background:rgba(255,255,255,.2); }
        .ocms-lb-nav { position:absolute;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:50%;border:none;background:rgba(255,255,255,.08);color:#fff;font-size:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s; }
        .ocms-lb-nav:hover { background:rgba(255,255,255,.15); }
        .ocms-lb-prev { left:20px; }
        .ocms-lb-next { right:20px; }
        .ocms-lb-counter { position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.6);font-size:.85rem; }
        /* Commenti */
        .ocms-comments { margin-top:48px;padding-top:32px;border-top:1px solid var(--border); }
        .ocms-comment { display:flex;gap:14px;padding:16px 0;border-bottom:1px solid var(--border); }
        .ocms-comment-avatar { width:40px;height:40px;border-radius:50%;flex-shrink:0;border:2px solid var(--border); }
        .ocms-comment-body { flex:1; }
        .ocms-comment-meta { font-size:.8rem;color:var(--text-muted);margin-bottom:6px;display:flex;gap:12px;align-items:center; }
        .ocms-comment-text { font-size:.9rem;color:var(--text-muted);line-height:1.6; }
        .ocms-reply { margin-left:54px;border-left:2px solid var(--border);padding-left:16px; }
        .ocms-comment-form input, .ocms-comment-form textarea { width:100%;padding:10px 14px;background:var(--bg-light,#1e293b);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:inherit;font-size:.9rem;margin-bottom:12px;outline:none; }
        .ocms-comment-form input:focus, .ocms-comment-form textarea:focus { border-color:var(--primary); }
        .ocms-comment-form textarea { resize:vertical;min-height:80px; }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/partials/header.php'; ?>

    <main class="site-main">
        <article class="page-content">
            <div class="container">
                <?php if (!empty($article['cover_image'])): ?>
                    <img src="<?= ocms_base_url() . ocms_escape($article['cover_image']) ?>" alt="<?= ocms_escape($article['title']) ?>"
                         style="width:100%;max-height:400px;object-fit:cover;border-radius:12px;margin-bottom:32px;">
                <?php endif; ?>

                <div style="margin-bottom:16px;">
                    <?php if (!empty($article['category'])): ?>
                        <span style="color:var(--primary-light);font-size:0.85rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;"><?= ocms_escape($article['category']) ?></span>
                    <?php endif; ?>
                </div>

                <h1><?= ocms_escape($article['title']) ?></h1>

                <?php $readTime = max(1, round(str_word_count(strip_tags($article['content'] ?? '')) / 200)); ?>
                <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:40px;display:flex;gap:16px;align-items:center;">
                    <span><?= ocms_format_date($article['created_at'], 'd M Y') ?></span>
                    <?php if (!empty($article['author'])): ?>
                        <span>di <?= ocms_escape($article['author']) ?></span>
                    <?php endif; ?>
                    <span style="display:inline-flex;align-items:center;gap:4px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= $readTime ?> min di lettura
                    </span>
                </div>

                <div class="content">
                    <?= $article['content'] ?>
                </div>

                <?php /* ─── GALLERIA ─── */ ?>
                <?php $gallery = $article['gallery'] ?? []; ?>
                <?php if (!empty($gallery)): ?>
                <div class="ocms-gallery">
                    <?php foreach ($gallery as $i => $img): ?>
                    <div class="ocms-gallery-item" onclick="openLightbox(<?= $i ?>)">
                        <img src="<?= ocms_base_url() . ocms_escape($img['thumb'] ?? $img['url']) ?>" alt="<?= ocms_escape($img['name'] ?? '') ?>" loading="lazy">
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Lightbox -->
                <div class="ocms-lightbox" id="lightbox">
                    <button class="ocms-lb-close" onclick="closeLightbox()">&times;</button>
                    <button class="ocms-lb-nav ocms-lb-prev" onclick="navLightbox(-1)">&#8249;</button>
                    <img id="lb-img" src="" alt="">
                    <button class="ocms-lb-nav ocms-lb-next" onclick="navLightbox(1)">&#8250;</button>
                    <div class="ocms-lb-counter"><span id="lb-counter"></span></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($article['tags'])): ?>
                <div style="margin-top:40px;padding-top:24px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap;">
                    <?php foreach ($article['tags'] as $tag): ?>
                        <span style="padding:4px 12px;background:rgba(99,102,241,0.1);border-radius:6px;font-size:0.8rem;color:var(--primary-light);"><?= ocms_escape($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php /* ─── COMMENTI ─── */ ?>
                <div class="ocms-comments" id="comments">
                    <h3 style="font-size:1.2rem;font-weight:700;margin-bottom:24px;">
                        Commenti
                        <?php $commentsList = $comments ?? []; ?>
                        <?php if (!empty($commentsList)): ?>
                        <span style="font-size:.85rem;font-weight:400;color:var(--text-muted);margin-left:8px;">(<?= count($commentsList) ?>)</span>
                        <?php endif; ?>
                    </h3>

                    <?php if (!empty($commentsList)): ?>
                        <?php
                        // Separa commenti root e risposte
                        $roots = array_filter($commentsList, fn($c) => empty($c['parent_id']));
                        $replies = [];
                        foreach ($commentsList as $c) {
                            if (!empty($c['parent_id'])) {
                                $replies[$c['parent_id']][] = $c;
                            }
                        }
                        ?>
                        <?php foreach ($roots as $c):
                            $hash = md5(strtolower(trim($c['author_email'] ?? '')));
                        ?>
                        <div class="ocms-comment">
                            <img src="https://www.gravatar.com/avatar/<?= $hash ?>?s=40&d=mp" class="ocms-comment-avatar" alt="">
                            <div class="ocms-comment-body">
                                <div class="ocms-comment-meta">
                                    <strong style="color:var(--text);"><?= ocms_escape($c['author_name']) ?></strong>
                                    <span><?= ocms_format_date($c['created_at'], 'd M Y, H:i') ?></span>
                                </div>
                                <div class="ocms-comment-text"><?= nl2br(ocms_escape($c['body'])) ?></div>
                                <button type="button" onclick="showReplyForm('<?= ocms_escape($c['id']) ?>')" style="background:none;border:none;color:var(--primary-light);font-size:.8rem;cursor:pointer;padding:6px 0;font-family:inherit;">Rispondi</button>

                                <?php /* Form risposta inline */ ?>
                                <div id="reply-<?= ocms_escape($c['id']) ?>" style="display:none;margin-top:12px;">
                                    <?php $captchaReply = ocms_captcha_generate(); ?>
                                    <form method="POST" action="<?= ocms_base_url() ?>/blog/<?= ocms_escape($article['slug']) ?>/comment" class="ocms-comment-form">
                                        <?= ocms_csrf_field() ?>
                                        <input type="hidden" name="parent_id" value="<?= ocms_escape($c['id']) ?>">
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                            <input type="text" name="author_name" placeholder="Nome *" required>
                                            <input type="email" name="author_email" placeholder="Email (opzionale)">
                                        </div>
                                        <textarea name="comment_body" placeholder="La tua risposta..." required></textarea>
                                        <div style="display:flex;gap:10px;align-items:center;">
                                            <label style="font-size:.85rem;color:var(--text-muted);white-space:nowrap;"><?= $captchaReply['question'] ?></label>
                                            <input type="text" name="captcha" required style="width:80px;" placeholder="?">
                                            <button type="submit" class="btn-primary" style="padding:8px 20px;border-radius:8px;font-size:.85rem;">Invia</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php /* Risposte */ ?>
                        <?php if (!empty($replies[$c['id']])): ?>
                            <?php foreach ($replies[$c['id']] as $r):
                                $rHash = md5(strtolower(trim($r['author_email'] ?? '')));
                            ?>
                            <div class="ocms-comment ocms-reply">
                                <img src="https://www.gravatar.com/avatar/<?= $rHash ?>?s=40&d=mp" class="ocms-comment-avatar" alt="">
                                <div class="ocms-comment-body">
                                    <div class="ocms-comment-meta">
                                        <strong style="color:var(--text);"><?= ocms_escape($r['author_name']) ?></strong>
                                        <span><?= ocms_format_date($r['created_at'], 'd M Y, H:i') ?></span>
                                    </div>
                                    <div class="ocms-comment-text"><?= nl2br(ocms_escape($r['body'])) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Form nuovo commento -->
                    <div style="margin-top:32px;">
                        <h4 style="font-size:1rem;font-weight:600;margin-bottom:16px;">Lascia un commento</h4>
                        <?php
                        foreach (ocms_flash_get() as $flash) {
                            $fClass = $flash['type'] === 'error' ? 'color:#ef4444;' : 'color:var(--success);';
                            echo '<p style="'.$fClass.'font-size:.85rem;margin-bottom:12px;">' . ocms_escape($flash['message']) . '</p>';
                        }
                        $captcha = ocms_captcha_generate();
                        ?>
                        <form method="POST" action="<?= ocms_base_url() ?>/blog/<?= ocms_escape($article['slug']) ?>/comment" class="ocms-comment-form">
                            <?= ocms_csrf_field() ?>
                            <input type="hidden" name="parent_id" value="">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <input type="text" name="author_name" placeholder="Nome *" required>
                                <input type="email" name="author_email" placeholder="Email (opzionale)">
                            </div>
                            <textarea name="comment_body" placeholder="Il tuo commento..." required></textarea>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <label style="font-size:.85rem;color:var(--text-muted);white-space:nowrap;"><?= $captcha['question'] ?></label>
                                <input type="text" name="captcha" required style="width:80px;" placeholder="?">
                                <button type="submit" class="btn-primary" style="padding:10px 24px;border-radius:8px;">Invia commento</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php /* ─── ARTICOLI CORRELATI ─── */ ?>
                <?php $relatedList = $related ?? []; ?>
                <?php if (!empty($relatedList)): ?>
                <div style="margin-top:48px;padding-top:32px;border-top:1px solid var(--border);">
                    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:20px;">Potrebbe interessarti</h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
                        <?php foreach ($relatedList as $rel): ?>
                        <a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($rel['slug']) ?>" style="display:flex;gap:14px;align-items:center;padding:14px;background:var(--bg-light,#1e293b);border:1px solid var(--border);border-radius:12px;text-decoration:none;transition:transform .15s,box-shadow .15s;">
                            <?php if (!empty($rel['cover_image'])): ?>
                            <img src="<?= ocms_base_url() . ocms_escape($rel['cover_image']) ?>" alt="" style="width:72px;height:54px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                            <?php endif; ?>
                            <div>
                                <div style="font-size:0.9rem;font-weight:600;color:var(--text);line-height:1.3;margin-bottom:4px;"><?= ocms_escape($rel['title']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= ocms_format_date($rel['created_at'], 'd M Y') ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* ─── PREV / NEXT ─── */ ?>
                <?php $prev = $prevArticle ?? null; $next = $nextArticle ?? null; ?>
                <?php if ($prev || $next): ?>
                <div style="margin-top:40px;padding-top:32px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php if ($prev): ?>
                    <a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($prev['slug']) ?>" style="display:flex;flex-direction:column;padding:16px;background:var(--bg-light,#1e293b);border:1px solid var(--border);border-radius:12px;text-decoration:none;transition:transform .15s;">
                        <span style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">&larr; Precedente</span>
                        <span style="font-size:0.9rem;font-weight:600;color:var(--text);line-height:1.3;"><?= ocms_escape(ocms_truncate($prev['title'], 60)) ?></span>
                    </a>
                    <?php else: ?><div></div><?php endif; ?>
                    <?php if ($next): ?>
                    <a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($next['slug']) ?>" style="display:flex;flex-direction:column;padding:16px;background:var(--bg-light,#1e293b);border:1px solid var(--border);border-radius:12px;text-decoration:none;text-align:right;transition:transform .15s;">
                        <span style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Successivo &rarr;</span>
                        <span style="font-size:0.9rem;font-weight:600;color:var(--text);line-height:1.3;"><?= ocms_escape(ocms_truncate($next['title'], 60)) ?></span>
                    </a>
                    <?php else: ?><div></div><?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="margin-top:24px;">
                    <a href="<?= ocms_base_url() ?>/blog" style="color:var(--text-muted);font-size:0.9rem;">&larr; Torna al blog</a>
                </div>
            </div>
        </article>
    </main>

    <?php include dirname(__DIR__) . '/partials/footer.php'; ?>

    <?php if (!empty($gallery)): ?>
    <script>
    const galleryUrls = <?= json_encode(array_map(fn($img) => ocms_base_url() . $img['url'], $gallery)) ?>;
    let lbIndex = 0;
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lb-img');
    const lbCounter = document.getElementById('lb-counter');

    function openLightbox(i) { lbIndex = i; lbImg.src = galleryUrls[i]; lbCounter.textContent = (i+1)+' / '+galleryUrls.length; lb.classList.add('active'); document.body.style.overflow='hidden'; }
    function closeLightbox() { lb.classList.remove('active'); document.body.style.overflow=''; }
    function navLightbox(dir) { lbIndex = (lbIndex+dir+galleryUrls.length)%galleryUrls.length; lbImg.src=galleryUrls[lbIndex]; lbCounter.textContent=(lbIndex+1)+' / '+galleryUrls.length; }
    function showReplyForm(id) { const el = document.getElementById('reply-'+id); el.style.display = el.style.display==='none'?'block':'none'; }

    lb.addEventListener('click', e => { if (e.target === lb) closeLightbox(); });
    document.addEventListener('keydown', e => { if (!lb.classList.contains('active')) return; if (e.key==='Escape') closeLightbox(); if (e.key==='ArrowLeft') navLightbox(-1); if (e.key==='ArrowRight') navLightbox(1); });
    </script>
    <?php else: ?>
    <script>function showReplyForm(id) { const el = document.getElementById('reply-'+id); el.style.display = el.style.display==='none'?'block':'none'; }</script>
    <?php endif; ?>
</body>
</html>
