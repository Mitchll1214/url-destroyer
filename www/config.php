<?php
/**
 * Global Configuration
 *
 * 优先级：数据库 settings 表 > 环境变量 > 本文件常量
 * 通过 Docker 环境变量设置后，即使重建容器也不会丢失配置。
 * docker-compose.yml 示例：
 *   environment:
 *     - ADMIN_PASSWORD=my-secure-pw
 *     - DEFAULT_ACCESS_TIMEOUT=1800
 *     - DEFAULT_ABSOLUTE_EXPIRY_HOURS=72
 *     - BASE_URL=https://your-domain.com
 */

// Timezone — 北京时间
date_default_timezone_set('Asia/Shanghai');

/**
 * 从环境变量读取值，不存在则返回默认值
 */
function env(string $name, mixed $default = null): mixed {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

// Admin password — 环境变量优先于硬编码默认值
define('ADMIN_PASSWORD', env('ADMIN_PASSWORD', 'admin123'));

// Admin URL path — 支持环境变量覆盖
define('ADMIN_PATH', env('ADMIN_PATH', 'admin'));

// Default settings — 环境变量优先于硬编码默认值
define('DEFAULT_ACCESS_TIMEOUT', (int)((float)env('DEFAULT_ACCESS_TIMEOUT', '24') * 3600));  // hours → seconds
define('DEFAULT_ABSOLUTE_EXPIRY_HOURS', (int)env('DEFAULT_ABSOLUTE_EXPIRY_HOURS', 168)); // 7 days

// Database path — 支持环境变量覆盖（例如将数据库放在持久卷中）
define('DB_PATH', env('DB_PATH', __DIR__ . '/../data/app.db'));

// Site base URL — 环境变量 > 自动检测
$envBaseUrl = env('BASE_URL', '');
if ($envBaseUrl !== '') {
    define('BASE_URL', rtrim($envBaseUrl, '/'));
} else {
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
