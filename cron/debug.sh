#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

# Define the UUID for this specific task
UUID="K64SlnbeEEaHlSsg"

# Define the path to the ping.sh script
# Assuming it's in the same directory.
PING_SCRIPT="/home/wp_bg3hrq/cron/ping.sh"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "down" "debug-directory-failed"
	exit 1
}

/usr/bin/wp lwtv generate debug --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "up" "debug-completed"
else
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "down" "debug-failed"
fi
