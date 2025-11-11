#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

# Define the UUID for this specific task
UUID="oz2Iox8OKnYD7uV2"

# Define the path to the ping.sh script
# Assuming it's in the same directory.
PING_SCRIPT="$(dirname "$0")/ping.sh"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "down" "hourly-directory-failed"
	exit 1
}

/usr/bin/wp lwtv generate cron hourly --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "up" "hourly-completed"
else
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "down" "hourly-failed"
fi
