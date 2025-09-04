# Instagram Autopilot - VPS Deployment Guide

## 🚀 Quick Setup on VPS

### 1. Upload Files to VPS
### If Instagram Posts Not Processing
```bash
# Check if jobs are being created
cd /var/www/instra_autopilot
php artisan queue:monitor

# Process schedules manually
php artisan schedule:run

# Check Instagram API service
php artisan tinker
>>> app(App\Services\InstagramApiService::class)->getAllInstagramAccounts();

# Check failed jobs
php artisan queue:failed

# View detailed job logs
sudo journalctl -u instagram-queue -f --since "1 hour ago"

# Check Laravel application logs
tail -f /var/www/instra_autopilot/storage/logs/laravel.log
```load your project to VPS
scp -r instagram_autopilot/ user@your-vps-ip:/var/www/instra_autopilot
```

### 2. Set Up Queue Service
```bash
# On your VPS, run:
cd /var/www/instra_autopilot/deployment
sudo chmod +x setup-queue-service.sh
sudo ./setup-queue-service.sh
```

### 3. Set Up Cron Jobs
```bash
# On your VPS, run:
sudo chmod +x setup-cron.sh
sudo ./setup-cron.sh
```

### 4. Configure Environment
```bash
# Copy environment file
cp .env.example .env

# Edit your .env file with production settings
nano .env
```

### 5. Install Dependencies & Setup
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Link storage
php artisan storage:link

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 📊 Service Management Commands

### Queue Service
```bash
# Check service status
sudo systemctl status instagram-queue

# Start service
sudo systemctl start instagram-queue

# Stop service
sudo systemctl stop instagram-queue

# Restart service
sudo systemctl restart instagram-queue

# View logs
sudo journalctl -u instagram-queue -f
```

### Cron Jobs
```bash
# View current cron jobs
sudo crontab -u www-data -l

# Edit cron jobs
sudo crontab -u www-data -e

# View cron logs
sudo journalctl -u cron -f
```

## 🔧 **IMPORTANT: Upload Missing Files to VPS**

After creating the service, you need to upload the missing Kernel.php file:

```bash
# On your VPS, create the missing Console Kernel
cd /var/www/instra_autopilot

# Create the Console Kernel file
cat > app/Console/Kernel.php << 'EOF'
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('instagram:process-scheduled-posts')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        $schedule->command('queue:prune-failed --hours=48')
                 ->daily();
                 
        $schedule->command('log:clear')
                 ->weekly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
EOF

# Create log clear command
cat > app/Console/Commands/LogClearCommand.php << 'EOF'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogClearCommand extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Clear Laravel log files';

    public function handle()
    {
        $logPath = storage_path('logs');
        
        if (!File::exists($logPath)) {
            $this->info('No log directory found.');
            return;
        }
        
        $files = File::files($logPath);
        $clearedCount = 0;
        
        foreach ($files as $file) {
            if ($file->getExtension() === 'log') {
                File::put($file->getPathname(), '');
                $clearedCount++;
            }
        }
        
        $this->info("Cleared {$clearedCount} log file(s).");
    }
}
EOF

# Clear config cache and restart service
php artisan config:clear
php artisan config:cache
sudo systemctl restart instagram-queue
```

## 🔧 Troubleshooting

### If Queue Service Won't Start
```bash
# Check PHP path
which php

# Check project permissions
sudo chown -R www-data:www-data /var/www/instra_autopilot
sudo chmod -R 755 /var/www/instra_autopilot
sudo chmod -R 775 /var/www/instra_autopilot/storage
sudo chmod -R 775 /var/www/instra_autopilot/bootstrap/cache

# Test queue manually
cd /var/www/instra_autopilot
sudo -u www-data php artisan queue:work --once
```

### If Instagram Posts Not Processing
```bash
# Check if jobs are being created
cd /var/www/instagram_autopilot
php artisan queue:monitor

# Process schedules manually
php artisan schedule:run

# Check Instagram API service
php artisan tinker
>>> app(App\Services\InstagramApiService::class)->getAllInstagramAccounts();
```

### Common Issues
1. **Permission denied**: Run `sudo chown -R www-data:www-data /var/www/instra_autopilot`
2. **PHP not found**: Update service file with correct PHP path
3. **Database connection**: Check .env database settings
4. **Instagram API errors**: Verify Facebook App credentials in .env
5. **Jobs failing**: Check Laravel logs and failed job queue
6. **Invalid tokens**: Refresh Instagram account tokens via app interface
7. **Missing Kernel.php**: Follow the file upload section above
8. **Console command errors**: Ensure all artisan commands work: `php artisan list`

## 📈 Monitoring

### Check System Status
```bash
# Service status
sudo systemctl status instagram-queue

# Resource usage
top -p $(pgrep -f "queue:work")

# Disk space
df -h

# Memory usage
free -h
```

### Log Files
```bash
# Laravel logs
tail -f /var/www/instra_autopilot/storage/logs/laravel.log

# Queue service logs
sudo journalctl -u instagram-queue -f

# System logs
sudo journalctl -f
```

## 🔄 Updates

### Deploy New Version
```bash
# Backup current version
sudo cp -r /var/www/instagram_autopilot /var/www/instagram_autopilot_backup

# Upload new files
# (your upload process)

# Update dependencies
cd /var/www/instra_autopilot
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart instagram-queue
```

## 🛡️ Security

### Firewall Setup
```bash
# Allow SSH, HTTP, HTTPS
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
```

### SSL Certificate (Let's Encrypt)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal
sudo systemctl enable certbot.timer
```

### File Permissions
```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/instra_autopilot
sudo chmod -R 755 /var/www/instra_autopilot
sudo chmod -R 775 /var/www/instra_autopilot/storage
sudo chmod -R 775 /var/www/instra_autopilot/bootstrap/cache
sudo chmod 600 /var/www/instra_autopilot/.env
```

## 📱 Instagram Autopilot Features

✅ **Multi-Account Management**: Connect multiple Instagram accounts via Facebook Graph API
✅ **Content Containers**: Organize posts into containers for different campaigns
✅ **Smart Scheduling**: Set custom intervals (15 minutes to 24 hours)
✅ **Queue Processing**: Reliable background job processing
✅ **Auto-Restart**: Service automatically restarts if it fails
✅ **Progress Tracking**: Monitor which posts have been published
✅ **Cycle Repeat**: Automatically restart posting cycle when container is complete

## 🆘 Support

If you encounter issues:
1. Check service logs: `sudo journalctl -u instagram-queue -f`
2. Verify queue is processing: `php artisan queue:monitor`
3. Test Instagram API: Access your app's test route
4. Check file permissions and ownership
5. Verify .env configuration
