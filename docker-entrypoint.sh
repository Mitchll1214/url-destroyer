#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache
set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔗 url-destroyer — 链接销毁系统"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

DATA_DIR="/var/www/data"
DB_FILE="$DATA_DIR/app.db"

# Ensure the data directory exists and has correct permissions
if [ -d "$DATA_DIR" ]; then
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
    chmod 775 "$DATA_DIR" 2>/dev/null || true
    echo "  ✓ 数据目录: $DATA_DIR"
else
    mkdir -p "$DATA_DIR"
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
    chmod 775 "$DATA_DIR" 2>/dev/null || true
    echo "  ✓ 数据目录已创建: $DATA_DIR"
fi

# Database status check
if [ -f "$DB_FILE" ]; then
    DB_SIZE=$(du -h "$DB_FILE" 2>/dev/null | cut -f1)
    LINK_COUNT=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM links;" 2>/dev/null || echo "?")
    echo "  ✓ 数据库: ${DB_SIZE} | ${LINK_COUNT} 条链接记录"
else
    echo "  ⚠ 数据库尚未创建（首次访问后台时自动初始化）"
    echo "    路径: $DB_FILE"
fi

# Check if data dir looks like a valid bind mount
if mountpoint -q "$DATA_DIR" 2>/dev/null; then
    echo "  ✓ 数据目录已挂载（bind mount / volume）"
else
    echo "  ⚠ 数据目录未挂载为独立卷 — 重建容器将丢失数据！"
fi

echo ""
echo "  💡 更新镜像不丢数据的正确流程："
echo "     docker compose pull            # 拉取新镜像"
echo "     docker compose up -d           # 重新创建容器"
echo "     # 切勿使用 'down -v'，那会删除数据卷"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Start Apache in foreground
exec apache2-foreground
