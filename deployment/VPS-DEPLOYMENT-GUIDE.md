# Instagram Autopilot - VPS Deployment Guide

## 🚀 Quick Setup on VPS

### 1. Upload Files to VPS
```bash
# Upload your project to VPS
scp -r instagram_autopilot/ user@your-vps-ip:/var/www/
```

### 2. Set Up Queue Service
```bash
# On your VPS, run:
cd /var/www/instagram_autopilot/deployment
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

## 🔧 Troubleshooting

### If Queue Service Won't Start
```bash
# Check PHP path
which php

# Check project permissions
sudo chown -R www-data:www-data /var/www/instagram_autopilot
sudo chmod -R 755 /var/www/instagram_autopilot
sudo chmod -R 775 /var/www/instagram_autopilot/storage
sudo chmod -R 775 /var/www/instagram_autopilot/bootstrap/cache

# Test queue manually
cd /var/www/instagram_autopilot
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
1. **Permission denied**: Run `sudo chown -R www-data:www-data /var/www/instagram_autopilot`
2. **PHP not found**: Update service file with correct PHP path
3. **Database connection**: Check .env database settings
4. **Instagram API errors**: Verify Facebook App credentials in .env

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
tail -f /var/www/instagram_autopilot/storage/logs/laravel.log

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
cd /var/www/instagram_autopilot
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
sudo chown -R www-data:www-data /var/www/instagram_autopilot
sudo chmod -R 755 /var/www/instagram_autopilot
sudo chmod -R 775 /var/www/instagram_autopilot/storage
sudo chmod -R 775 /var/www/instagram_autopilot/bootstrap/cache
sudo chmod 600 /var/www/instagram_autopilot/.env
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
