#!/bin/bash
# ~/bin/lwtv-api-rollup.sh
# Daily rollup of /wp-json/lwtv/v1/ hits from nginx access logs into a CSV.
# Columns: date,endpoint,consumer,status,hits,bytes
set -uo pipefail

LOGDIR="$HOME/logs/lezwatchtv.com"
OUT="$HOME/lwtv-api-usage/usage.csv"
HEADER="date,endpoint,consumer,status,hits,bytes"

mkdir -p "$(dirname "$OUT")"

if [ ! -f "$OUT" ]; then
  echo "$HEADER" > "$OUT"
elif [ "$(head -n1 "$OUT")" != "$HEADER" ]; then
  echo "ERROR: $OUT has an old header. Move it aside (mv usage.csv usage.old.csv) and re-run." >&2
  exit 1
fi

zcat -f "$LOGDIR"/access.log* | awk -F'"' -v out="$OUT" '
BEGIN {
  n = split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", m, " ")
  for (i = 1; i <= n; i++) mon[m[i]] = sprintf("%02d", i)
  # dates already in the CSV are skipped (idempotent)
  while ((getline line < out) > 0) { split(line, f, ","); seen[f[1]] = 1 }
  close(out)
}

# Every line: track the newest date in the logs. That day is still
# in progress, so it is never written (no reliance on the server clock).
{
  if (match($1, /\[[0-9]+\/[A-Za-z]+\/[0-9]+/)) {
    split(substr($1, RSTART + 1, RLENGTH - 1), d, "/")
    day = sprintf("%s-%s-%02d", d[3], mon[d[2]], d[1])
    if (day > maxday) maxday = day
  } else {
    day = ""
  }
}

day != "" && $2 ~ / \/wp-json\/lwtv\/v1\// {
  if (day in seen) next

  split($2, r, " "); p = r[2]
  sub(/\?.*/, "", p); sub(/\/$/, "", p); gsub(/,/, "%2C", p)

  ua = $6
  if      (ua ~ /^Uptime-Kuma/)                       ua = "uptime-kuma"
  else if (ua ~ /^WordPress/)                         sub(/WordPress\/[0-9.]+; /, "WP:", ua)
  else if (ua == "Mozilla/5.0")                       ua = "bare-mozilla"
  else if (ua ~ /research; low-rate/)                 ua = "low-rate-reader"
  else if (ua ~ /Chrome\/113\.0\.0\.0/)               ua = "chrome113"
  else if (ua ~ /^Mozilla/ && ua ~ /bot|Bot|crawl/)   ua = "search-bot"
  else if (ua ~ /^Mozilla/)                           ua = "browser-ish"
  else                                                sub(/[\/ ].*/, "", ua)
  gsub(/,/, " ", ua)

  split($3, s, " ")   # s[1] = status, s[2] = body bytes
  key = day SUBSEP p SUBSEP ua SUBSEP s[1]
  hits[key]++
  bytes[key] += s[2]
}

END {
  for (k in hits) {
    split(k, x, SUBSEP)
    if (x[1] >= maxday) continue
    print x[1] "," x[2] "," x[3] "," x[4] "," hits[k] "," bytes[k]
  }
}' | sort >> "$OUT"
