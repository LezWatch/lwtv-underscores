#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/oz2Iox8OKnYD7uV2?status=down&msg=hourly-directory-failed
	exit 1
}

/usr/bin/wp lwtv generate cron hourly --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/oz2Iox8OKnYD7uV2?status=up&msg=hourly-completed
else
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/oz2Iox8OKnYD7uV2?status=down&msg=hourly-failed
fi
