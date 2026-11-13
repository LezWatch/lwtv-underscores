#!/bin/bash
: "${HOME:?HOME is not set}"
export PATH="/usr/local/bin:/usr/bin:/bin"

UUID="generate-lists"
PING_SCRIPT="$HOME/cron/ping.sh"
LOG_FILE="$HOME/cron/lists-debug.log"

cd $HOME/lezwatchtv.com || {
    echo "$(date): Failed to cd" >> "$LOG_FILE"
    /bin/bash "$PING_SCRIPT" "$UUID" "false"
    exit 1
}

TMPFILE=$(mktemp)

{
    echo "--- Start: $(date) ---"
    echo "User: $(whoami)"
    /usr/bin/wp lwtv generate lists --path=$HOME/lezwatchtv.com/
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
