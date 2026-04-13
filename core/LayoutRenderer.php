<?php
/**
 * O-CMS — Layout Renderer
 *
 * Converts a JSON layout structure into rendered HTML. Supports sections,
 * rows, columns, and a variety of content modules (heading, text, image,
 * gallery, video, menu, articles, etc.).
 *
 * @package O-CMS
 * @version 1.0.0
 *
 * Layout Structure:
 * {
 *   "id": "base",
 *   "name": "Layout Base",
 *   "sections": [
 *     {
 *       "id": "header",
 *       "type": "section",
 *       "tag": "header",
 *       "class": "site-header",
 *       "fullWidth": false,
 *       "rows": [
 *         {
 *           "id": "row-1",
 *           "columns": [
 *             {
 *               "id": "col-1",
 *               "width": 12,
 *               "modules": [
 *                 { "id": "mod-1", "type": "logo", "settings": {} }
 *               ]
 *             }
 *           ]
 *         }
 *       ]
 *     }
 *   ]
 * }
 */
class LayoutRenderer {
    private App $app;
    /** @var array Current page data (title, content, etc.) for content modules */
    private array $pageData;

    /**
     * @param App   $app      The main application instance
     * @param array $pageData Current page data used by content/breadcrumb modules
     */
    public function __construct(App $app, array $pageData = []) {
        $this->app = $app;
        $this->pageData = $pageData;
    }

    /**
     * Render a complete layout into HTML.
     *
     * @param array $layout The layout data with 'sections' array
     * @return string Rendered HTML
     */
    public function render(array $layout): string {
        $html = '';
        foreach ($layout['sections'] ?? [] as $section) {
            $html .= $this->renderSection($section);
        }
        return $html;
    }

    /**
     * Render a section (header, main, footer, etc.).
     *
     * @param array $section Section definition with tag, class, rows, etc.
     * @return string Rendered HTML
     */
    private function renderSection(array $section): string {
        $tag = $section['tag'] ?? 'section';
        $class = $section['class'] ?? '';
        $id = $section['id'] ?? '';
        $bgColor = $section['bgColor'] ?? '';
        $bgImage = $section['bgImage'] ?? '';
        $fullWidth = $section['fullWidth'] ?? false;

        $style = '';
        if ($bgColor) $style .= "background-color:{$bgColor};";
        if ($bgImage) $style .= "background-image:url('{$bgImage}');background-size:cover;background-position:center;";

        $attrs = '';
        if ($id) $attrs .= ' id="' . htmlspecialchars($id) . '"';
        if ($class) $attrs .= ' class="layout-section ' . htmlspecialchars($class) . '"';
        else $attrs .= ' class="layout-section"';
        if ($style) $attrs .= ' style="' . htmlspecialchars($style) . '"';

        $inner = '';
        foreach ($section['rows'] ?? [] as $row) {
            $inner .= $this->renderRow($row);
        }

        $content = $fullWidth ? $inner : '<div class="container">' . $inner . '</div>';
        return "<{$tag}{$attrs}>{$content}</{$tag}>\n";
    }

    /**
     * Render a row with its columns as a CSS grid.
     *
     * @param array $row Row definition with columns and optional gap/class
     * @return string Rendered HTML
     */
    private function renderRow(array $row): string {
        $class = 'layout-row';
        if (!empty($row['class'])) $class .= ' ' . $row['class'];
        $gap = $row['gap'] ?? '24';

        $html = '<div class="' . htmlspecialchars($class) . '" style="display:grid;grid-template-columns:';
        $cols = $row['columns'] ?? [];
        $colTemplates = [];
        foreach ($cols as $col) {
            $w = $col['width'] ?? 12;
            $colTemplates[] = ($w == 12) ? '1fr' : "minmax(0,{$w}fr)";
        }
        $html .= implode(' ', $colTemplates);
        $html .= ";gap:{$gap}px;\">";

        foreach ($cols as $col) {
            $html .= $this->renderColumn($col);
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a column and its child modules.
     *
     * @param array $col Column definition with width, class, and modules
     * @return string Rendered HTML
     */
    private function renderColumn(array $col): string {
        $class = 'layout-col';
        if (!empty($col['class'])) $class .= ' ' . $col['class'];

        $html = '<div class="' . htmlspecialchars($class) . '">';
        foreach ($col['modules'] ?? [] as $module) {
            $html .= $this->renderModule($module);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render a single module by type.
     *
     * @param array $module Module definition with 'type' and 'settings'
     * @return string Rendered HTML
     */
    public function renderModule(array $module): string {
        $type = $module['type'] ?? '';
        $s = $module['settings'] ?? [];

        return match ($type) {
            'heading'    => $this->modHeading($s),
            'text'       => $this->modText($s),
            'richtext'   => $this->modRichText($s),
            'image'      => $this->modImage($s),
            'gallery'    => $this->modGallery($s),
            'video'      => $this->modVideo($s),
            'button'     => $this->modButton($s),
            'spacer'     => $this->modSpacer($s),
            'divider'    => $this->modDivider($s),
            'html'       => $this->modHtml($s),
            'logo'       => $this->modLogo($s),
            'menu'       => $this->modMenu($s),
            'content'    => $this->modContent($s),
            'breadcrumb' => $this->modBreadcrumb($s),
            'social'     => $this->modSocial($s),
            'card'       => $this->modCard($s),
            'icon'       => $this->modIcon($s),
            'columns'    => $this->modColumns($s),
            'articles'   => $this->modArticles($s),
            default      => "<!-- unknown module: {$type} -->",
        };
    }

    // ─── MODULE RENDERERS ───

    private function modHeading(array $s): string {
        $tag = $s['tag'] ?? 'h2';
        $text = htmlspecialchars($s['text'] ?? '');
        $align = $s['align'] ?? 'left';
        $color = $s['color'] ?? '';
        $style = "text-align:{$align};";
        if ($color) $style .= "color:{$color};";
        return "<{$tag} class=\"mod-heading\" style=\"{$style}\">{$text}</{$tag}>\n";
    }

    private function modText(array $s): string {
        $text = htmlspecialchars($s['text'] ?? '');
        $align = $s['align'] ?? 'left';
        return '<p class="mod-text" style="text-align:' . $align . ';">' . nl2br($text) . "</p>\n";
    }

    private function modRichText(array $s): string {
        return '<div class="mod-richtext">' . ($s['html'] ?? '') . "</div>\n";
    }

    private function modImage(array $s): string {
        $src = htmlspecialchars($s['src'] ?? '');
        $alt = htmlspecialchars($s['alt'] ?? '');
        $width = $s['width'] ?? '100%';
        $radius = $s['radius'] ?? '12';
        $link = $s['link'] ?? '';
        $img = '<img src="' . ocms_base_url() . $src . '" alt="' . $alt . '" style="width:' . $width . ';border-radius:' . $radius . 'px;display:block;" loading="lazy">';
        if ($link) $img = '<a href="' . htmlspecialchars($link) . '">' . $img . '</a>';
        return '<div class="mod-image">' . $img . "</div>\n";
    }

    private function modGallery(array $s): string {
        $images = $s['images'] ?? [];
        $cols = $s['columns'] ?? 3;
        $gap = $s['gap'] ?? '12';
        $radius = $s['radius'] ?? '8';
        $html = '<div class="mod-gallery" style="display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:' . $gap . 'px;">';
        foreach ($images as $img) {
            $src = htmlspecialchars($img['src'] ?? '');
            $alt = htmlspecialchars($img['alt'] ?? '');
            $html .= '<img src="' . ocms_base_url() . $src . '" alt="' . $alt . '" style="width:100%;border-radius:' . $radius . 'px;aspect-ratio:1;object-fit:cover;" loading="lazy">';
        }
        $html .= "</div>\n";
        return $html;
    }

    private function modVideo(array $s): string {
        $url = $s['url'] ?? '';
        $ratio = $s['ratio'] ?? '56.25'; // 16:9
        if (str_contains($url, 'youtube') || str_contains($url, 'youtu.be')) {
            preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m);
            $id = $m[1] ?? '';
            return '<div class="mod-video" style="position:relative;padding-bottom:' . $ratio . '%;height:0;overflow:hidden;border-radius:12px;"><iframe src="https://www.youtube.com/embed/' . htmlspecialchars($id) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe></div>' . "\n";
        }
        if (str_contains($url, 'vimeo')) {
            preg_match('/vimeo\.com\/(\d+)/', $url, $m);
            $id = $m[1] ?? '';
            return '<div class="mod-video" style="position:relative;padding-bottom:' . $ratio . '%;height:0;overflow:hidden;border-radius:12px;"><iframe src="https://player.vimeo.com/video/' . htmlspecialchars($id) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen loading="lazy"></iframe></div>' . "\n";
        }
        return '<video src="' . htmlspecialchars(ocms_base_url() . $url) . '" controls style="width:100%;border-radius:12px;"></video>' . "\n";
    }

    private function modButton(array $s): string {
        $text = htmlspecialchars($s['text'] ?? 'Click');
        $url = htmlspecialchars($s['url'] ?? '#');
        $style = $s['style'] ?? 'primary';
        $align = $s['align'] ?? 'left';
        $target = ($s['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
        return '<div style="text-align:' . $align . ';"><a href="' . $url . '" class="btn-' . $style . '"' . $target . '>' . $text . "</a></div>\n";
    }

    private function modSpacer(array $s): string {
        $h = $s['height'] ?? '40';
        return '<div class="mod-spacer" style="height:' . $h . "px;\"></div>\n";
    }

    private function modDivider(array $s): string {
        $color = $s['color'] ?? 'var(--border)';
        $width = $s['width'] ?? '100%';
        $margin = $s['margin'] ?? '24';
        return '<hr class="mod-divider" style="border:none;border-top:1px solid ' . $color . ';width:' . $width . ';margin:' . $margin . "px auto;\">\n";
    }

    private function modHtml(array $s): string {
        return '<div class="mod-html">' . ($s['code'] ?? '') . "</div>\n";
    }

    private function modLogo(array $s): string {
        $siteName = $this->app->config['site_name'] ?? 'O-CMS';
        $logoUrl = $s['image'] ?? '';
        if ($logoUrl) {
            return '<a href="' . ocms_base_url() . '/" class="mod-logo"><img src="' . ocms_base_url() . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($siteName) . '" style="height:' . ($s['height'] ?? '40') . 'px;"></a>' . "\n";
        }
        return '<a href="' . ocms_base_url() . '/" class="site-logo">' . htmlspecialchars($siteName) . "</a>\n";
    }

    private function modMenu(array $s): string {
        $menuName = $s['menu'] ?? 'main';
        $menu = $this->app->storage->find('menus', $menuName);
        if (!$menu || empty($menu['items'])) return '';
        $html = '<nav class="mod-menu site-nav">';
        foreach (ocms_filter_menu_items($menu['items']) as $item) {
            $target = ($item['target'] ?? '_self') === '_blank' ? ' target="_blank" rel="noopener"' : '';
            $html .= '<a href="' . ocms_base_url() . htmlspecialchars($item['url']) . '" class="nav-link"' . $target . '>' . htmlspecialchars($item['label']) . '</a>';
        }
        $html .= "</nav>\n";
        return $html;
    }

    private function modContent(array $s): string {
        // Insert the current page content with shortcode rendering
        $content = $this->pageData['content'] ?? '';
        $content = str_replace('src="/uploads/', 'src="' . ocms_base_url() . '/uploads/', $content);
        $content = ocms_render_gallery_shortcode($content);
        $content = ocms_render_form_shortcode($content);
        return '<div class="mod-content page-content"><div class="content">' . $content . "</div></div>\n";
    }

    private function modBreadcrumb(array $s): string {
        $sep = $s['separator'] ?? '›';
        $html = '<nav class="mod-breadcrumb" style="font-size:0.85rem;color:var(--text-muted);">';
        $html .= '<a href="' . ocms_base_url() . '/">Home</a>';
        if (!empty($this->pageData['title'])) {
            $html .= " {$sep} <span>" . htmlspecialchars($this->pageData['title']) . '</span>';
        }
        $html .= "</nav>\n";
        return $html;
    }

    private function modSocial(array $s): string {
        $links = $s['links'] ?? [];
        $size = $s['size'] ?? '20';
        $html = '<div class="mod-social" style="display:flex;gap:12px;">';
        foreach ($links as $link) {
            $html .= '<a href="' . htmlspecialchars($link['url'] ?? '#') . '" target="_blank" rel="noopener" style="color:var(--text-muted);font-size:' . $size . 'px;" title="' . htmlspecialchars($link['label'] ?? '') . '">' . htmlspecialchars($link['icon'] ?? $link['label'] ?? '') . '</a>';
        }
        $html .= "</div>\n";
        return $html;
    }

    private function modCard(array $s): string {
        $title = htmlspecialchars($s['title'] ?? '');
        $text = htmlspecialchars($s['text'] ?? '');
        $image = $s['image'] ?? '';
        $link = $s['link'] ?? '';
        $html = '<div class="mod-card" style="background:var(--bg-light,#1e293b);border:1px solid var(--border);border-radius:16px;overflow:hidden;">';
        if ($image) $html .= '<img src="' . ocms_base_url() . htmlspecialchars($image) . '" style="width:100%;height:200px;object-fit:cover;" loading="lazy">';
        $html .= '<div style="padding:20px;">';
        if ($title) $html .= '<h3 style="font-size:1.1rem;font-weight:700;margin-bottom:8px;">' . $title . '</h3>';
        if ($text) $html .= '<p style="color:var(--text-muted);font-size:0.9rem;">' . $text . '</p>';
        if ($link) $html .= '<a href="' . htmlspecialchars($link) . '" style="display:inline-block;margin-top:12px;font-weight:600;font-size:0.85rem;">Scopri di più →</a>';
        $html .= '</div></div>' . "\n";
        return $html;
    }

    private function modIcon(array $s): string {
        $icon = $s['emoji'] ?? '⭐';
        $size = $s['size'] ?? '48';
        $align = $s['align'] ?? 'center';
        return '<div class="mod-icon" style="text-align:' . $align . ';font-size:' . $size . "px;\">{$icon}</div>\n";
    }

    private function modColumns(array $s): string {
        $cols = $s['columns'] ?? 3;
        $items = $s['items'] ?? [];
        $gap = $s['gap'] ?? '24';
        $html = '<div class="mod-columns" style="display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:' . $gap . 'px;">';
        foreach ($items as $item) {
            $html .= '<div>' . ($item['html'] ?? '') . '</div>';
        }
        $html .= "</div>\n";
        return $html;
    }

    private function modArticles(array $s): string {
        $count = $s['count'] ?? 3;
        $articles = $this->app->storage->findAll('articles', fn($a) => $a['status'] === 'published', 'created_at', 'desc');
        $articles = array_slice($articles, 0, $count);
        if (empty($articles)) return '<p style="color:var(--text-muted);">Nessun articolo.</p>';

        $cols = $s['columns'] ?? 3;
        $html = '<div class="articles-grid" style="grid-template-columns:repeat(' . $cols . ',1fr);">';
        foreach ($articles as $a) {
            $html .= '<article class="article-card">';
            if (!empty($a['cover_image'])) {
                $html .= '<div class="article-cover" style="background-image:url(\'' . ocms_base_url() . htmlspecialchars($a['cover_image']) . '\');"></div>';
            }
            $html .= '<div class="article-body">';
            $html .= '<h2><a href="' . ocms_base_url() . '/blog/' . htmlspecialchars($a['slug']) . '">' . htmlspecialchars($a['title']) . '</a></h2>';
            if (!empty($a['excerpt'])) $html .= '<p>' . htmlspecialchars($a['excerpt']) . '</p>';
            $html .= '</div></article>';
        }
        $html .= "</div>\n";
        return $html;
    }

    /**
     * Merge a base layout with page-specific section overrides.
     *
     * @param array      $base     The base layout
     * @param array|null $override Page-specific layout overrides (sections matched by ID)
     * @return array The merged layout
     */
    public static function mergeLayouts(array $base, ?array $override): array {
        if (!$override || empty($override['sections'])) return $base;

        $merged = $base;
        $overrideSections = [];
        foreach ($override['sections'] as $s) {
            $overrideSections[$s['id']] = $s;
        }

        foreach ($merged['sections'] as &$section) {
            if (isset($overrideSections[$section['id']])) {
                $section = array_merge($section, $overrideSections[$section['id']]);
            }
        }

        return $merged;
    }
}
