#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/tDyL8iiSG0wHPna6?status=down&msg=tvmaze-directory-failed
	exit 1
}

/usr/bin/wp lwtv generate tvmaze --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/tDyL8iiSG0wHPna6?status=up&msg=tvmaze-completed
else
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/tDyL8iiSG0wHPna6?status=down&msg=tvmaze-failed
fi
