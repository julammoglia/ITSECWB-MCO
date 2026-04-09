#!/bin/sh
set -eu

PORT="${PORT:-10000}"

cat /usr/local/etc/render/apache-vhost.conf | sed "s/__PORT__/${PORT}/g" > /etc/apache2/sites-available/000-default.conf
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

exec apache2-foreground
