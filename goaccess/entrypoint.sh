#!/bin/sh
# Wait for the nginx access log to appear, then generate a report every 30 s.

LOG=/var/log/nginx/access.log
OUT=/var/www/html/report.html

echo "Waiting for nginx access log..."
while [ ! -f "$LOG" ]; do
    sleep 5
done
echo "Log found. Starting GoAccess report loop."

while true; do
    goaccess "$LOG" \
        --log-format=COMBINED \
        --output="$OUT" \
        --no-global-config \
        2>/dev/null && echo "Report updated at $(date)" || echo "GoAccess: log is empty or parse error (retrying)"
    sleep 30
done
