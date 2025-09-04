#!/bin/bash

# Instagram Autopilot - Queue Service Setup Script
# Run this script on your VPS to set up the systemd service

set -e

echo "🚀 Setting up Instagram Autopilot Queue Service..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Please run this script as root (use sudo)"
    exit 1
fi

# Variables
PROJECT_PATH="/var/www/instagram_autopilot"
SERVICE_NAME="instagram-queue"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"

# Check if project directory exists
if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Project directory $PROJECT_PATH not found!"
    echo "Please ensure your Laravel project is deployed to $PROJECT_PATH"
    exit 1
fi

# Create the systemd service file
echo "📝 Creating systemd service file..."
cat > $SERVICE_FILE << 'EOF'
[Unit]
Description=Instagram Autopilot Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/instagram_autopilot
ExecStart=/usr/bin/php /var/www/instagram_autopilot/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10

# Set environment variables
Environment=APP_ENV=production
Environment=APP_DEBUG=false

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=instagram-queue

# Security settings
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/www/instagram_autopilot/storage
ReadWritePaths=/var/www/instagram_autopilot/bootstrap/cache

[Install]
WantedBy=multi-user.target
EOF

# Set proper permissions
echo "🔐 Setting permissions..."
chmod 644 $SERVICE_FILE
chown www-data:www-data $PROJECT_PATH -R
chmod -R 755 $PROJECT_PATH
chmod -R 775 $PROJECT_PATH/storage
chmod -R 775 $PROJECT_PATH/bootstrap/cache

# Reload systemd
echo "🔄 Reloading systemd..."
systemctl daemon-reload

# Enable the service
echo "✅ Enabling service..."
systemctl enable $SERVICE_NAME

# Start the service
echo "🚀 Starting service..."
systemctl start $SERVICE_NAME

# Check status
echo "📊 Service status:"
systemctl status $SERVICE_NAME --no-pager

echo ""
echo "✅ Instagram Autopilot Queue Service setup complete!"
echo ""
echo "📋 Service Management Commands:"
echo "   Check status:    sudo systemctl status $SERVICE_NAME"
echo "   Start service:   sudo systemctl start $SERVICE_NAME"
echo "   Stop service:    sudo systemctl stop $SERVICE_NAME"
echo "   Restart service: sudo systemctl restart $SERVICE_NAME"
echo "   View logs:       sudo journalctl -u $SERVICE_NAME -f"
echo ""
echo "🔍 The service will automatically:"
echo "   - Start on boot"
echo "   - Restart if it crashes"
echo "   - Process Instagram posting jobs"
echo "   - Log to system journal"
echo ""
