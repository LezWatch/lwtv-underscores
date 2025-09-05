#!/bin/bash
export PATH="/usr/local/bin:/usr/bin:/bin"

cd /home/wp_bg3hrq/lezwatchtv.com || exit 1

now=$(date)
echo $now >> /home/wp_bg3hrq/cron/otd.log 2>&1

/usr/bin/wp lwtv generate otd --path=/home/wp_bg3hrq/lezwatchtv.com/ >> /home/wp_bg3hrq/cron/otd.log 2>&1

/usr/bin/curl -fsS -m 10 --retry 5 -o /dev/null https://health.ipstenu.com/ping/sY19ROZQjbxP8e5HhKjnIA/lwtv-set-otd
