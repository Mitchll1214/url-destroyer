<?php
/**
 * Global Configuration
 */

// Timezone — 北京时间
date_default_timezone_set('Asia/Shanghai');

// Admin password (change in production!)
define('ADMIN_PASSWORD', 'admin123');

// Admin URL path — customize to hide the backend entrance (e.g. 'my-secret-panel')
define('ADMIN_PATH', 'admin');

// Default settings — can be overridden via admin/settings.php
define('DEFAULT_ACCESS_TIMEOUT', 600);      // 10 minutes after first access
define('DEFAULT_ABSOLUTE_EXPIRY_HOURS', 24); // 24 hours from creation

// Database path
define('DB_PATH', __DIR__ . '/../data/app.db');

// Site base URL — auto-detect (supports reverse proxy via X-Forwarded-Proto/Host)
// 如果反向代理未传正确 Header，手动取消注释下面一行并填写你的域名：
// define('BASE_URL', 'https://your-domain.com');
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

    // 计算 Web 根路径：如果当前脚本在管理子目录下，回到根目录
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $adminSuffix = '/' . ADMIN_PATH;
    if (str_ends_with($scriptDir, $adminSuffix)) {
        $scriptDir = substr($scriptDir, 0, -strlen($adminSuffix));
    }

    define('BASE_URL', $scheme . '://' . $host . $scriptDir);
}
