#!/bin/bash
##
# TaskFlow AI Workflow Scheduler Setup
# Sets up cron jobs for proactive workflow notifications
##

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PHP_SCRIPT="$PROJECT_DIR/src/features/workflow-scheduler.php"
LOG_FILE="/var/log/taskflow-scheduler.log"

echo "Setting up TaskFlow AI Workflow Scheduler..."

# Check if PHP script exists
if [ ! -f "$PHP_SCRIPT" ]; then
    echo "Error: PHP scheduler script not found at $PHP_SCRIPT"
    exit 1
fi

# Create log file if it doesn't exist
if [ ! -f "$LOG_FILE" ]; then
    echo "Creating log file at $LOG_FILE"
    sudo touch "$LOG_FILE"
    sudo chmod 666 "$LOG_FILE"
fi

# Check if running as root for system-wide cron
if [ "$EUID" -eq 0 ]; then
    echo "Setting up system-wide cron jobs..."
    CRON_USER="www-data"
else
    echo "Setting up user cron jobs..."
    CRON_USER=$(whoami)
fi

# Backup existing crontab
echo "Backing up existing crontab..."
if crontab -l > /dev/null 2>&1; then
    crontab -l > "/tmp/crontab.backup.$(date +%Y%m%d_%H%M%S)"
    echo "Crontab backed up to /tmp/"
fi

# Check for existing TaskFlow scheduler entries
if crontab -l 2>/dev/null | grep -q "taskflow.*scheduler"; then
    echo "Found existing TaskFlow scheduler cron jobs"
    echo "Removing old entries..."
    crontab -l 2>/dev/null | grep -v "taskflow.*scheduler" | crontab -
fi

# Create new cron entries
echo "Adding new cron jobs..."

# Create temporary cron file
TEMP_CRON="/tmp/taskflow_cron_$$"
crontab -l 2>/dev/null > "$TEMP_CRON" || echo "" > "$TEMP_CRON"

# Add TaskFlow scheduler jobs
cat >> "$TEMP_CRON" << EOF

# TaskFlow AI Workflow Scheduler
# Check for due workflows every minute during active hours (7 AM - 11 PM)
* 7-23 * * * /usr/bin/php "$PHP_SCRIPT" run >> "$LOG_FILE" 2>&1

# Daily cleanup at 2 AM (keep 90 days of logs)
0 2 * * * /usr/bin/php "$PHP_SCRIPT" cleanup 90 >> "$LOG_FILE" 2>&1

# Weekly status check on Sundays at 3 AM
0 3 * * 0 /usr/bin/php "$PHP_SCRIPT" status >> "$LOG_FILE" 2>&1

EOF

# Install the new crontab
crontab "$TEMP_CRON"
rm "$TEMP_CRON"

echo "Cron jobs installed successfully!"
echo ""
echo "Installed jobs:"
crontab -l | grep -A 10 "TaskFlow AI Workflow Scheduler"
echo ""

# Test the scheduler
echo "Testing scheduler..."
if /usr/bin/php "$PHP_SCRIPT" status; then
    echo "✓ Scheduler test passed"
else
    echo "✗ Scheduler test failed"
    echo "Check your database configuration and dependencies"
    exit 1
fi

# Create systemd timer as alternative (if systemd is available)
if command -v systemctl >/dev/null 2>&1; then
    echo ""
    echo "Creating systemd timer as alternative..."
    
    # Create service file
    sudo tee /etc/systemd/system/taskflow-scheduler.service > /dev/null << EOF
[Unit]
Description=TaskFlow AI Workflow Scheduler
After=network.target

[Service]
Type=oneshot
User=$CRON_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/php $PHP_SCRIPT run
StandardOutput=journal
StandardError=journal

EOF

    # Create timer file
    sudo tee /etc/systemd/system/taskflow-scheduler.timer > /dev/null << EOF
[Unit]
Description=Run TaskFlow AI Scheduler every minute during active hours
Requires=taskflow-scheduler.service

[Timer]
# Run every minute from 7 AM to 11 PM
OnCalendar=*-*-* 07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23:*:00
Persistent=true

[Install]
WantedBy=timers.target

EOF

    # Create cleanup service and timer
    sudo tee /etc/systemd/system/taskflow-cleanup.service > /dev/null << EOF
[Unit]
Description=TaskFlow AI Log Cleanup
After=network.target

[Service]
Type=oneshot
User=$CRON_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/php $PHP_SCRIPT cleanup 90
StandardOutput=journal
StandardError=journal

EOF

    sudo tee /etc/systemd/system/taskflow-cleanup.timer > /dev/null << EOF
[Unit]
Description=Daily TaskFlow AI cleanup at 2 AM
Requires=taskflow-cleanup.service

[Timer]
OnCalendar=*-*-* 02:00:00
Persistent=true

[Install]
WantedBy=timers.target

EOF

    # Reload systemd and enable timers
    sudo systemctl daemon-reload
    sudo systemctl enable taskflow-scheduler.timer
    sudo systemctl enable taskflow-cleanup.timer
    
    echo "✓ Systemd timers created and enabled"
    echo ""
    echo "To start timers now:"
    echo "  sudo systemctl start taskflow-scheduler.timer"
    echo "  sudo systemctl start taskflow-cleanup.timer"
    echo ""
    echo "To check timer status:"
    echo "  systemctl status taskflow-scheduler.timer"
    echo "  systemctl list-timers taskflow-*"
fi

echo ""
echo "Setup complete! The scheduler will:"
echo "- Check for due workflows every minute from 7 AM to 11 PM"
echo "- Clean up old logs daily at 2 AM"
echo "- Log all activity to $LOG_FILE"
echo ""
echo "To monitor:"
echo "  tail -f $LOG_FILE"
echo ""
echo "To test manually:"
echo "  php $PHP_SCRIPT run"
echo "  php $PHP_SCRIPT status"
echo "  php $PHP_SCRIPT trigger morning"
echo ""
echo "To disable:"
echo "  crontab -e  # Remove TaskFlow lines"
echo "  sudo systemctl disable taskflow-scheduler.timer  # If using systemd"
