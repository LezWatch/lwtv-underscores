#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

# Define the UUID for this specific task
UUID="YOLHUVeoryV7I5GP"

# Define the path to the ping.sh script
# Assuming it's in the same directory.
PING_SCRIPT="$(dirname "$0")/ping.sh"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "down" "ontheten-directory-failed"
	exit 1
}

/usr/bin/wp cron event run --due-now --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "up" "ontheten-completed"
else
	# Call ping.sh with UUID, status, and message
	$PING_SCRIPT "$UUID" "down" "ontheten-failed"
fi
