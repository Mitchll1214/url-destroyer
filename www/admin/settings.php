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

    // ── Admin account management (multi-admin) ──
    if (isset($_POST['account_action'])) {
        $action = $_POST['account_action'];
        $accUser = trim($_POST['acc_username'] ?? '');
        $accNew  = $_POST['acc_password'] ?? '';

        if ($action === 'add') {
            if ($accUser === '' || strlen($accNew) < 4) {
                $message = '❌ 账号不能为空，密码至少 4 位。';
            } else {
                $exists = DB::prepare("SELECT COUNT(*) FROM admins WHERE username = :u");
                $exists->execute([':u' => $accUser]);
                if ((int)$exists->fetchColumn() > 0) {
                    $message = '❌ 账号已存在：' . htmlspecialchars($accUser);
                } else {
                    DB::prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (:u, :h, datetime('now','localtime'))")
                       ->execute([':u' => $accUser, ':h' => password_hash($accNew, PASSWORD_DEFAULT)]);
                    $message = '✅ 已新增账号：' . htmlspecialchars($accUser);
                }
            }
        } elseif ($action === 'delete') {
            if ($accUser === '') {
                $message = '❌ 缺少账号。';
            } elseif ($accUser === currentAdmin()) {
                $message = '❌ 不能删除当前登录的账号。';
            } else {
                DB::prepare("DELETE FROM admins WHERE username = :u")->execute([':u' => $accUser]);
                $message = '✅ 已删除账号：' . htmlspecialchars($accUser);
            }
        } elseif ($action === 'password') {
            if ($accUser === '' || strlen($accNew) < 4) {
                $message = '❌ 账号不能为空，新密码至少 4 位。';
            } else {
                DB::prepare("UPDATE admins SET password_hash = :h WHERE username = :u")->execute([':h' => password_hash($accNew, PASSWORD_DEFAULT), ':u' => $accUser]);
                $message = '✅ 已更新账号密码：' . htmlspecialchars($accUser);
            }
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

    if (!isset($_POST['account_action']) && !isset($_POST['backup_action']) && !isset($message)) {
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
    <div class="card-header">👥 管理员账号管理</div>
    <p class="section-meta">所有管理员权限相同。当前管理员：<strong><?= htmlspecialchars(currentAdmin()) ?></strong></p>

    <!-- 新增账号 -->
    <form method="post" style="margin-bottom:18px;">
        <input type="hidden" name="account_action" value="add">
        <div class="form-row">
            <div class="form-group"><label>新账号</label><input type="text" name="acc_username" required placeholder="登录账号" maxlength="64"></div>
            <div class="form-group"><label>密码</label><input type="password" name="acc_password" required minlength="4" placeholder="至少 4 位"></div>
            <div class="form-group" style="display:flex;align-items:flex-end;"><button type="submit" class="btn btn-primary">＋ 新增账号</button></div>
        </div>
    </form>

    <?php $admins = DB::query("SELECT username, created_at FROM admins ORDER BY username")->fetchAll(); ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>账号</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a): $isSelf = ($a['username'] === currentAdmin()); ?>
        <tr>
            <td><strong><?= htmlspecialchars($a['username']) ?></strong><?= $isSelf ? ' <span class="badge badge-active" style="padding:2px 8px;">当前</span>' : '' ?></td>
            <td><?= htmlspecialchars($a['created_at']) ?></td>
            <td>
                <div class="table-actions">
                    <form method="post" onsubmit="var p=prompt('为账号 [<?= htmlspecialchars($a['username']) ?>] 设置新密码（至少4位）：');if(!p){return false;}this.acc_password.value=p;return true;">
                        <input type="hidden" name="account_action" value="password">
                        <input type="hidden" name="acc_username" value="<?= htmlspecialchars($a['username']) ?>">
                        <input type="hidden" name="acc_password" value="">
                        <button type="submit" class="btn btn-sm btn-outline">改密</button>
                    </form>
                    <?php if (!$isSelf): ?>
                    <form method="post" onsubmit="return confirm('确定删除账号 [<?= htmlspecialchars($a['username']) ?>]？')">
                        <input type="hidden" name="account_action" value="delete">
                        <input type="hidden" name="acc_username" value="<?= htmlspecialchars($a['username']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
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
