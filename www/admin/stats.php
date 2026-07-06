<?php
/**
 * Link Statistics — view access logs for a specific link
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();

$linkId = (int)($_GET['id'] ?? 0);
if ($linkId <= 0) {
    header('Location: links.php');
    exit;
}

// Fetch link info
$link = $db->prepare("SELECT * FROM links WHERE id = :id");
$link->execute([':id' => $linkId]);
$link = $link->fetch();

if (!$link) {
    echo '<div class="alert alert-error">链接不存在</div>';
    adminFooter();
    exit;
}

// Handle clear logs
if (isset($_POST['clear_logs'])) {
    $db->prepare("DELETE FROM access_logs WHERE link_id = :id")->execute([':id' => $linkId]);
    header("Location: stats.php?id=$linkId&cleared=1");
    exit;
}

$cleared = isset($_GET['cleared']);

// Pagination for logs
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$totalLogs = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE link_id = :id");
$totalLogs->execute([':id' => $linkId]);
$totalLogs = $totalLogs->fetchColumn();
$totalPages = max(1, ceil($totalLogs / $perPage));
$offset = ($page - 1) * $perPage;

$logs = $db->prepare("SELECT * FROM access_logs WHERE link_id = :id ORDER BY id DESC LIMIT :limit OFFSET :offset");
$logs->bindValue(':id', $linkId, PDO::PARAM_INT);
$logs->bindValue(':limit', $perPage, PDO::PARAM_INT);
$logs->bindValue(':offset', $offset, PDO::PARAM_INT);
$logs->execute();

adminHeader('访问统计 #' . $linkId, 'links');
?>

<h1 class="page-title main-shell">📈 访问统计 — #<?= $linkId ?> <?= htmlspecialchars($link['campaign_name']) ?></h1>

<?php if ($cleared): ?>
    <div class="alert alert-success main-shell">✅ 访问日志已清空</div>
<?php endif; ?>

<!-- Link Summary -->
<div class="stats-grid main-shell">
    <div class="stat-card">
        <div class="stat-value"><?= $link['access_count'] ?></div>
        <div class="stat-label">总访问次数</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalLogs ?></div>
        <div class="stat-label">日志条数</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="font-size:24px;word-break:break-all;"><?= htmlspecialchars($link['token']) ?></div>
        <div class="stat-label">Token</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="font-size:16px;">
            <span class="badge badge-<?= $link['status'] ?>"><?= ['active'=>'活跃','opened'=>'已打开','expired'=>'已过期'][$link['status']] ?></span>
        </div>
        <div class="stat-label">状态</div>
    </div>
</div>

<div class="card main-shell">
    <div class="card-header">🔗 链接详情</div>
    <table class="kv-table">
        <tr><td>访问链接</td><td><div class="url-display"><?= htmlspecialchars(BASE_URL . '/access.php?token=' . $link['token']) ?></div></td></tr>
        <tr><td>创建时间</td><td><?= $link['created_at'] ?></td></tr>
        <tr><td>首次访问</td><td><?= $link['first_accessed_at'] ?: '尚未访问' ?></td></tr>
        <tr><td>过期时间</td><td><?= $link['expires_at'] ?: '未开始计时' ?></td></tr>
        <tr><td>访问超时</td><td><?= $link['access_timeout'] ?> 秒 (<?= round($link['access_timeout']/60, 1) ?> 分钟)</td></tr>
        <tr><td>绝对过期</td><td><?= $link['absolute_expiry_hours'] ?> 小时</td></tr>
        <tr><td>最大次数</td><td><?= $link['max_accesses'] ?></td></tr>
    </table>
</div>

<!-- Form Preview -->
<?php
$cfg = null;
$tc = $link['target_content'];
if (!empty(trim($tc))) {
    $decoded = json_decode($tc, true);
    if (is_array($decoded) && ($decoded['type'] ?? '') === 'form_builder') {
        $cfg = $decoded;
    }
}
if ($cfg):
?>
<details class="card main-shell">
    <summary class="card-header">👁 表单预览 <span style="font-size:11px;color:#888;">(点击展开)</span></summary>
    <div style="display:flex;justify-content:center;">
        <div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;padding:20px;max-width:420px;width:100%;">
            <div style="background:#fff;border-radius:10px;padding:20px;">
                <h3 style="text-align:center;color:#333;margin-bottom:4px;font-size:16px;"><?= htmlspecialchars($cfg['title'] ?? '') ?></h3>
                <?php if (!empty($cfg['subtitle'])): ?>
                <p style="text-align:center;color:#888;font-size:11px;margin-bottom:16px;"><?= htmlspecialchars($cfg['subtitle']) ?></p>
                <?php endif; ?>
                <?php foreach ($cfg['fields'] ?? [] as $f):
                    $label = htmlspecialchars($f['label'] ?? $f['name'] ?? '');
                    $type  = $f['type'] ?? 'text';
                    $ph    = htmlspecialchars($f['placeholder'] ?? '');
                    $dv    = htmlspecialchars($f['default_value'] ?? '');
                    $req   = !empty($f['required']);
                    $opts  = $f['options'] ?? [];
                ?>
                <div style="margin-bottom:10px;">
                    <label style="font-size:11px;font-weight:600;color:#444;"><?= $label ?><?= $req ? ' <span style="color:#e74c3c;">*</span>' : '' ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <div style="width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:11px;min-height:40px;background:#fafafa;color:#999;"><?= $dv ?: $ph ?></div>
                    <?php elseif ($type === 'select'): ?>
                        <div style="width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:11px;background:#fafafa;color:#999;"><?= $ph ?: ($opts[0] ?? '请选择') ?></div>
                    <?php else: ?>
                        <div style="width:100%;padding:6px 8px;border:1px solid #ddd;border-radius:4px;font-size:11px;background:#fafafa;color:#999;"><?= $dv ?: $ph ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div style="width:100%;padding:8px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;text-align:center;">📤 <?= htmlspecialchars($cfg['submit_text'] ?? '提交') ?></div>
            </div>
        </div>
    </div>
</details>
<?php endif; ?>

<!-- Access Logs -->
<div class="card main-shell">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <span>📋 访问记录 (<?= $totalLogs ?> 条)</span>
        <?php if ($totalLogs > 0): ?>
        <form method="post" onsubmit="return confirm('确定清空此链接的所有访问记录？')">
            <input type="hidden" name="clear_logs" value="1">
            <button type="submit" class="btn btn-sm btn-danger">清空日志</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr>
            <th>#</th><th>IP</th><th>User-Agent</th><th>Referer</th><th>表单数据</th><th>访问时间</th>
        </tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= $log['id'] ?></td>
            <td><code><?= htmlspecialchars($log['ip']) ?></code></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['user_agent']) ?>"><?= htmlspecialchars(mb_strlen($log['user_agent'])>40 ? mb_substr($log['user_agent'],0,40).'...' : $log['user_agent']) ?></td>
            <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['referer']) ?>"><?= htmlspecialchars($log['referer'] ?: '-') ?></td>
            <td>
                <?php if (!empty($log['form_data'])): ?>
                <details>
                    <summary style="cursor:pointer;color:#e94560;">查看数据</summary>
                    <pre style="font-size:11px;background:#f5f5f5;padding:8px;border-radius:4px;max-width:300px;overflow:auto;"><?= htmlspecialchars(json_encode(json_decode($log['form_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td><?= $log['accessed_at'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($logs->rowCount() === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:#888;padding:32px;">暂无访问记录</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="stats.php?id=<?= $linkId ?>&page=<?= $i ?>" class="<?= $i===$page ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<div style="margin-top:16px;">
    <a href="links.php" class="btn btn-outline">← 返回链接列表</a>
</div>

<?php adminFooter(); ?>
