#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/xrPNV40IWEd5ShDP?status=down&msg=otd-directory-failed
	exit 1
}

/usr/bin/wp lwtv generate otd --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/xrPNV40IWEd5ShDP?status=up&msg=otd-completed
else
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/xrPNV40IWEd5ShDP?status=down&msg=otd-failed
fi
