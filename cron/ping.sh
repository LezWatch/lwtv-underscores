#!/bin/bash

# Assign command-line arguments to variables
UUID=$1
STATUS=$2
MESSAGE=$3

# Check if all arguments are provided
if [ -z "$UUID" ] || [ -z "$STATUS" ] || [ -z "$MESSAGE" ]; then
	echo "Usage: $0 <uuid> <status> <message>"
	exit 1
fi

BASEURL="https://uptime.ipstenu.com/api/push/$UUID"

# Execute the curl command to send the ping
/usr/bin/curl -X POST -m 10 --retry 5 -o /dev/null "$BASEURL?status=$STATUS&msg=$MESSAGE"
