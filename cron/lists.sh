#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"
export HOME="/home/wp_bg3hrq"

UUID="generate-lists"
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"
LOG_FILE="/home/wp_bg3hrq/cron/lists-debug.log"

cd /home/wp_bg3hrq/lezwatchtv.com || {
    echo "$(date): Failed to cd" >> "$LOG_FILE"
    /bin/bash "$PING_SCRIPT" "$UUID" "false"
    exit 1
}

{
    echo "--- Start: $(date) ---"
    echo "User: $(whoami)"

    # We call 'wp' directly. We use --path to ensure it finds the right site.
    # The '2>&1' at the end of the block captures all output.

    /usr/bin/wp lwtv generate lists --path=/home/wp_bg3hrq/lezwatchtv.com/

    EXIT_CODE=$?
    echo "Finished with exit code $EXIT_CODE"

    if [ $EXIT_CODE -eq 0 ]; then
        SUCCEEDED="true"
    else
        SUCCEEDED="false"
    fi

    /bin/bash "$PING_SCRIPT" "$UUID" "$SUCCEEDED"
    echo "--- End ---"
} >> "$LOG_FILE" 2>&1
