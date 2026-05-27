<?php
/**
 * Settings — global default timeout, expiry, and admin password
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update default timeout
    if (isset($_POST['default_access_timeout'])) {
        $timeout = max(10, (int)$_POST['default_access_timeout']);
        $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('default_access_timeout', :v)")
           ->execute([':v' => $timeout]);
    }

    // Update default absolute expiry
    if (isset($_POST['default_absolute_expiry_hours'])) {
        $expiry = max(1, (int)$_POST['default_absolute_expiry_hours']);
        $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('default_absolute_expiry_hours', :v)")
           ->execute([':v' => $expiry]);
    }

    $message = '✅ 设置已保存';
}

// Load current values
$currentTimeout = $db->query("SELECT value FROM settings WHERE key='default_access_timeout'")->fetchColumn() ?: 600;
$currentExpiry  = $db->query("SELECT value FROM settings WHERE key='default_absolute_expiry_hours'")->fetchColumn() ?: 24;

adminHeader('系统设置', 'settings');
?>

<h1 class="page-title">⚙️ 系统设置</h1>

<?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">🕐 默认过期配置</div>
    <p class="text-muted mb-16">这些默认值将在创建新链接时预填，每个链接可单独覆盖。</p>
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>首次访问后超时 (秒)</label>
                <input type="number" name="default_access_timeout" value="<?= $currentTimeout ?>" min="10" required>
                <span class="text-muted">默认 <?= $currentTimeout ?> 秒 ≈ <?= round($currentTimeout/60, 1) ?> 分钟</span>
            </div>
            <div class="form-group">
                <label>未打开自动过期 (小时)</label>
                <input type="number" name="default_absolute_expiry_hours" value="<?= $currentExpiry ?>" min="1" required>
                <span class="text-muted">默认 <?= $currentExpiry ?> 小时</span>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">💾 保存设置</button>
    </form>
</div>

<div class="card">
    <div class="card-header">🔑 管理员密码</div>
    <p class="text-muted mb-16">当前密码定义在 <code>www/config.php</code> 的 <code>ADMIN_PASSWORD</code> 常量中。修改密码请编辑该文件后重启容器。</p>
    <div class="form-group">
        <label>当前密码</label>
        <input type="text" value="<?= htmlspecialchars(ADMIN_PASSWORD) ?>" readonly style="background:#f5f5f5;">
    </div>
</div>

<div class="card">
    <div class="card-header">📊 数据库信息</div>
    <table style="width:auto;">
        <tr><td style="font-weight:600;width:120px;">数据库路径</td><td><code><?= htmlspecialchars(DB_PATH) ?></code></td></tr>
        <tr><td style="font-weight:600;">链接总数</td><td><?= $db->query("SELECT COUNT(*) FROM links")->fetchColumn() ?></td></tr>
        <tr><td style="font-weight:600;">日志总数</td><td><?= $db->query("SELECT COUNT(*) FROM access_logs")->fetchColumn() ?></td></tr>
    </table>
</div>

<?php adminFooter(); ?>
