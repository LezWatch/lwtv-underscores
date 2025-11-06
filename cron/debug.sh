#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/8qGWpfC-UzrCZCyV?status=down&msg=debug-directory-failed
	exit 1
}

/usr/bin/wp lwtv generate debug --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/8qGWpfC-UzrCZCyV?status=up&msg=debug-completed
else
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/8qGWpfC-UzrCZCyV?status=down&msg=debug-failed
fi
