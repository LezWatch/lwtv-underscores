#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/YOLHUVeoryV7I5GP?status=down&msg=debug-directory-failed
	exit 1
}

/usr/bin/wp cron event run --due-now --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/YOLHUVeoryV7I5GP?status=up&msg=ontheten-completed
else
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/YOLHUVeoryV7I5GP?status=down&msg=ontheten-failed
fi
