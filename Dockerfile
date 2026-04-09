FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/apache-vhost.conf /usr/local/etc/render/apache-vhost.conf
COPY docker/start-apache.sh /usr/local/bin/start-apache.sh
COPY . /var/www/html

RUN chmod +x /usr/local/bin/start-apache.sh \
    && mkdir -p /var/www/html/logs /var/www/html/config /var/www/html/uploads/profile_pictures \
    && chown -R www-data:www-data /var/www/html/logs /var/www/html/config /var/www/html/uploads

EXPOSE 10000

CMD ["/usr/local/bin/start-apache.sh"]
