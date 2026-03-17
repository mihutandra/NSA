#!/bin/sh
# Wait for the nginx access log to appear, then generate a report every 30 s.

LOG=/var/log/nginx/access-local.log
OUT=/var/www/html/report.html

if [ ! -f "$OUT" ]; then
    cat >"$OUT" <<'EOF'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>GoAccess report pending</title>
</head>
<body>
    <h1>GoAccess report is starting</h1>
    <p>Reload this page after a few requests reach nginx.</p>
</body>
</html>
EOF
fi

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
