<?php
require_once __DIR__ . '/_bootstrap.php';
$slug      = (string) $article['slug'];
$artUrl    = ocms_base_url() . '/blog/' . rawurlencode($slug);
$metaTitle = ($article['meta']['title'] ?? '') ?: (string) $article['title'];
$metaDesc  = ($article['meta']['description'] ?? '') ?: (string) ($article['excerpt'] ?? '');
$mins      = flavor_read_time((string) ($article['content'] ?? ''));
$commentsList = array_values(is_array($comments ?? null) ? $comments : []);
$relatedList  = is_array($related ?? null) ? $related : [];
$prev = $prevArticle ?? null;
$next = $nextArticle ?? null;
$gallery = is_array($article['gallery'] ?? null) ? array_values(array_filter($article['gallery'], fn($g) => is_array($g) && !empty($g['url']))) : [];

$catNames = [];
foreach ($app->storage->findAll('categories') as $c) { $catNames[$c['slug'] ?? ''] = $c['name'] ?? ''; }
$cat = (string) ($article['category'] ?? '');
$author = (string) ($article['author'] ?? '');
if ($author !== '') {
    $u = $app->storage->find('users', $author);
    if (is_array($u) && !empty($u['display_name'])) $author = (string) $u['display_name'];
}

$flashes = ocms_flash_get();
$captcha = ocms_captcha_generate(); // una sola domanda per tutta la pagina

$flavor = [
    'title'       => $metaTitle,
    'description' => $metaDesc,
    'seo'         => [
        'type'        => 'article',
        'title'       => $metaTitle,
        'description' => $metaDesc,
        'image'       => ($article['meta']['og_image'] ?? '') ?: ($article['cover_image'] ?? ''),
        'published'   => $article['created_at'] ?? '',
        'modified'    => $article['updated_at'] ?? '',
        'author'      => $author,
    ],
    'bodyClass'   => 'page-article',
    'progress'    => true,
];
include __DIR__ . '/_header.php';

$roots = [];
$replies = [];
foreach ($commentsList as $c) {
    if (!empty($c['parent_id'])) $replies[(string) $c['parent_id']][] = $c; else $roots[] = $c;
}
// Risposte il cui commento padre non è visibile: mostrale comunque, come commenti principali
$rootIds = array_map(fn($c) => (string) ($c['id'] ?? ''), $roots);
foreach ($replies as $pid => $list) {
    if (!in_array((string) $pid, $rootIds, true)) { foreach ($list as $r) $roots[] = $r; unset($replies[$pid]); }
}

$commentForm = function (string $parentId, string $idSuffix) use ($slug, $captcha): string {
    $action = ocms_base_url() . '/blog/' . rawurlencode($slug) . '/comment';
    $isReply = $parentId !== '';
    ob_start(); ?>
        <form class="comment-form" method="post" action="<?= ocms_escape($action) ?>">
            <?= ocms_csrf_field() ?>
            <input type="hidden" name="parent_id" value="<?= ocms_escape($parentId) ?>">
            <div class="form-row">
                <div class="field">
                    <label for="cn-<?= $idSuffix ?>">Nome <span aria-hidden="true">*</span></label>
                    <input id="cn-<?= $idSuffix ?>" type="text" name="author_name" required maxlength="100" autocomplete="name">
                </div>
                <div class="field">
                    <label for="ce-<?= $idSuffix ?>">Email <span class="field-opt">(facoltativa, non pubblicata)</span></label>
                    <input id="ce-<?= $idSuffix ?>" type="email" name="author_email" maxlength="200" autocomplete="email">
                </div>
            </div>
            <div class="field">
                <label for="cb-<?= $idSuffix ?>"><?= $isReply ? 'La tua risposta' : 'Il tuo commento' ?> <span aria-hidden="true">*</span></label>
                <textarea id="cb-<?= $idSuffix ?>" name="comment_body" rows="<?= $isReply ? 3 : 5 ?>" required maxlength="5000"></textarea>
            </div>
            <div class="form-foot">
                <div class="field field-captcha">
                    <label for="cc-<?= $idSuffix ?>">Verifica: quanto fa <?= ocms_escape(str_replace(' = ?', '', (string) $captcha['question'])) ?>?</label>
                    <input id="cc-<?= $idSuffix ?>" type="text" name="captcha" required inputmode="numeric" autocomplete="off" maxlength="4">
                </div>
                <button class="btn btn-primary" type="submit"><?= $isReply ? 'Invia la risposta' : 'Pubblica il commento' ?></button>
            </div>
            <p class="form-note">I commenti compaiono dopo l'approvazione.</p>
        </form>
<?php return (string) ob_get_clean();
};
$renderComment = function (array $c, bool $isReply) use ($replies, $commentForm, &$renderComment): string {
    $id = (string) ($c['id'] ?? '');
    $domId = 'comment-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    ob_start(); ?>
        <li class="comment<?= $isReply ? ' comment-reply' : '' ?><?= !empty($c['is_admin_reply']) ? ' is-staff' : '' ?>" id="<?= ocms_escape($domId) ?>">
            <div class="comment-avatar" aria-hidden="true"><?= ocms_escape(flavor_initials((string) ($c['author_name'] ?? ''))) ?></div>
            <div class="comment-main">
                <p class="comment-head">
                    <strong><?= ocms_escape($c['author_name'] ?? 'Anonimo') ?></strong>
<?php if (!empty($c['is_admin_reply'])): ?>
                    <span class="badge">Autore</span>
<?php endif; ?>
                    <time datetime="<?= ocms_escape(flavor_datetime($c['created_at'] ?? '')) ?>"><?= ocms_escape(flavor_date($c['created_at'] ?? '', true)) ?></time>
                </p>
                <div class="comment-body"><?= nl2br(ocms_escape($c['body'] ?? '')) ?></div>
<?php if (!$isReply && $id !== ''): ?>
                <button class="link-btn" type="button" data-reply-toggle aria-expanded="false" aria-controls="reply-<?= ocms_escape($domId) ?>"><?= flavor_icon('reply', 15) ?>Rispondi</button>
                <div class="reply-box" id="reply-<?= ocms_escape($domId) ?>" hidden>
                    <?= $commentForm($id, 'r' . substr(md5($id), 0, 8)) ?>
                </div>
<?php endif; ?>
<?php if (!$isReply && !empty($replies[$id])): ?>
                <ol class="comment-replies">
<?php foreach ($replies[$id] as $r) echo $renderComment($r, true); ?>
                </ol>
<?php endif; ?>
            </div>
        </li>
<?php return (string) ob_get_clean();
};
?>
<article class="article" itemscope itemtype="https://schema.org/BlogPosting">
    <header class="article-hero wrap">
        <nav class="crumbs" aria-label="Percorso">
            <a href="<?= ocms_base_url() ?>/blog">Blog</a>
<?php if ($cat !== ''): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= ocms_base_url() ?>/blog?cat=<?= rawurlencode($cat) ?>"><?= ocms_escape($catNames[$cat] ?? ucfirst($cat)) ?></a>
<?php endif; ?>
        </nav>
        <h1 class="display article-title" itemprop="headline"><?= ocms_escape($article['title']) ?></h1>
<?php if (flavor_plain($article['excerpt'] ?? '') !== ''): ?>
        <p class="dek" itemprop="description"><?= ocms_escape(flavor_plain($article['excerpt'])) ?></p>
<?php endif; ?>
        <ul class="meta-row">
<?php if (!empty($article['created_at'])): ?>
            <li><?= flavor_icon('calendar', 16) ?><time datetime="<?= ocms_escape(flavor_datetime($article['created_at'])) ?>" itemprop="datePublished"><?= ocms_escape(flavor_date($article['created_at'])) ?></time></li>
<?php endif; ?>
<?php if ($author !== ''): ?>
            <li><?= flavor_icon('user', 16) ?><span itemprop="author"><?= ocms_escape($author) ?></span></li>
<?php endif; ?>
            <li><?= flavor_icon('clock', 16) ?><span><?= $mins ?> min di lettura</span></li>
<?php if (($article['views'] ?? 0) > 0): ?>
            <li><?= flavor_icon('eye', 16) ?><span><?= number_format((int) $article['views'], 0, ',', '.') ?> letture</span></li>
<?php endif; ?>
        </ul>
        <div class="article-cover">
            <?= flavor_cover($article, ['class' => 'cover', 'vt' => true, 'eager' => true, 'alt' => '', 'sizes' => '(min-width: 1200px) 1100px, 100vw']) ?>
        </div>
    </header>

    <div class="wrap article-layout">
        <div class="prose article-body" itemprop="articleBody">
            <?= flavor_content((string) ($article['content'] ?? '')) ?>
        </div>

<?php if ($gallery): ?>
        <section class="article-gallery" aria-label="Galleria fotografica">
            <ul class="thumb-grid">
<?php foreach ($gallery as $img):
    $full = flavor_media((string) $img['url']);
    $thumb = flavor_media((string) ($img['thumb'] ?? $img['url']));
    $alt = (string) ($img['name'] ?? ''); ?>
                <li><a href="<?= ocms_escape($full) ?>" data-lightbox="article" data-caption="<?= ocms_escape($alt) ?>"><img src="<?= ocms_escape($thumb) ?>" alt="<?= ocms_escape($alt) ?>" loading="lazy" decoding="async"></a></li>
<?php endforeach; ?>
            </ul>
        </section>
<?php endif; ?>

<?php if (!empty($article['tags']) && is_array($article['tags'])): ?>
        <div class="article-tags">
            <h2 class="sr-only">Tag</h2>
            <ul class="tag-list">
<?php foreach ((array)$article['tags'] as $tag): if (!is_string($tag) || trim($tag) === '') continue; ?>
                <li><a class="tag" href="<?= ocms_base_url() ?>/blog?tag=<?= rawurlencode(ocms_slug($tag)) ?>"><?= ocms_escape(flavor_tag_label($tag)) ?></a></li>
<?php endforeach; ?>
            </ul>
        </div>
<?php endif; ?>

<?php if ($prev || $next): ?>
        <nav class="post-nav" aria-label="Altri articoli">
<?php if ($prev): ?>
            <a class="post-nav-link is-prev" href="<?= ocms_base_url() ?>/blog/<?= rawurlencode((string) $prev['slug']) ?>" rel="prev">
                <span class="post-nav-dir"><?= flavor_icon('left', 16) ?>Più recente</span>
                <span class="post-nav-title"><?= ocms_escape(ocms_truncate((string) $prev['title'], 90)) ?></span>
            </a>
<?php endif; ?>
<?php if ($next): ?>
            <a class="post-nav-link is-next" href="<?= ocms_base_url() ?>/blog/<?= rawurlencode((string) $next['slug']) ?>" rel="next">
                <span class="post-nav-dir">Meno recente<?= flavor_icon('right', 16) ?></span>
                <span class="post-nav-title"><?= ocms_escape(ocms_truncate((string) $next['title'], 90)) ?></span>
            </a>
<?php endif; ?>
        </nav>
<?php endif; ?>

        <section class="comments" id="comments" aria-labelledby="comments-title">
            <h2 id="comments-title">Commenti<?php if ($commentsList): ?> <span class="count"><?= count($commentsList) ?></span><?php endif; ?></h2>
            <?= flavor_flashes($flashes) ?>
<?php if ($roots): ?>
            <ol class="comment-list">
<?php foreach ($roots as $c) echo $renderComment($c, false); ?>
            </ol>
<?php else: ?>
            <p class="muted">Ancora nessun commento. Il primo è sempre il più difficile.</p>
<?php endif; ?>
            <div class="comment-new ocms-glass">
                <h3>Lascia un commento</h3>
                <?= $commentForm('', 'main') ?>
            </div>
        </section>
    </div>

<?php if ($relatedList): ?>
    <section class="wrap related" aria-labelledby="related-title">
        <h2 id="related-title">Potrebbe interessarti</h2>
        <div class="post-grid post-grid-related">
<?php foreach ($relatedList as $rel) echo flavor_article_card($rel, ['variant' => 'compact', 'heading' => 'h3', 'catNames' => $catNames, 'hideFeatured' => true]); ?>
        </div>
    </section>
<?php endif; ?>
</article>
<?php include __DIR__ . '/_footer.php'; ?>
