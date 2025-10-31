#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || {
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/n_XK3iMolnf23y6C?status=down&msg=lists-directory-failed
	exit 1
}

/usr/bin/wp lwtv generate lists --path=/home/wp_bg3hrq/lezwatchtv.com/

if [ $? -eq 0 ]; then
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/n_XK3iMolnf23y6C?status=up&msg=lists-completed
else
	/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://uptime.ipstenu.com/api/push/n_XK3iMolnf23y6C?status=down&msg=lists-failed
fi
