FROM php:8.2-apache

# ── 时区设为北京时间 ──
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# ── 国内加速：替换 Debian apt 源为腾讯云镜像 ──
RUN sed -i 's/deb.debian.org/mirrors.cloud.tencent.com/g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || \
    sed -i 's/deb.debian.org/mirrors.cloud.tencent.com/g' /etc/apt/sources.list

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install SQLite3 PHP extension (pdo_sqlite 通常已内置，此处确保)
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Copy application
COPY www/ /var/www/html/

# Create data directory for SQLite (fallback; entrypoint 会在挂载后重新修复权限)
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data && chmod 775 /var/www/data

# Apache config: AllowOverride for .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Entrypoint — 修复挂载卷权限后启动 Apache
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80
