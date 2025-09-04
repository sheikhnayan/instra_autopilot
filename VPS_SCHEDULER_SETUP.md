# 🚀 VPS Scheduler Setup Guide for Instagram Autopilot

## ⚙️ VPS Scheduler Configuration

This guide will help you set up automatic posting on your VPS server.

## 1. Queue Worker Setup

Your Instagram posting uses Laravel queues, so you need a persistent queue worker.

### **Create Queue Worker Service (Systemd)**

Create a service file for the queue worker:

```bash
sudo nano /etc/systemd/system/instagram-queue.service
```

Add this content:

```ini
[Unit]
Description=Instagram Autopilot Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --timeout=90
WorkingDirectory=/path/to/your/project
Environment=LARAVEL_ENV=production

[Install]
WantedBy=multi-user.target
```

**Replace `/path/to/your/project` with your actual project path!**

### **Enable and Start the Service**

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable instagram-queue.service

# Start the service
sudo systemctl start instagram-queue.service

# Check status
sudo systemctl status instagram-queue.service
```

## 2. Cron Job for Schedule Processing

Set up a cron job to check for scheduled posts every minute:

```bash
# Edit crontab
crontab -e

# Add this line (replace with your actual project path):
* * * * * cd /path/to/your/project && php artisan instagram:process-scheduled-posts >> /dev/null 2>&1
```

## 3. Laravel Scheduler (Alternative Method)

### **Add to app/Console/Kernel.php**

```php
protected function schedule(Schedule $schedule)
{
    // Process scheduled Instagram posts every minute
    $schedule->command('instagram:process-scheduled-posts')->everyMinute();
    
    // Log cleanup
    $schedule->command('queue:prune-failed --hours=48')->daily();
}
```

### **Add Single Cron Entry**

```bash
# Edit crontab
crontab -e

# Add this single line:
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Environment Configuration

### **Production Environment Settings**

Update your `.env` file on the VPS:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Queue Configuration (Use database or Redis)
QUEUE_CONNECTION=database

# Instagram API (your existing settings)
INSTAGRAM_CLIENT_ID=1907520249713676
INSTAGRAM_CLIENT_SECRET=cb4991c5bd1234f100d1ab2381f9395e
INSTAGRAM_REDIRECT_URI=https://your-domain.com/auth/instagram/callback
```

### **Optimize for Production**

```bash
# Install/update dependencies
composer install --optimize-autoloader --no-dev

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

# Run migrations
php artisan migrate --force

# Create queue jobs table if not exists
php artisan queue:table
php artisan migrate
```

## 5. Testing the Scheduler

### **Manual Testing Commands**

```bash
# Test queue worker
php artisan queue:work --once

# Test scheduled posts processing
php artisan instagram:process-scheduled-posts

# Test specific post
php artisan instagram:test-post 1 1

# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed
```

### **Monitor Logs**

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# System service logs
sudo journalctl -u instagram-queue.service -f
```

## 6. Schedule Management via Dashboard

### **Check Schedule Status**

Your dashboard already shows active schedules. Make sure your schedules have:
- ✅ `status = 'active'`
- ✅ Valid `start_date` and `start_time`
- ✅ Proper `interval_minutes`
- ✅ Connected Instagram account
- ✅ Content container with posts

### **Schedule Status Values**

```php
// In your Schedule model or migration
'status' => ['active', 'paused', 'completed', 'failed']
```

## 7. Troubleshooting

### **Common Issues**

1. **Queue worker stopped**
   ```bash
   sudo systemctl restart instagram-queue.service
   ```

2. **Permissions issues**
   ```bash
   sudo chown -R www-data:www-data /path/to/your/project
   sudo chmod -R 755 /path/to/your/project
   ```

3. **Storage permissions**
   ```bash
   sudo chmod -R 775 storage bootstrap/cache
   ```

4. **Failed jobs**
   ```bash
   php artisan queue:failed
   php artisan queue:retry all
   ```

### **Health Check Script**

Create a health check script:

```bash
#!/bin/bash
# health-check.sh

echo "=== Instagram Autopilot Health Check ==="

echo "1. Queue Worker Status:"
sudo systemctl status instagram-queue.service --no-pager

echo "2. Recent Laravel Logs:"
tail -5 /path/to/your/project/storage/logs/laravel.log

echo "3. Queue Status:"
cd /path/to/your/project && php artisan queue:monitor

echo "4. Active Schedules:"
cd /path/to/your/project && php artisan tinker --execute="echo App\Models\Schedule::where('status', 'active')->count() . ' active schedules';"
```

Make it executable and run:
```bash
chmod +x health-check.sh
./health-check.sh
```

## 8. Production Deployment Checklist

- [ ] Queue worker service running
- [ ] Cron job configured
- [ ] SSL certificate installed
- [ ] Environment variables set correctly
- [ ] File permissions correct
- [ ] Storage link created
- [ ] Database migrations run
- [ ] Laravel caches built
- [ ] Instagram accounts connected
- [ ] Test schedule created and working

## Next Steps

1. **Run the setup commands** on your VPS
2. **Create a test schedule** in your dashboard
3. **Monitor the logs** to ensure it's working
4. **Scale up** by adding more schedules

Your Instagram autopilot is now ready for production! 🚀
