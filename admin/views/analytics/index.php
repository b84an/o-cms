<?php
$pageTitle = 'Analytics';
$activeMenu = 'analytics';

$days = $days ?? 30;
$stats = $analytics ?? ['days' => [], 'total' => 0, 'pages' => [], 'referrers' => [], 'hours' => array_fill(0, 24, 0)];
$maxViews = max(array_column($stats['days'], 'views') ?: [0]);
$todayViews = end($stats['days'])['views'] ?? 0;
$avgViews = $stats['total'] > 0 ? round($stats['total'] / count($stats['days'])) : 0;

ob_start();
?>

<div class="page-header">
    <h1>Analytics</h1>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="<?= ocms_base_url() ?>/admin/analytics?days=7" class="btn <?= $days == 7 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">7 giorni</a>
        <a href="<?= ocms_base_url() ?>/admin/analytics?days=30" class="btn <?= $days == 30 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">30 giorni</a>
        <a href="<?= ocms_base_url() ?>/admin/analytics?days=90" class="btn <?= $days == 90 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">90 giorni</a>
    </div>
</div>

<!-- Stats riassunto -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="stat-value"><?= number_format($stats['total']) ?></div>
        <div class="stat-label">Visite totali (<?= $days ?>gg)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="stat-value"><?= $todayViews ?></div>
        <div class="stat-label">Oggi</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </div>
        <div class="stat-value"><?= $avgViews ?></div>
        <div class="stat-label">Media giornaliera</div>
    </div>
</div>

<!-- Grafico visite SVG -->
<div class="card" style="margin-bottom:24px;">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;">Visite giornaliere</h3>
    <div style="overflow-x:auto;">
        <?php
        $chartW = max(count($stats['days']) * 24, 600);
        $chartH = 200;
        $barW = max(($chartW - 40) / count($stats['days']) - 4, 6);
        $safeMax = $maxViews ?: 1;
        ?>
        <svg width="<?= $chartW ?>" height="<?= $chartH + 30 ?>" viewBox="0 0 <?= $chartW ?> <?= $chartH + 30 ?>" style="display:block;min-width:100%;">
            <!-- Griglia -->
            <?php for ($g = 0; $g <= 4; $g++): ?>
                <?php $gy = $chartH - ($chartH / 4 * $g); ?>
                <line x1="40" y1="<?= $gy ?>" x2="<?= $chartW ?>" y2="<?= $gy ?>" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
                <text x="36" y="<?= $gy + 4 ?>" fill="#94a3b8" font-size="10" text-anchor="end"><?= round($safeMax / 4 * $g) ?></text>
            <?php endfor; ?>

            <!-- Barre -->
            <?php foreach ($stats['days'] as $i => $day): ?>
                <?php
                $barH = $safeMax > 0 ? ($day['views'] / $safeMax * ($chartH - 10)) : 0;
                $x = 44 + $i * ($barW + 4);
                $y = $chartH - $barH;
                $opacity = $day['views'] > 0 ? 1 : 0.2;
                ?>
                <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $barW ?>" height="<?= $barH ?>"
                      rx="3" fill="rgba(99,102,241,<?= $opacity ?>)"
                      style="transition:all .2s;">
                    <title><?= $day['date'] ?>: <?= $day['views'] ?> visite</title>
                </rect>
                <?php if (count($stats['days']) <= 31 && $i % max(1, intval(count($stats['days']) / 15)) === 0): ?>
                    <text x="<?= $x + $barW / 2 ?>" y="<?= $chartH + 16 ?>" fill="#64748b" font-size="9" text-anchor="middle">
                        <?= date('d/m', strtotime($day['date'])) ?>
                    </text>
                <?php endif; ?>
            <?php endforeach; ?>
        </svg>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Pagine più viste -->
    <div class="card">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Pagine più viste</h3>
        <?php if (empty($stats['pages'])): ?>
            <p style="color:var(--text-muted);font-size:0.85rem;">Nessun dato</p>
        <?php else: ?>
            <?php $topPage = max($stats['pages']); ?>
            <?php foreach ($stats['pages'] as $path => $count): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                <div style="flex:1;min-width:0;">
                    <div style="font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= ocms_escape($path) ?>"><?= ocms_escape($path) ?></div>
                    <div style="margin-top:4px;height:4px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;">
                        <div style="height:100%;width:<?= round($count / $topPage * 100) ?>%;background:var(--primary);border-radius:2px;"></div>
                    </div>
                </div>
                <span style="font-size:0.8rem;font-weight:600;color:var(--text-muted);flex-shrink:0;"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Referrer e Ore -->
    <div>
        <div class="card" style="margin-bottom:24px;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">Referrer</h3>
            <?php if (empty($stats['referrers'])): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;">Nessun referrer esterno</p>
            <?php else: ?>
                <?php foreach (array_slice($stats['referrers'], 0, 8, true) as $ref => $count): ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:0.85rem;">
                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= ocms_escape($ref) ?></span>
                    <span style="color:var(--text-muted);flex-shrink:0;margin-left:12px;"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Distribuzione oraria -->
        <div class="card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:12px;">Distribuzione oraria</h3>
            <?php $maxHour = max($stats['hours']) ?: 1; ?>
            <div style="display:flex;align-items:flex-end;gap:2px;height:60px;">
                <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php $hh = ($stats['hours'][$h] ?? 0) / $maxHour * 100; ?>
                    <div style="flex:1;background:rgba(99,102,241,<?= $hh > 0 ? '0.7' : '0.15' ?>);height:<?= max($hh, 4) ?>%;border-radius:2px 2px 0 0;min-width:0;" title="<?= $h ?>:00 — <?= $stats['hours'][$h] ?? 0 ?>"></div>
                <?php endfor; ?>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:4px;">
                <span style="font-size:0.65rem;color:var(--text-muted);">00</span>
                <span style="font-size:0.65rem;color:var(--text-muted);">06</span>
                <span style="font-size:0.65rem;color:var(--text-muted);">12</span>
                <span style="font-size:0.65rem;color:var(--text-muted);">18</span>
                <span style="font-size:0.65rem;color:var(--text-muted);">23</span>
            </div>
        </div>
    </div>
</div>


<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
