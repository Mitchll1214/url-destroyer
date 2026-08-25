<?php
/**
 * Settings — global default timeout, expiry, and admin password
 */

require_once __DIR__ . '/_lib.php';
requireLogin();

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Database backup / restore management (SQLite only) ──
    if (isset($_POST['backup_action'])) {
        $backupDir = dirname(DB_PATH) . '/backups';
        if (DB_DRIVER !== 'sqlite') {
            $message = '❌ 当前为 MySQL 模式，备份请使用数据库导出工具（mysqldump 等）。';
        } elseif (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        if ($_POST['backup_action'] === 'create' && DB_DRIVER === 'sqlite') {
            // 强制 checkpoint，确保 WAL 中已提交的数据合并回主库后再复制
            try { $db->exec("PRAGMA wal_checkpoint(TRUNCATE)"); } catch (Throwable $e) {}
            $dest = $backupDir . '/app_' . date('Ymd_His') . '.db';
            if (copy(DB_PATH, $dest)) {
                $message = '✅ 备份已创建：' . basename($dest);
            } else {
                $message = '❌ 备份失败，请检查备份目录写入权限。';
            }
        } elseif ($_POST['backup_action'] === 'delete' && DB_DRIVER === 'sqlite') {
            $file = basename((string)($_POST['backup_file'] ?? '')); // basename 防路径穿越
            if ($file === '' || !preg_match('/^app_\d{14}\.db$/', $file)) {
                $message = '❌ 非法的备份文件名。';
            } elseif (!is_file($backupDir . '/' . $file)) {
                $message = '❌ 备份文件不存在。';
            } elseif (@unlink($backupDir . '/' . $file)) {
                $message = '✅ 已删除备份：' . $file;
            } else {
                $message = '❌ 删除失败。';
            }
        }
    }

    // Password change (handled first, returns its own message)
    if (isset($_POST['change_password'])) {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $currentPw = DB::query("SELECT value FROM settings WHERE key='admin_password'")->fetchColumn() ?: ADMIN_PASSWORD;
        if ($oldPass !== $currentPw) {
            $message = '❌ 当前密码错误';
        } elseif (strlen($newPass) < 4) {
            $message = '❌ 新密码至少4位';
        } elseif ($newPass !== $confirm) {
            $message = '❌ 两次输入的新密码不一致';
        } else {
            DB::prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_password', :v)")->execute([':v' => $newPass]);
            $message = '✅ 密码已修改，下次登录生效';
        }
    }

    // Update default timeout (前端输入小时，存储为秒)
    if (isset($_POST['default_access_timeout'])) {
        $timeoutHours = max(0.1, (float)$_POST['default_access_timeout']);
        $timeoutSeconds = (int)($timeoutHours * 3600);
        DB::prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('default_access_timeout', :v)")
           ->execute([':v' => $timeoutSeconds]);
    }

    // Update default absolute expiry
    if (isset($_POST['default_absolute_expiry_hours'])) {
        $expiry = max(1, (int)$_POST['default_absolute_expiry_hours']);
        DB::prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('default_absolute_expiry_hours', :v)")
           ->execute([':v' => $expiry]);
    }

    if (!isset($_POST['change_password']) && !isset($message)) {
        $message = '✅ 设置已保存';
    }
}

// Load current values (优先级：数据库 > 环境变量 > config.php 常量)
$currentTimeout = DB::query("SELECT value FROM settings WHERE key='default_access_timeout'")->fetchColumn() ?: DEFAULT_ACCESS_TIMEOUT;
$currentExpiry  = DB::query("SELECT value FROM settings WHERE key='default_absolute_expiry_hours'")->fetchColumn() ?: DEFAULT_ABSOLUTE_EXPIRY_HOURS;
// 前端显示用：超时秒数转为小时
$currentTimeoutHours = round($currentTimeout / 3600, 1);

adminHeader('系统设置', 'settings');
?>

<h1 class="page-title main-shell">⚙️ 系统设置</h1>

<?php if ($message): ?>
    <div class="alert alert-success main-shell"><?= $message ?></div>
<?php endif; ?>

<div class="card main-shell">
    <div class="card-header">🕐 默认过期配置</div>
    <p class="section-meta">这些默认值将在创建新链接时预填，每个链接可单独覆盖。<br>💡 通过 Docker 环境变量 <code>DEFAULT_ACCESS_TIMEOUT</code>（小时）和 <code>DEFAULT_ABSOLUTE_EXPIRY_HOURS</code>（小时）可设置初始值，重建容器不丢失。</p>
    <form method="post">
        <div class="form-row">
            <div class="form-group">
                <label>首次访问后超时 (小时)</label>
                <input type="number" name="default_access_timeout" value="<?= $currentTimeoutHours ?>" min="0.1" step="any" required>
                <span class="text-muted">默认 <?= $currentTimeoutHours ?> 小时 ≈ <?= round($currentTimeout/86400, 1) ?> 天</span>
            </div>
            <div class="form-group">
                <label>未打开自动过期 (小时)</label>
                <input type="number" name="default_absolute_expiry_hours" value="<?= $currentExpiry ?>" min="1" required>
                <span class="text-muted">默认 <?= $currentExpiry ?> 小时 ≈ <?= round($currentExpiry/24, 1) ?> 天</span>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">💾 保存设置</button>
    </form>
</div>

<div class="card main-shell">
    <div class="card-header">🔑 修改管理员密码</div>
    <p class="section-meta">修改后即时生效。初始密码来源：环境变量 <code>ADMIN_PASSWORD</code> > 默认值 <code><?= htmlspecialchars(ADMIN_PASSWORD) ?></code><br>💡 在 Docker 中设置 <code>ADMIN_PASSWORD</code> 环境变量可永久固定初始密码。</p>
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
        <tr><td>数据库类型</td><td><?= DB_DRIVER === 'mysql' ? 'MySQL' : 'SQLite' ?></td></tr>
        <tr><td>链接总数</td><td><?= DB::query("SELECT COUNT(*) FROM links")->fetchColumn() ?></td></tr>
        <tr><td>日志总数</td><td><?= DB::query("SELECT COUNT(*) FROM access_logs")->fetchColumn() ?></td></tr>
    </table>
</div>

<?php if (DB_DRIVER === 'sqlite'): ?>
<?php $backupDir = dirname(DB_PATH) . '/backups'; ?>
<div class="card main-shell">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <span>💾 数据库备份 (SQLite)</span>
        <form method="post" style="margin:0;">
            <input type="hidden" name="backup_action" value="create">
            <button type="submit" class="btn btn-sm btn-primary">＋ 立即备份</button>
        </form>
    </div>
    <p class="section-meta">备份文件保存在备份目录 <code><?= htmlspecialchars($backupDir) ?></code>,文件名按时间生成,可删除。</p>
    <?php
    $backups = [];
    if (is_dir($backupDir)) {
        foreach (glob($backupDir . '/app_*.db') ?: [] as $f) {
            $backups[] = ['file' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
        }
        usort($backups, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    }
    ?>
    <?php if (empty($backups)): ?>
        <div class="alert alert-info" style="margin-bottom:0;">暂无备份。</div>
    <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>备份文件</th><th>大小</th><th>备份时间</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
            <tr>
                <td><code style="font-size:12px;"><?= htmlspecialchars($b['file']) ?></code></td>
                <td><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                <td><?= date('Y-m-d H:i:s', $b['mtime']) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('确定删除备份 <?= htmlspecialchars($b['file']) ?>？此操作不可恢复。')">
                        <input type="hidden" name="backup_action" value="delete">
                        <input type="hidden" name="backup_file" value="<?= htmlspecialchars($b['file']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php adminFooter(); ?>
