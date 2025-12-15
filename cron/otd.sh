#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

UUID="set-char-and-show-otd"
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"
LOG_FILE="/home/wp_bg3hrq/cron/otd-debug.log"

cd /home/wp_bg3hrq/lezwatchtv.com || {
    echo "$(date): Failed to cd" >> "$LOG_FILE"
    $PING_SCRIPT "$UUID" "false"
    exit 1
}

echo "$(date): Starting OTD generation" >> "$LOG_FILE"
/usr/bin/wp lwtv generate otd --path=/home/wp_bg3hrq/lezwatchtv.com/ --debug 2>&1 >> "$LOG_FILE"
EXIT_CODE=$?
echo "$(date): Finished with exit code $EXIT_CODE" >> "$LOG_FILE"

if [ $EXIT_CODE -eq 0 ]; then
    SUCCEEDED="true"
else
    SUCCEEDED="false"
fi

$PING_SCRIPT "$UUID" "$SUCCEEDED"
