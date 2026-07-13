<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();

// Stats
$totalLinks   = DB::query("SELECT COUNT(*) FROM links")->fetchColumn();
$activeLinks  = DB::query("SELECT COUNT(*) FROM links WHERE status='active'")->fetchColumn();
$expiredLinks = DB::query("SELECT COUNT(*) FROM links WHERE status='expired'")->fetchColumn();
$openedLinks  = DB::query("SELECT COUNT(*) FROM links WHERE access_count > 0")->fetchColumn();
$totalAccess  = DB::query("SELECT COUNT(*) FROM access_logs")->fetchColumn();

adminHeader('管理仪表盘', 'dashboard');
?>

<h1 class="page-title main-shell">📊 管理仪表盘</h1>

<div class="stats-grid main-shell">
    <div class="stat-card">
        <div class="stat-value"><?= $totalLinks ?></div>
        <div class="stat-label">链接总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $activeLinks ?></div>
        <div class="stat-label">活跃链接</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $openedLinks ?></div>
        <div class="stat-label">已被访问</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $expiredLinks ?></div>
        <div class="stat-label">已过期</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalAccess ?></div>
        <div class="stat-label">总访问次数</div>
    </div>
</div>

<div class="card main-shell">
    <div class="card-header">📌 最近创建的链接</div>
    <p class="section-meta">这里展示最近创建的 10 条链接，方便快速查看访问状态与入口。</p>
    <div class="table-wrap">
    <table>
        <thead><tr>
            <th>ID</th><th>活动名称</th><th>Token</th><th>状态</th><th>访问次数</th><th>创建时间</th><th>操作</th>
        </tr></thead>
        <tbody>
        <?php
        $recent = DB::query("SELECT * FROM links ORDER BY id DESC LIMIT 10");
        foreach ($recent as $row):
            if ($row['status'] === 'active') {
                $displayState = empty($row['first_accessed_at']) ? 'unopened' : 'opened';
            } else {
                $displayState = $row['status'];
            }
            $statusLabel = ['unopened'=>'未打开','opened'=>'已打开','draft'=>'草稿中','submitted'=>'已提交','expired'=>'已过期'][$displayState] ?? $row['status'];
            $statusClass = 'badge-' . $displayState;
        ?>
        <tr>
            <td>#<?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['campaign_name'] ?: '-') ?></td>
            <td><code><?= htmlspecialchars($row['token']) ?></code></td>
            <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
            <td><?= $row['access_count'] ?></td>
            <td><?= $row['created_at'] ?></td>
            <td>
                <a href="stats.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline">查看</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php adminFooter(); ?>
