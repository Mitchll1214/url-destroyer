#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache
set -e

# ── Detect database driver ──
DB_DRIVER="${DB_DRIVER:-sqlite}"

if [ "$DB_DRIVER" = "mysql" ]; then
    # ── MySQL mode ──
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  🔗 url-destroyer — 链接销毁系统"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  🗄️  数据库引擎: MySQL"
    echo "  📁  主机:       ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}"
    echo "  🗃️   数据库:    ${DB_DATABASE:-url_destroyer}"

    # Test MySQL connection
    DB_OK=false
    COUNT=$(php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=${DB_HOST:-127.0.0.1};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-url_destroyer};charset=utf8mb4',
                '${DB_USERNAME:-root}',
                '${DB_PASSWORD}',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            \$count = \$pdo->query('SELECT COUNT(*) FROM ud_links')->fetchColumn();
            echo \$count === false ? '0' : \$count;
        } catch (Exception \$e) {
            echo 'CONNECT_ERROR';
        }
    " 2>/dev/null)

    if [ "$COUNT" = "CONNECT_ERROR" ]; then
        echo "  ⚠️  数据库连接失败！"
        echo ""
        echo "  ╔══════════════════════════════════════════════╗"
        echo "  ║  ⚠️  无法连接到 MySQL 服务器                   ║"
        echo "  ║                                              ║"
        echo "  ║  请检查环境变量设置：                          ║"
        echo "  ║  DB_DRIVER=mysql                              ║"
        echo "  ║  DB_HOST=<MySQL主机地址>                       ║"
        echo "  ║  DB_PORT=3306                                 ║"
        echo "  ║  DB_DATABASE=url_destroyer                    ║"
        echo "  ║  DB_USERNAME=root                             ║"
        echo "  ║  DB_PASSWORD=<密码>                            ║"
        echo "  ║                                              ║"
        echo "  ║  首次启动时将自动创建表结构。                    ║"
        echo "  ╚══════════════════════════════════════════════╝"
    else
        echo "  ✓  连接成功 — 链接记录: ${COUNT} 条"
        DB_OK=true
    fi

elif [ "$DB_DRIVER" = "sqlite" ]; then
    # ── SQLite mode (original behavior) ──
    # Resolve database path
    if [ -n "$DB_PATH" ]; then
        DB_FILE="$DB_PATH"
        DATA_DIR="$(dirname "$DB_FILE")"
    elif [ -n "$DATA_DIR" ]; then
        DB_FILE="$DATA_DIR/app.db"
    else
        DATA_DIR="/var/www/data"
        DB_FILE="$DATA_DIR/app.db"
    fi

    # ── Detect mounted volume ──
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
    echo "  🗄️  数据库引擎: SQLite"
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
                echo \$pdo->query('SELECT COUNT(*) FROM ud_links')->fetchColumn();
            } catch (Exception \$e) { echo '?'; }
        " 2>/dev/null)
        echo "  ✓ 链接记录: ${COUNT} 条"
    else
        FILE_COUNT=$(ls -A "$DATA_DIR" 2>/dev/null | wc -l)
        if [ "$FILE_COUNT" -gt 0 ]; then
            echo "  ⚠ 数据目录中有文件但不是 app.db："
            ls -lhA "$DATA_DIR" 2>/dev/null | tail -n +2 | while IFS= read -r l; do echo "     $l"; done
        else
            echo ""
            if $IS_MOUNTED; then
                echo "  ╔══════════════════════════════════════════════╗"
                echo "  ║  ℹ️  首次启动 — 将在后台创建新数据库          ║"
                echo "  ║                                              ║"
                echo "  ║  数据卷已挂载，数据将持久化保存。               ║"
                echo "  ║  首次访问后台页面时将自动创建数据库。           ║"
                echo "  ╚══════════════════════════════════════════════╝"
            else
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
                echo "  ║      -v \"\$(pwd)/data:/var/www/data\" \\         ║"
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
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exec apache2-foreground
