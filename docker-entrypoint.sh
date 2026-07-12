#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔗 url-destroyer — 链接销毁系统"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Ensure the data directory is writable by www-data
if [ -d /var/www/data ]; then
    chown -R www-data:www-data /var/www/data
    chmod 775 /var/www/data
    echo "  ✓ 数据目录权限已修复: /var/www/data"
fi

# Check database status
DB_FILE="/var/www/data/app.db"
if [ -f "$DB_FILE" ]; then
    DB_SIZE=$(du -h "$DB_FILE" | cut -f1)
    echo "  ✓ 数据库已存在: ${DB_SIZE}"
else
    echo "  ⚠ 数据库不存在，首次访问时将自动创建"
    echo "    路径: $DB_FILE"
fi

echo "  ✓ 数据卷: url-destroyer-data (named volume)"
echo "    即使重建容器或更换目录，数据也不会丢失"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Start Apache in foreground
exec apache2-foreground
