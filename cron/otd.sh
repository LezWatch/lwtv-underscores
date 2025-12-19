#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"
export HOME="/home/wp_bg3hrq"

UUID="set-char-and-show-otd"
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"
LOG_FILE="/home/wp_bg3hrq/cron/otd-debug.log"

cd /home/wp_bg3hrq/lezwatchtv.com || {
    echo "$(date): Failed to cd" >> "$LOG_FILE"
    $PING_SCRIPT "$UUID" "false"
    exit 1
}

echo "$(date): Starting OTD generation" >> "$LOG_FILE"
echo "$(date): PHP Version: $(/usr/bin/php -v | head -1)" >> "$LOG_FILE"
echo "$(date): WP-CLI Version: $(/usr/bin/wp --version)" >> "$LOG_FILE"
echo "$(date): User: $(whoami)" >> "$LOG_FILE"
echo "$(date): HOME: $HOME" >> "$LOG_FILE"
echo "$(date): PWD: $(pwd)" >> "$LOG_FILE"

# Run WP-CLI with error display
export WP_CLI_PHP_ARGS="-d display_errors=1 -d log_errors=1 -d error_reporting=E_ALL"
/usr/bin/wp lwtv generate otd --path=/home/wp_bg3hrq/lezwatchtv.com/ --debug 2>&1 >> "$LOG_FILE"
EXIT_CODE=$?
echo "$(date): Finished with exit code $EXIT_CODE" >> "$LOG_FILE"

if [ $EXIT_CODE -eq 0 ]; then
    SUCCEEDED="true"
else
    SUCCEEDED="false"
fi

$PING_SCRIPT "$UUID" "$SUCCEEDED"
