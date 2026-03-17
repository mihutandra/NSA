FROM alpine:3.19

RUN apk add --no-cache goaccess bash

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

VOLUME ["/var/log/nginx", "/var/www/html"]

ENTRYPOINT ["/entrypoint.sh"]
