#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache
set -e

# ── Resolve database path ──
# 优先级：DB_PATH 环境变量 > DATA_DIR 环境变量 > 默认路径
# 必须与 www/config.php 中的 DB_PATH 逻辑保持一致
if [ -n "$DB_PATH" ]; then
    DB_FILE="$DB_PATH"
    DATA_DIR="$(dirname "$DB_FILE")"
elif [ -n "$DATA_DIR" ]; then
    DB_FILE="$DATA_DIR/app.db"
else
    DATA_DIR="/var/www/data"
    DB_FILE="$DATA_DIR/app.db"
fi

# ── Detect whether the data directory is a mounted volume ──
# 通过 /proc/mounts 检查数据目录是否挂载了宿主机目录或命名卷
# 如果没有挂载，容器删除后数据会丢失
IS_MOUNTED=false
if [ -f /proc/mounts ] && grep -qE " ${DATA_DIR}(/| |$)" /proc/mounts 2>/dev/null; then
    IS_MOUNTED=true
elif mountpoint -q "$DATA_DIR" 2>/dev/null; then
    IS_MOUNTED=true
fi

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
echo "  📁 数据目录: ${DATA_DIR}"
echo "  🗄️  数据库:  ${DB_FILE}"

if $IS_MOUNTED; then
    echo "  🔒 数据卷:   已挂载 ✓"
else
    echo "  ⚠️  数据卷:   未挂载 — 数据不会持久化！"
fi

# ── Database check ──
if [ -f "$DB_FILE" ]; then
    DB_SIZE=$(du -h "$DB_FILE" 2>/dev/null | cut -f1)
    echo "  ✓ 数据库: ${DB_SIZE}"
    COUNT=$(php -r "
        try {
            \$pdo = new PDO('sqlite:${DB_FILE}', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            echo \$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
        } catch (Exception \$e) { echo '?'; }
    " 2>/dev/null)
    echo "  ✓ 链接记录: ${COUNT} 条"
else
    # Check: is the data dir truly empty, or just missing app.db?
    FILE_COUNT=$(ls -A "$DATA_DIR" 2>/dev/null | wc -l)
    if [ "$FILE_COUNT" -gt 0 ]; then
        echo "  ⚠ 数据目录中有文件但不是 app.db："
        ls -lhA "$DATA_DIR" 2>/dev/null | tail -n +2 | while IFS= read -r l; do echo "     $l"; done
    else
        echo ""
        if $IS_MOUNTED; then
            # 卷已挂载但数据库不存在 — 首次启动或数据卷为空
            echo "  ╔══════════════════════════════════════════════╗"
            echo "  ║  ℹ️  首次启动 — 将在后台创建新数据库          ║"
            echo "  ║                                              ║"
            echo "  ║  数据卷已挂载，数据将持久化保存。               ║"
            echo "  ║  首次访问后台页面时将自动创建数据库。           ║"
            echo "  ╚══════════════════════════════════════════════╝"
        else
            # 无数据卷挂载 — 容器删除后数据丢失
            echo "  ╔══════════════════════════════════════════════╗"
            echo "  ║  🚨  未检测到数据卷挂载！                     ║"
            echo "  ║                                              ║"
            echo "  ║  ⚠️  当前数据库仅存在于容器临时存储中，         ║"
            echo "  ║  容器删除后所有数据将丢失！                    ║"
            echo "  ║                                              ║"
            echo "  ║  🔧 解决方法：                                ║"
            echo "  ║                                              ║"
            echo "  ║  方案 A — 使用 docker-compose（推荐）：        ║"
            echo "  ║    docker-compose up -d                       ║"
            echo "  ║                                              ║"
            echo "  ║  方案 B — docker run 加 -v 挂载：              ║"
            echo "  ║    mkdir -p ./data                            ║"
            echo "  ║    docker run -d \\                            ║"
            echo "  ║      -p 1000:80 \\                             ║"
            echo "  ║      -v \"$(pwd)/data:/var/www/data\" \\         ║"
            echo "  ║      -e DB_PATH=/var/www/data/app.db \\        ║"
            echo "  ║      mitchll1214/url-destroyer:latest         ║"
            echo "  ║                                              ║"
            echo "  ║  方案 C — 使用 Docker 命名卷（自动管理）：      ║"
            echo "  ║    docker volume create url-destroyer-data     ║"
            echo "  ║    docker run -d \\                            ║"
            echo "  ║      -p 1000:80 \\                             ║"
            echo "  ║      -v url-destroyer-data:/var/www/data \\    ║"
            echo "  ║      mitchll1214/url-destroyer:latest         ║"
            echo "  ╚══════════════════════════════════════════════╝"
        fi
        echo ""
    fi
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exec apache2-foreground
