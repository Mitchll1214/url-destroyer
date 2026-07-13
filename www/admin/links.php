<?php
/**
 * Links List — view all links, delete, filter by status
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();

// Handle deletion
if (isset($_POST['delete_id'])) {
    $delStmt = DB::prepare("DELETE FROM links WHERE id = :id");
    $delStmt->execute([':id' => (int)$_POST['delete_id']]);
    header('Location: links.php?deleted=1');
    exit;
}

// Handle reactivate (expired → active) — only if not absolutely expired
if (isset($_POST['reactivate_id'])) {
    $rid = (int)$_POST['reactivate_id'];
    $link = DB::prepare("SELECT created_at, absolute_expiry_hours FROM links WHERE id=:id");
    $link->execute([':id'=>$rid]);
    $link = $link->fetch();
    if ($link && time() <= strtotime($link['created_at']) + (int)$link['absolute_expiry_hours'] * 3600) {
        DB::prepare("UPDATE links SET status='active', first_accessed_at=NULL, expires_at=NULL, access_count=0, max_accesses=2, expire_on_submit=0 WHERE id=:id")
           ->execute([':id'=>$rid]);
        DB::prepare("INSERT INTO access_logs (link_id, ip, user_agent, form_data, accessed_at) VALUES (:id, '管理员', 'reactivate', '链接被重新打开', datetime('now','localtime'))"))
           ->execute([':id'=>$rid]);
    }
    header('Location: links.php?edited=1');
    exit;
}

// Handle force expire (non-expired → expired)
if (isset($_POST['expire_id'])) {
    $eid = (int)$_POST['expire_id'];
    DB::prepare("UPDATE links SET status='expired', expires_at=datetime('now','localtime') WHERE id=:id"))
       ->execute([':id'=>$eid]);
    DB::prepare("INSERT INTO access_logs (link_id, ip, user_agent, form_data, accessed_at) VALUES (:id, '管理员', 'force_expire', '管理员置为已过期', datetime('now','localtime'))"))
       ->execute([':id'=>$eid]);
    header('Location: links.php?edited=1');
    exit;
}

$deleted = isset($_GET['deleted']);
$edited  = isset($_GET['edited']);

// Filters — composite status resolution
$statusFilter = $_GET['status'] ?? '';
$searchCampaign = trim($_GET['campaign'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($statusFilter === 'unopened') {
    $where[] = "status='active' AND first_accessed_at IS NULL";
} elseif ($statusFilter === 'opened') {
    $where[] = "status='active' AND first_accessed_at IS NOT NULL";
} elseif ($statusFilter === 'draft') {
    $where[] = "status='draft'";
} elseif ($statusFilter === 'submitted') {
    $where[] = "status='submitted'";
} elseif ($statusFilter === 'expired') {
    $where[] = "status='expired'";
}
if ($searchCampaign !== '') {
    $where[] = "campaign_name LIKE :campaign";
    $params[':campaign'] = '%' . $searchCampaign . '%';
}
if ($dateFrom !== '') {
    $where[] = "created_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = "created_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = DB::prepare("SELECT COUNT(*) FROM links $whereClause");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$links = DB::prepare("SELECT * FROM links $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $links->bindValue($k, $v);
$links->bindValue(':limit', $perPage, PDO::PARAM_INT);
$links->bindValue(':offset', $offset, PDO::PARAM_INT);
$links->execute();

adminHeader('链接列表', 'links');
?>

<h1 class="page-title main-shell">📋 链接列表</h1>

<?php if ($deleted): ?>
    <div class="alert alert-success">✅ 链接已删除</div>
<?php endif; ?>
<?php if ($edited): ?>
    <div class="alert alert-success">✅ 链接已更新</div>
<?php endif; ?>

<div class="card main-shell">
    <form method="get" class="filter-bar">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div class="filter-group"><label>活动名称</label><input type="text" name="campaign" value="<?= htmlspecialchars($searchCampaign) ?>" placeholder="模糊搜索..."></div>
        <div class="filter-group"><label>创建日期从</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
        <div class="filter-group"><label>至</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
        <button type="submit" class="btn btn-sm btn-primary">🔍 查询</button>
        <a href="links.php" class="btn btn-sm btn-outline">重置</a>
        <a href="export.php?<?= http_build_query(array_filter(['status'=>$statusFilter, 'campaign'=>$searchCampaign, 'date_from'=>$dateFrom, 'date_to'=>$dateTo])) ?>" class="btn btn-sm btn-primary" style="margin-left:auto;background:#27ae60;">📥 导出CSV</a>
    </form>
    <div class="toolbar-row">
        <div class="chip-group">
            <a href="links.php" class="btn btn-sm <?= $statusFilter==='' ? 'btn-primary' : 'btn-outline' ?>">全部</a>
            <a href="links.php?status=unopened" class="btn btn-sm <?= $statusFilter==='unopened' ? 'btn-primary' : 'btn-outline' ?>">未打开</a>
            <a href="links.php?status=opened" class="btn btn-sm <?= $statusFilter==='opened' ? 'btn-primary' : 'btn-outline' ?>">已打开</a>
            <a href="links.php?status=draft" class="btn btn-sm <?= $statusFilter==='draft' ? 'btn-primary' : 'btn-outline' ?>">草稿</a>
            <a href="links.php?status=submitted" class="btn btn-sm <?= $statusFilter==='submitted' ? 'btn-primary' : 'btn-outline' ?>">已提交</a>
            <a href="links.php?status=expired" class="btn btn-sm <?= $statusFilter==='expired' ? 'btn-primary' : 'btn-outline' ?>">已过期</a>
        </div>
        <span class="text-muted">共 <?= $total ?> 条</span>
    </div>

    <div class="table-wrap">
    <table>
        <thead><tr>
            <th>ID</th><th>活动</th><th>Token</th><th>状态</th><th>访问</th><th>超时</th><th>创建时间</th><th>首次访问</th><th>过期时间</th><th>操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($links as $row):
            // Resolve display state
            if ($row['status'] === 'active') {
                $displayState = empty($row['first_accessed_at']) ? 'unopened' : 'opened';
            } else {
                $displayState = $row['status'];
            }
            $statusLabel = ['unopened'=>'未打开','opened'=>'已打开','draft'=>'草稿中','submitted'=>'已提交','expired'=>'已过期'][$displayState] ?? $displayState;
            $statusClass = 'badge-' . $displayState;
            // Check if absolutely expired (beyond absolute_expiry_hours from created_at)
            $absExpiryHours = (int)$row['absolute_expiry_hours'];
            $absDeadline = strtotime($row['created_at']) + $absExpiryHours * 3600;
            $isAbsolutelyExpired = (time() > $absDeadline);
        ?>
        <tr>
            <td>#<?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['campaign_name'] ?: '-') ?></td>
            <td>
                <code style="font-size:11px;"><?= htmlspecialchars(substr($row['token'], 0, 16)) ?>...</code>
                <button type="button" class="copy-link-btn" data-url="<?= htmlspecialchars(BASE_URL . '/access.php?token=' . $row['token']) ?>" data-campaign="<?= htmlspecialchars($row['campaign_name'] ?: '未命名') ?>" style="background:none;border:none;cursor:pointer;font-size:12px;padding:0 4px;" title="复制访问链接">📋</button>
            </td>
            <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
            <td><?= $row['access_count'] ?>/<?= $row['max_accesses'] ?></td>
            <td><?php $ts = (int)$row['access_timeout']; echo $ts >= 3600 ? round($ts/3600,1).'h' : round($ts/60).'min'; ?></td>
            <td><?= $row['created_at'] ?></td>
            <td><?= $row['first_accessed_at'] ?: '-' ?></td>
            <td><?= $row['expires_at'] ?: '未开始计时' ?></td>
            <td>
                <div class="table-actions">
                <a href="stats.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline">统计</a>
                <?php if (!empty(trim($row['target_content']))): ?>
                <a href="create.php?copy_from=<?= $row['id'] ?>" class="btn btn-sm btn-outline" title="基于此配置创建新链接">📋</a>
                <?php endif; ?>
                <?php if ($row['status'] === 'expired'): ?>
                    <?php if ($isAbsolutelyExpired): ?>
                    <span class="badge" style="background:#eee;color:#999;font-size:10px;">永久过期</span>
                    <?php else: ?>
                    <form method="post" onsubmit="return confirm('确定重新打开此链接？')">
                        <input type="hidden" name="reactivate_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-primary" style="background:#27ae60;">🔄 重开</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" onsubmit="return confirm('确定将此链接置为已过期？')">
                        <input type="hidden" name="expire_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" style="color:#c9403a;border-color:#c9403a;">⏹ 过期</button>
                    </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('确定删除此链接及所有访问记录？此操作不可撤销。')">
                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($links->rowCount() === 0): ?>
        <tr class="empty-row"><td colspan="10">暂无数据</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $query = http_build_query(array_filter(['status'=>$statusFilter, 'campaign'=>$searchCampaign, 'date_from'=>$dateFrom, 'date_to'=>$dateTo, 'page'=>$i]));
        ?>
            <a href="links.php?<?= $query ?>" class="<?= $i===$page ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Copy link to clipboard (format: 【活动名称】：链接)
document.querySelectorAll('.copy-link-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation();
        const url = this.getAttribute('data-url');
        const campaign = this.getAttribute('data-campaign') || '未命名';
        if (!url) return;
        const text = '【' + campaign + '】：' + url;
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(ex) {}
        document.body.removeChild(ta);
        const orig = this.textContent;
        this.textContent = '✅';
        setTimeout(() => { this.textContent = orig; }, 1500);
    });
});
</script>
<?php adminFooter(); ?>
