#!/bin/bash

# Instagram Autopilot VPS Setup Script
# Run this script on your VPS after uploading the project

echo "🚀 Setting up Instagram Autopilot on VPS..."

# Get project directory
read -p "Enter full path to your project directory: " PROJECT_PATH

if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Directory not found: $PROJECT_PATH"
    exit 1
fi

cd "$PROJECT_PATH"

echo "📁 Working in: $(pwd)"

# 1. Install dependencies
echo "📦 Installing dependencies..."
composer install --optimize-autoloader --no-dev

# 2. Set permissions
echo "🔐 Setting permissions..."
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache

# 3. Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link

# 4. Run migrations
echo "🗃️  Running migrations..."
php artisan migrate --force

# 5. Create queue table
echo "📋 Setting up queue table..."
php artisan queue:table
php artisan migrate --force

# 6. Cache configuration
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Create systemd service
echo "⚙️  Creating queue worker service..."
sudo tee /etc/systemd/system/instagram-queue.service > /dev/null <<EOF
[Unit]
Description=Instagram Autopilot Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php $PROJECT_PATH/artisan queue:work --sleep=3 --tries=3 --timeout=90
WorkingDirectory=$PROJECT_PATH
Environment=LARAVEL_ENV=production

[Install]
WantedBy=multi-user.target
EOF

# 8. Enable and start service
echo "🔄 Starting queue worker service..."
sudo systemctl daemon-reload
sudo systemctl enable instagram-queue.service
sudo systemctl start instagram-queue.service

# 9. Add cron job
echo "⏰ Setting up cron job..."
(crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_PATH && php artisan instagram:process-scheduled-posts >> /dev/null 2>&1") | crontab -

# 10. Test setup
echo "🧪 Testing setup..."
echo "Queue worker status:"
sudo systemctl status instagram-queue.service --no-pager

echo "Testing scheduled posts command:"
php artisan instagram:process-scheduled-posts

echo ""
echo "✅ Setup complete!"
echo ""
echo "📊 Next steps:"
echo "1. Check your domain: https://your-domain.com"
echo "2. Connect Instagram accounts via dashboard"
echo "3. Create content containers"
echo "4. Set up schedules"
echo "5. Monitor logs: tail -f storage/logs/laravel.log"
echo ""
echo "🔍 Health check commands:"
echo "  sudo systemctl status instagram-queue.service"
echo "  php artisan queue:monitor"
echo "  php artisan queue:failed"
echo ""
echo "Happy posting! 🎉"
