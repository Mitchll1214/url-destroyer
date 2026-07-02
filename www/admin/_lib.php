<?php
/**
 * Admin helpers: authentication and shared layout
 */

require_once __DIR__ . '/../db.php';

// ── Authentication ──
session_start();

function getClientIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function getAdminPassword(): string {
    $db = getDB();
    $stored = $db->query("SELECT value FROM settings WHERE key='admin_password'")->fetchColumn();
    return $stored ?: ADMIN_PASSWORD;
}

function requireLogin(): void {
    if (!empty($_SESSION['admin_logged_in'])) return;

    $db = getDB();
    $ip = getClientIP();

    // ── Rate limiting: max 5 attempts in 10 minutes; lockout 60s after 5 failures ──
    $windowMinutes = 10;
    $maxAttempts = 5;
    $lockoutSeconds = 60;

    // Clean up old attempts (> window)
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', 'localtime', '-{$windowMinutes} minutes')");

    // Count recent failed attempts
    $countStmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip");
    $countStmt->execute([':ip' => $ip]);
    $failedCount = (int)$countStmt->fetchColumn();

    if ($failedCount >= $maxAttempts) {
        // Get the earliest attempt in the window to calculate cooldown
        $earliest = $db->prepare("SELECT attempted_at FROM login_attempts WHERE ip = :ip ORDER BY attempted_at DESC LIMIT 1");
        $earliest->execute([':ip' => $ip]);
        $lastAttemptTime = $earliest->fetchColumn();
        $lastAttempt = new DateTime($lastAttemptTime);
        $now = new DateTime('now');
        $secondsSinceLast = $now->getTimestamp() - $lastAttempt->getTimestamp();
        $waitSeconds = max(0, $lockoutSeconds - $secondsSinceLast);

        if ($waitSeconds > 0) {
            $GLOBALS['login_error'] = "尝试次数过多，请 {$waitSeconds} 秒后再试。";
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        // Allow attempt only if not rate-limited
        if ($failedCount < $maxAttempts) {
            if (hash_equals(getAdminPassword(), $_POST['password'])) {
                // Success: clear all attempts for this IP
                $db->prepare("DELETE FROM login_attempts WHERE ip = :ip")->execute([':ip' => $ip]);
                $_SESSION['admin_logged_in'] = true;
                return;
            }
            // Record failed attempt
            $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)")->execute([':ip' => $ip]);
            $GLOBALS['login_error'] = '密码错误';
        }
    }

    showLogin();
    exit;
}

function showLogin(): void {
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录 — 链接销毁系统</title>
        <link rel="stylesheet" href="../assets/style.css">
    </head>
    <body class="login-page">
    <div class="login-container">
        <div class="login-icon">🔐</div>
        <h1>管理员登录</h1>
        <p class="login-subtitle">链接销毁管理系统</p>
        <?php if (!empty($GLOBALS['login_error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($GLOBALS['login_error']) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="password" required autofocus placeholder="请输入密码">
            </div>
            <button type="submit" class="btn btn-primary">🚀 登 录</button>
        </form>
        <p class="login-footer">连续 5 次密码错误将锁定 60 秒</p>
    </div>
    </body></html>
    <?php
}

function adminHeader(string $title, string $activeNav = 'dashboard'): void {
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> — 动态网址销毁系统</title>
        <link rel="stylesheet" href="../assets/style.css">
    </head>
    <body>
    <div class="admin-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="logo">🔗 链接<span>销毁</span></div>
            <nav>
                <a href="index.php"        class="<?= $activeNav==='dashboard' ? 'active' : '' ?>">📊 仪表盘</a>
                <a href="create.php"       class="<?= $activeNav==='create'   ? 'active' : '' ?>">➕ 创建链接</a>
                <a href="links.php"        class="<?= $activeNav==='links'    ? 'active' : '' ?>">📋 链接列表</a>
                <a href="settings.php"     class="<?= $activeNav==='settings' ? 'active' : '' ?>">⚙️ 设置</a>
            </nav>
        </aside>
        <main class="main">
        <div class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" title="折叠菜单">☰</button>
            <span style="flex:1;"></span>
            <a href="?logout=1" class="logout-btn">🚪 退出</a>
        </div>
    <?php
}

function adminFooter(): void {
    ?>
        </main>
    </div>
    <script>
    (function(){
        var sidebar = document.getElementById('sidebar');
        var toggle = document.getElementById('sidebarToggle');
        var wrapper = document.querySelector('.admin-wrapper');
        var saved = localStorage.getItem('sidebar_collapsed');
        if (saved === '1') { wrapper.classList.add('sidebar-collapsed'); }
        toggle.addEventListener('click', function(){
            wrapper.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar_collapsed', wrapper.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
    })();
    </script>
    </body></html>
    <?php
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
