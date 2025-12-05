#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

# Define the UUID for this specific task
UUID="set-char-and-show-otd"

# Define the path to the ping.sh script
# Assuming it's in the same directory.
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"

cd /home/wp_bg3hrq/lezwatchtv.com || {
        # Call ping.sh with UUID, status, and message
        $PING_SCRIPT "$UUID" "false"
        exit 1
}

/usr/bin/wp lwtv generate otd --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
        SUCCEEDED="true"
else
        SUCCEEDED="false"
fi

$PING_SCRIPT "$UUID" "$SUCCEEDED"
