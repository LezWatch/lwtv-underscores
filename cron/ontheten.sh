#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"
export HOME="/home/wp_bg3hrq"

UUID="due-now-10-min"
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"
LOG_FILE="/home/wp_bg3hrq/cron/ontheten-debug.log"

cd /home/wp_bg3hrq/lezwatchtv.com || {
    echo "$(date): Failed to cd" >> "$LOG_FILE"
    /bin/bash "$PING_SCRIPT" "$UUID" "false"
    exit 1
}

TMPFILE=$(mktemp)

{
    echo "--- Start: $(date) ---"
    echo "User: $(whoami)"
    /usr/bin/wp cron event run --due-now --path=/home/wp_bg3hrq/lezwatchtv.com/ --verbose
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
