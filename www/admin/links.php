<?php
/**
 * Links List — view all links, delete, filter by status
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();

// Handle deletion
if (isset($_POST['delete_id'])) {
    $delStmt = $db->prepare("DELETE FROM links WHERE id = :id");
    $delStmt->execute([':id' => (int)$_POST['delete_id']]);
    header('Location: links.php?deleted=1');
    exit;
}

$deleted = isset($_GET['deleted']);

// Filters
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = '';
$params = [];
if ($statusFilter && in_array($statusFilter, ['active', 'expired', 'opened'])) {
    $where = "WHERE status = :status";
    $params[':status'] = $statusFilter;
}

$total = $db->query("SELECT COUNT(*) FROM links $where")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$links = $db->prepare("SELECT * FROM links $where ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $links->bindValue($k, $v);
$links->bindValue(':limit', $perPage, PDO::PARAM_INT);
$links->bindValue(':offset', $offset, PDO::PARAM_INT);
$links->execute();

adminHeader('链接列表', 'links');
?>

<h1 class="page-title">📋 链接列表</h1>

<?php if ($deleted): ?>
    <div class="alert alert-success">✅ 链接已删除</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <a href="links.php" class="btn btn-sm <?= $statusFilter==='' ? 'btn-primary' : 'btn-outline' ?>">全部</a>
            <a href="links.php?status=active" class="btn btn-sm <?= $statusFilter==='active' ? 'btn-primary' : 'btn-outline' ?>">活跃</a>
            <a href="links.php?status=opened" class="btn btn-sm <?= $statusFilter==='opened' ? 'btn-primary' : 'btn-outline' ?>">已打开</a>
            <a href="links.php?status=expired" class="btn btn-sm <?= $statusFilter==='expired' ? 'btn-primary' : 'btn-outline' ?>">已过期</a>
        </div>
        <span class="text-muted">共 <?= $total ?> 条</span>
    </div>

    <table>
        <thead><tr>
            <th>ID</th><th>活动</th><th>Token</th><th>状态</th><th>访问</th><th>超时(s)</th><th>创建时间</th><th>首次访问</th><th>过期时间</th><th>操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($links as $row):
            $statusLabel = ['active'=>'活跃','opened'=>'已打开','expired'=>'已过期'][$row['status']] ?? $row['status'];
            $statusClass = 'badge-' . $row['status'];
        ?>
        <tr>
            <td>#<?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['campaign_name'] ?: '-') ?></td>
            <td><code style="font-size:11px;"><?= htmlspecialchars(substr($row['token'], 0, 16)) ?>...</code></td>
            <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
            <td><?= $row['access_count'] ?>/<?= $row['max_accesses'] ?></td>
            <td><?= $row['access_timeout'] ?></td>
            <td><?= $row['created_at'] ?></td>
            <td><?= $row['first_accessed_at'] ?: '-' ?></td>
            <td><?= $row['expires_at'] ?: '未开始计时' ?></td>
            <td>
                <a href="stats.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline">统计</a>
                <form method="post" style="display:inline" onsubmit="return confirm('确定删除此链接及所有访问记录？此操作不可撤销。')">
                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($links->rowCount() === 0): ?>
        <tr><td colspan="10" style="text-align:center;color:#888;padding:32px;">暂无数据</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $query = http_build_query(array_filter(['status'=>$statusFilter, 'page'=>$i]));
        ?>
            <a href="links.php?<?= $query ?>" class="<?= $i===$page ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php adminFooter(); ?>
