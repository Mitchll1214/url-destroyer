<?php
/**
 * Admin helpers: authentication and shared layout
 */

require_once __DIR__ . '/../db.php';

// ── Authentication ──
session_start();

function getAdminPassword(): string {
    $db = getDB();
    $stored = $db->query("SELECT value FROM settings WHERE key='admin_password'")->fetchColumn();
    return $stored ?: ADMIN_PASSWORD;
}

function requireLogin(): void {
    if (!empty($_SESSION['admin_logged_in'])) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === getAdminPassword()) {
            $_SESSION['admin_logged_in'] = true;
            return;
        }
        $GLOBALS['login_error'] = '密码错误';
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
        <title>管理员登录 — 动态网址销毁系统</title>
        <link rel="stylesheet" href="../assets/style.css">
        <style>
            .login-container {
                max-width: 400px; margin: 80px auto; padding: 40px;
                background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .login-container h1 { text-align: center; margin-bottom: 24px; font-size: 20px; }
        </style>
    </head>
    <body>
    <div class="login-container">
        <h1>🔐 管理员登录</h1>
        <?php if (!empty($GLOBALS['login_error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($GLOBALS['login_error']) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="password" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">登录</button>
        </form>
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
