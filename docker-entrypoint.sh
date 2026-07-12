#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache
set -e

DATA_DIR="/var/www/data"
DB_FILE="$DATA_DIR/app.db"

# ── Permissions ──
if [ -d "$DATA_DIR" ]; then
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
    chmod 775 "$DATA_DIR" 2>/dev/null || true
else
    mkdir -p "$DATA_DIR"
    chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true
    chmod 775 "$DATA_DIR" 2>/dev/null || true
fi

# ── Header ──
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔗 url-destroyer — 链接销毁系统"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── Database check ──
if [ -f "$DB_FILE" ]; then
    DB_SIZE=$(du -h "$DB_FILE" 2>/dev/null | cut -f1)
    echo "  ✓ 数据库: ${DB_SIZE}"
    COUNT=$(php -r '
        try {
            $pdo = new PDO("sqlite:/var/www/data/app.db", null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            echo $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn();
        } catch (Exception $e) { echo "?"; }
    ' 2>/dev/null)
    echo "  ✓ 链接记录: ${COUNT} 条"
else
    # Check: is the data dir truly empty, or just missing app.db?
    FILE_COUNT=$(ls -A "$DATA_DIR" 2>/dev/null | wc -l)
    if [ "$FILE_COUNT" -gt 0 ]; then
        echo "  ⚠ 数据目录中有文件但不是 app.db："
        ls -lhA "$DATA_DIR" 2>/dev/null | tail -n +2 | while IFS= read -r l; do echo "     $l"; done
    else
        echo ""
        echo "  ╔══════════════════════════════════════════════╗"
        echo "  ║  ⚠️  数据目录为空 — 数据库尚未创建            ║"
        echo "  ║                                              ║"
        echo "  ║  首次启动正常，更新镜像后出现则说明：           ║"
        echo "  ║  旧容器中的数据未持久化到宿主机。               ║"
        echo "  ║                                              ║"
        echo "  ║  解决方法：                                    ║"
        echo "  ║  1. 确保 docker-compose.yml 在同目录运行        ║"
        echo "  ║  2. 宿主机 ./data 目录应包含旧 app.db          ║"
        echo "  ║  3. 或用 .env 设 DATA_DIR=/固定/路径/data      ║"
        echo "  ╚══════════════════════════════════════════════╝"
        echo ""
    fi
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exec apache2-foreground
