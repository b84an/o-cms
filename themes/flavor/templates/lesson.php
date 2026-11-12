<?php
require_once __DIR__ . '/_bootstrap.php';
$fileList   = is_array($files ?? null) ? $files : [];
$groupList  = is_array($groups ?? null) ? $groups : [];
$descs      = is_array($groupDescs ?? null) ? $groupDescs : [];
$mainBase   = (string) ($mainBasename ?? '');
$mainFile   = (string) ($lesson['main_file'] ?? '');
$fileBase   = ocms_base_url() . '/uploads/lezioni/' . rawurlencode((string) $lesson['slug']) . '/';
$catLabels  = ['html' => 'Pagina web', 'image' => 'Immagine', 'audio' => 'Audio', 'video' => 'Video', 'pdf' => 'PDF', 'archive' => 'Archivio'];
$catOrder   = ['html' => 0, 'image' => 1, 'audio' => 2, 'video' => 3, 'pdf' => 4, 'archive' => 5];
$totalFiles = count($fileList);
$tags = array_values(array_filter((array) ($lesson['tags'] ?? []), fn($t) => is_string($t) && trim($t) !== ''));

$metaTitle = ($lesson['meta']['title'] ?? '') ?: (string) $lesson['title'];
$metaDesc  = ($lesson['meta']['description'] ?? '') ?: (string) ($lesson['description'] ?? '');
$flavor = [
    'title'       => $metaTitle,
    'description' => $metaDesc,
    'seo'         => [
        'type'        => 'article',
        'title'       => $metaTitle,
        'description' => $metaDesc,
        'image'       => ($lesson['meta']['og_image'] ?? '') ?: ($lesson['cover_image'] ?? ''),
        'published'   => $lesson['created_at'] ?? '',
        'modified'    => $lesson['updated_at'] ?? '',
    ],
    'bodyClass'   => 'page-lesson',
];
include __DIR__ . '/_header.php';
?>
<div class="wrap">
    <header class="page-head lesson-head">
        <nav class="crumbs" aria-label="Percorso"><a href="<?= ocms_base_url() ?>/lezioni">Lezioni</a></nav>
        <h1 class="display"><?= ocms_escape($lesson['title']) ?></h1>
<?php if (!empty($lesson['description'])): ?>
        <p class="dek"><?= ocms_escape(flavor_plain($lesson['description'])) ?></p>
<?php endif; ?>
        <ul class="meta-row">
            <li><?= flavor_icon('file', 16) ?><span><?= $totalFiles === 1 ? '1 file' : $totalFiles . ' file' ?></span></li>
<?php if (!empty($lesson['updated_at'])): ?>
            <li><?= flavor_icon('calendar', 16) ?><time datetime="<?= ocms_escape(flavor_datetime($lesson['updated_at'])) ?>"><?= ocms_escape(flavor_date($lesson['updated_at'])) ?></time></li>
<?php endif; ?>
<?php if (($lesson['views'] ?? 0) > 0): ?>
            <li><?= flavor_icon('eye', 16) ?><span><?= number_format((int) $lesson['views'], 0, ',', '.') ?> visite</span></li>
<?php endif; ?>
        </ul>
<?php if ($tags): ?>
        <ul class="tag-list">
<?php foreach ($tags as $t): ?>
            <li><span class="tag"><?= ocms_escape(flavor_tag_label($t)) ?></span></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </header>

<?php if ($totalFiles === 0): ?>
    <div class="empty-state ocms-glass"><p>Questa lezione non contiene ancora materiali.</p></div>
<?php endif; ?>

    <div class="res-groups">
<?php foreach ($groupList as $basename => $members):
    if (!is_array($members) || !$members) continue;
    $basename = (string) $basename;
    $isMainGroup = ($basename === $mainBase);
    usort($members, fn($a, $b) => ($catOrder[$a['cat']] ?? 9) <=> ($catOrder[$b['cat']] ?? 9));
    $kinds = array_values(array_unique(array_column($members, 'cat')));
?>
        <section class="res-group ocms-glass<?= $isMainGroup ? ' is-main' : '' ?>">
            <header class="res-head">
                <h2><?= ocms_escape(ucfirst(str_replace(['_', '-'], ' ', $basename))) ?></h2>
                <ul class="res-kinds">
<?php if ($isMainGroup): ?>
                    <li class="badge badge-main">Principale</li>
<?php endif; ?>
<?php foreach ($kinds as $k): ?>
                    <li class="badge kind-<?= ocms_escape($k) ?>"><?= ocms_escape($catLabels[$k] ?? $k) ?></li>
<?php endforeach; ?>
                </ul>
            </header>
<?php if (isset($descs[$basename]) && trim((string) $descs[$basename]) !== ''): ?>
            <p class="res-desc"><?= nl2br(ocms_escape((string) $descs[$basename])) ?></p>
<?php endif; ?>
            <div class="res-body">
<?php foreach ($members as $m):
    $name = (string) $m['name'];
    $href = $fileBase . rawurlencode($name);
    $esc  = ocms_escape($name);
    $isMain = ($name === $mainFile);
    switch ($m['cat']):
        case 'html': ?>
                <a class="file-link<?= $isMain ? ' is-main' : '' ?>" href="<?= ocms_escape($href) ?>" target="_blank" rel="noopener">
                    <span class="file-ico"><?= flavor_icon('html', 20) ?></span>
                    <span class="file-name"><?= $isMain ? 'Apri la lezione' : $esc ?><small><?= $esc ?></small></span>
                    <?= flavor_icon('external', 18) ?><span class="sr-only">(si apre in una nuova scheda)</span>
                </a>
<?php break; case 'image': ?>
                <a class="res-image" href="<?= ocms_escape($href) ?>" data-lightbox="lesson" data-caption="<?= $esc ?>">
                    <img src="<?= ocms_escape($href) ?>" alt="<?= $esc ?>" loading="lazy" decoding="async">
                </a>
<?php break; case 'audio': ?>
                <figure class="res-media">
                    <figcaption><?= flavor_icon('audio', 18) ?><?= $esc ?></figcaption>
                    <audio controls preload="metadata" src="<?= ocms_escape($href) ?>">Il browser non riproduce questo audio: <a href="<?= ocms_escape($href) ?>" download>scaricalo</a>.</audio>
                </figure>
<?php break; case 'video': ?>
                <figure class="res-media">
                    <figcaption><?= flavor_icon('video', 18) ?><?= $esc ?></figcaption>
                    <video controls preload="metadata" playsinline src="<?= ocms_escape($href) ?>">Il browser non riproduce questo video: <a href="<?= ocms_escape($href) ?>" download>scaricalo</a>.</video>
                </figure>
<?php break; case 'pdf': ?>
                <div class="res-pdf">
                    <div class="res-pdf-bar">
                        <span><?= flavor_icon('pdf', 18) ?><?= $esc ?></span>
                        <span class="res-pdf-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= ocms_escape($href) ?>" target="_blank" rel="noopener">Apri</a>
                            <a class="btn btn-ghost btn-sm" href="<?= ocms_escape($href) ?>" download><?= flavor_icon('download', 16) ?>Scarica</a>
                        </span>
                    </div>
                    <iframe src="<?= ocms_escape($href) ?>" title="<?= $esc ?>" loading="lazy"></iframe>
                </div>
<?php break; case 'archive': ?>
                <a class="file-link" href="<?= ocms_escape($href) ?>" download>
                    <span class="file-ico"><?= flavor_icon('archive', 20) ?></span>
                    <span class="file-name"><?= $esc ?><small>Archivio da scaricare</small></span>
                    <?= flavor_icon('download', 18) ?>
                </a>
<?php break; endswitch; endforeach; ?>
            </div>
        </section>
<?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
