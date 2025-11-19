#!/bin/bash

# Assign command-line arguments to variables
UUID=$1
SUCCEEDED=$2

# Check if all arguments are provided
if [ -z "$UUID" ] || [ -z "$SUCCEEDED" ]; then
	echo "Usage: $0 <uuid> <succeeded: boolean>"
	exit 1
fi

BASEURL="https://health.ipstenu.com/ping/YngRQgrkWz3aUQkrLjMrPg"
STATUS=""

if [ "$SUCCEEDED" != true ]; then
	STATUS="/fail"
fi

# Execute the curl command to send the ping
/usr/bin/curl -X POST -m 10 --retry 5 -o /dev/null "$BASEURL/$UUID$STATUS"
