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
        <link rel="icon" type="image/svg+xml" href="../favicon.svg">
        <link rel="stylesheet" href="../assets/style.css">
    </head>
    <body class="login-page">
    <!-- Floating particles -->
    <div class="login-bg-particles" aria-hidden="true">
        <span style="left:10%;animation-duration:14s;animation-delay:0s;width:2px;height:2px;"></span>
        <span style="left:25%;animation-duration:18s;animation-delay:2s;width:3px;height:3px;"></span>
        <span style="left:40%;animation-duration:12s;animation-delay:1s;width:2px;height:2px;"></span>
        <span style="left:55%;animation-duration:16s;animation-delay:3s;width:4px;height:4px;"></span>
        <span style="left:70%;animation-duration:20s;animation-delay:0.5s;width:2px;height:2px;"></span>
        <span style="left:85%;animation-duration:15s;animation-delay:1.5s;width:3px;height:3px;"></span>
    </div>
    <div class="login-container">
        <div class="login-icon">🔐</div>
        <h1>管理员登录</h1>
        <p class="login-subtitle">链接销毁管理系统</p>
        <?php if (!empty($GLOBALS['login_error'])): ?>
            <div class="alert alert-error"><span class="alert-icon">⚠️</span><?= htmlspecialchars($GLOBALS['login_error']) ?></div>
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
        <title><?= htmlspecialchars($title) ?> — 链接销毁系统</title>
        <link rel="icon" type="image/svg+xml" href="../favicon.svg">
        <link rel="stylesheet" href="../assets/style.css">
    </head>
    <body>
    <div class="admin-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="logo">🔗 链接<span>销毁</span><span class="version">ADMIN</span></div>
            <nav>
                <a href="index.php"    data-icon="📊" class="<?= $activeNav==='dashboard' ? 'active' : '' ?>"><span class="nav-icon">📊</span><span class="nav-text">仪表盘</span></a>
                <a href="create.php"   data-icon="➕" class="<?= $activeNav==='create'   ? 'active' : '' ?>"><span class="nav-icon">➕</span><span class="nav-text">创建链接</span></a>
                <a href="links.php"    data-icon="📋" class="<?= $activeNav==='links'    ? 'active' : '' ?>"><span class="nav-icon">📋</span><span class="nav-text">链接列表</span></a>
                <a href="settings.php" data-icon="⚙️" class="<?= $activeNav==='settings' ? 'active' : '' ?>"><span class="nav-icon">⚙️</span><span class="nav-text">设置</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">👤</div>
                    <div>
                        <div class="user-name">管理员</div>
                        <div class="user-role">后台控制台</div>
                    </div>
                </div>
            </div>
        </aside>
        <main class="main">
        <div class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" title="折叠菜单">☰</button>
            <div class="breadcrumb">管理后台 <span class="breadcrumb-sep">/</span> <span><?= htmlspecialchars($title) ?></span></div>
            <div class="topbar-spacer"></div>
            <div class="topbar-actions">
                <a href="?logout=1" class="logout-btn">
                    <svg class="logout-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    退出
                </a>
            </div>
        </div>
        <div class="main-inner">
    <?php
}

function adminFooter(): void {
    ?>
        </div><!-- /.main-inner -->
        </main>
    </div>
    <script>
    (function(){
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
