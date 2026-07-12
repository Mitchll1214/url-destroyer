#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔗 url-destroyer — 链接销毁系统"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

DATA_DIR="/var/www/data"
DB_FILE="$DATA_DIR/app.db"

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
echo "  ✓ 权限已就绪"

# Database status
if [ -f "$DB_FILE" ]; then
    DB_SIZE=$(du -h "$DB_FILE" 2>/dev/null | cut -f1)
    echo "  ✓ 数据库已存在: ${DB_SIZE}"
    # Row count via PHP
    COUNT=$(php -r '
        try {
            $pdo = new PDO("sqlite:/var/www/data/app.db", null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            echo $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn();
        } catch (Exception $e) { echo "?"; }
    ' 2>/dev/null)
    echo "  ✓ 链接记录: ${COUNT} 条"
else
    echo "  ⚠ 数据库文件不存在: $DB_FILE"
    echo "  "
    # Show what's actually there
    ITEMS=$(ls -A "$DATA_DIR" 2>/dev/null)
    if [ -n "$ITEMS" ]; then
        echo "  📁 数据目录内容:"
        ls -lhA "$DATA_DIR" 2>/dev/null | tail -n +2 | while IFS= read -r line; do
            echo "     $line"
        done
    else
        echo "  📁 数据目录为空（首次启动正常现象）"
    fi
fi

echo ""
echo "  💡 更新镜像不丢数据: docker compose pull && docker compose up -d"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Start Apache in foreground
exec apache2-foreground
