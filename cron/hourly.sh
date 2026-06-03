#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"
export HOME="/home/wp_bg3hrq"

UUID="due-now-hourly"
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"
LOG_FILE="/home/wp_bg3hrq/cron/hourly-debug.log"

cd /home/wp_bg3hrq/lezwatchtv.com || {
    echo "$(date): Failed to cd" >> "$LOG_FILE"
    /bin/bash "$PING_SCRIPT" "$UUID" "false"
    exit 1
}

TMPFILE=$(mktemp)

{
    echo "--- Start: $(date) ---"
    echo "User: $(whoami)"
    /usr/bin/wp lwtv generate cron hourly --path=/home/wp_bg3hrq/lezwatchtv.com/
} > "$TMPFILE" 2>&1
EXIT_CODE=$?

echo "Finished with exit code $EXIT_CODE" >> "$TMPFILE"

if [ "$EXIT_CODE" -ne 0 ]; then
    cat "$TMPFILE" >> "$LOG_FILE"
    SUCCEEDED="false"
else
    SUCCEEDED="true"
fi

/bin/bash "$PING_SCRIPT" "$UUID" "$SUCCEEDED"
rm -f "$TMPFILE"
