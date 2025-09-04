#!/bin/bash

# Instagram Autopilot - Cron Job Setup Script
# Sets up the Laravel scheduler cron job

set -e

echo "⏰ Setting up Instagram Autopilot Cron Jobs..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Please run this script as root (use sudo)"
    exit 1
fi

PROJECT_PATH="/var/www/instra_autopilot"

# Check if project directory exists
if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Project directory $PROJECT_PATH not found!"
    exit 1
fi

# Create cron job for www-data user
echo "📝 Setting up cron job for Laravel scheduler..."

# Create the cron job
(crontab -u www-data -l 2>/dev/null; echo "* * * * * cd $PROJECT_PATH && /usr/bin/php artisan schedule:run >> /dev/null 2>&1") | crontab -u www-data -

echo "✅ Cron job added successfully!"

# Show current cron jobs for www-data
echo ""
echo "📋 Current cron jobs for www-data user:"
crontab -u www-data -l

echo ""
echo "✅ Cron setup complete!"
echo ""
echo "🔍 The cron job will run every minute and execute:"
echo "   - Schedule processing (Instagram posts)"
echo "   - Queue maintenance"
echo "   - Cleanup tasks"
echo ""
echo "📊 Monitor cron execution:"
echo "   sudo journalctl -u cron -f"
echo ""
