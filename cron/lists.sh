#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

# Define the UUID for this specific task
UUID="n_XK3iMolnf23y6C"

# Define the path to the ping.sh script
# Assuming it's in the same directory.
PING_SCRIPT="./ping.sh"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	$PING_SCRIPT "$UUID" "down" "lists-directory-failed"
	exit 1
}

/usr/bin/wp lwtv generate lists --path=/home/wp_bg3hrq/lezwatchtv.com/

# Call ping.sh with UUID, status, and message
if [ $? -eq 0 ]; then
	$PING_SCRIPT "$UUID" "up" "lists-completed"
else
	$PING_SCRIPT "$UUID" "down" "lists-failed"
fi
