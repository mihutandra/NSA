#!/bin/sh
set -e

# Generate self-signed TLS certificate on first run
mkdir -p /etc/nginx/ssl
if [ ! -f /etc/nginx/ssl/cert.pem ]; then
    echo "Generating self-signed TLS certificate..."
    openssl req -x509 -nodes -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/key.pem \
        -out  /etc/nginx/ssl/cert.pem \
        -days 365 \
        -subj "/C=RO/ST=Bucharest/L=Bucharest/O=NSA/OU=Dev/CN=nsa.local" \
        2>/dev/null
    echo "Certificate generated."
fi

exec nginx -g 'daemon off;'
