#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔗 url-destroyer — 链接销毁系统"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

DATA_DIR="/var/www/data"

# Ensure the data directory exists and has correct permissions
if [ -d "$DATA_DIR" ]; then
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
    chmod 775 "$DATA_DIR" 2>/dev/null || true
else
    mkdir -p "$DATA_DIR"
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
    chmod 775 "$DATA_DIR" 2>/dev/null || true
fi

echo "  ✓ 数据目录: $DATA_DIR"

# Database status — use PHP (same code path as the app, most reliable)
php -r '
// Resolve DB path the same way config.php does
$envPath = getenv("DB_PATH");
$dbFile = $envPath ?: "/var/www/html/../data/app.db";
// Normalize: resolve parent dir, then append filename
$dir  = dirname($dbFile);
$name = basename($dbFile);
$realDir = is_dir($dir) ? realpath($dir) : $dir;
$dbFile = $realDir . "/" . $name;

if (file_exists($dbFile)) {
    $size = filesize($dbFile);
    $sizeStr = $size > 1024 ? round($size/1024, 1)."KB" : $size."B";
    echo "  ✓ 数据库: {$sizeStr}";
    try {
        $pdo = new PDO("sqlite:$dbFile", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $count = $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn();
        $latest = $pdo->query("SELECT MAX(created_at) FROM links")->fetchColumn();
        echo " | {$count} 条链接";
        if ($latest) echo " | 最近: {$latest}";
    } catch (Exception $e) {
        // DB exists but might be locked or corrupt — still counts as "exists"
    }
    echo PHP_EOL;
} else {
    echo "  ⚠ 数据库尚未创建（首次访问后台时自动初始化）" . PHP_EOL;
    echo "    路径: {$dbFile}" . PHP_EOL;
}
'

echo ""
echo "  💡 更新镜像不丢数据的正确流程："
echo "     docker compose pull && docker compose up -d"
echo "     # 切勿使用 down -v，那会删除数据！"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Start Apache in foreground
exec apache2-foreground
