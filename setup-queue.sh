#!/bin/bash

# Laravel Queue Setup Script for Production
echo "Setting up Laravel Queue for Instagram Auto Import..."

# 1. Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "Error: Please run this script from your Laravel project root directory"
    exit 1
fi

# 2. Clear and optimize Laravel
echo "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Set proper permissions
echo "Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 4. Create supervisor config
echo "Creating supervisor configuration..."
cat > /tmp/laravel-worker.conf << EOF
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $(pwd)/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=$(pwd)/storage/logs/worker.log
stopwaitsecs=3600
EOF

echo "Supervisor config created at /tmp/laravel-worker.conf"
echo "To install: sudo mv /tmp/laravel-worker.conf /etc/supervisor/conf.d/"
echo "Then run: sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start laravel-worker"

# 5. Create systemd service alternative
cat > /tmp/laravel-queue.service << EOF
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php $(pwd)/artisan queue:work --sleep=3 --tries=3 --timeout=90
WorkingDirectory=$(pwd)

[Install]
WantedBy=multi-user.target
EOF

echo "Systemd service created at /tmp/laravel-queue.service"
echo "To install: sudo mv /tmp/laravel-queue.service /etc/systemd/system/"
echo "Then run: sudo systemctl enable laravel-queue && sudo systemctl start laravel-queue"

# 6. Show crontab entry
echo ""
echo "Add this to your crontab (crontab -e):"
echo "* * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"

echo ""
echo "Setup complete! Choose one of the process managers above to start the queue worker."
