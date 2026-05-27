#!/bin/bash
# Docker entrypoint — fix permissions on mounted volume, then start Apache

# Ensure the data directory is writable by www-data
if [ -d /var/www/data ]; then
    chown -R www-data:www-data /var/www/data
    chmod 775 /var/www/data
fi

# Start Apache in foreground
exec apache2-foreground
