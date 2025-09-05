#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || exit 1

cstart=$(date)
echo "START" $cstart >> /home/wp_bg3hrq/cron/debug.log 2>&1

/usr/bin/wp lwtv generate debug --path=/home/wp_bg3hrq/lezwatchtv.com/ >> /home/wp_bg3hrq/cron/debug.log 2>&1

curl -fsS -m 10 --retry 5 -o /dev/null https://health.ipstenu.com/ping/sY19ROZQjbxP8e5HhKjnIA/lwtv-debug-daily

cend=$(date)
echo "END" $cend >> /home/wp_bg3hrq/cron/debug.log 2>&1
