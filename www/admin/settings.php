<?php
/**
 * Settings — global default timeout, expiry, and admin password
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Password change (handled first, returns its own message)
    if (isset($_POST['change_password'])) {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $currentPw = $db->query("SELECT value FROM settings WHERE key='admin_password'")->fetchColumn() ?: ADMIN_PASSWORD;
        if ($oldPass !== $currentPw) {
            $message = '❌ 当前密码错误';
        } elseif (strlen($newPass) < 4) {
            $message = '❌ 新密码至少4位';
        } elseif ($newPass !== $confirm) {
            $message = '❌ 两次输入的新密码不一致';
        } else {
            $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_password', :v)")->execute([':v' => $newPass]);
            $message = '✅ 密码已修改，下次登录生效';
        }
    }

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

    if (!isset($_POST['change_password']) && !isset($message)) {
        $message = '✅ 设置已保存';
    }
}

// Load current values
$currentTimeout = $db->query("SELECT value FROM settings WHERE key='default_access_timeout'")->fetchColumn() ?: 600;
$currentExpiry  = $db->query("SELECT value FROM settings WHERE key='default_absolute_expiry_hours'")->fetchColumn() ?: 24;

adminHeader('系统设置', 'settings');
?>

<h1 class="page-title main-shell">⚙️ 系统设置</h1>

<?php if ($message): ?>
    <div class="alert alert-success main-shell"><?= $message ?></div>
<?php endif; ?>

<div class="card main-shell">
    <div class="card-header">🕐 默认过期配置</div>
    <p class="section-meta">这些默认值将在创建新链接时预填，每个链接可单独覆盖。</p>
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

<div class="card main-shell">
    <div class="card-header">🔑 修改管理员密码</div>
    <p class="section-meta">修改后即时生效。默认初始密码：<code><?= htmlspecialchars(ADMIN_PASSWORD) ?></code></p>
    <form method="post">
        <input type="hidden" name="change_password" value="1">
        <div class="form-row">
            <div class="form-group"><label>当前密码</label><input type="text" name="old_password" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>新密码</label><input type="text" name="new_password" required minlength="4"></div>
            <div class="form-group"><label>确认新密码</label><input type="text" name="confirm_password" required minlength="4"></div>
        </div>
        <button type="submit" class="btn btn-primary">🔒 修改密码</button>
    </form>
</div>

<div class="card main-shell">
    <div class="card-header">📊 数据库信息</div>
    <table class="kv-table">
        <tr><td>数据库路径</td><td><code><?= htmlspecialchars(DB_PATH) ?></code></td></tr>
        <tr><td>链接总数</td><td><?= $db->query("SELECT COUNT(*) FROM links")->fetchColumn() ?></td></tr>
        <tr><td>日志总数</td><td><?= $db->query("SELECT COUNT(*) FROM access_logs")->fetchColumn() ?></td></tr>
    </table>
</div>

<?php adminFooter(); ?>
