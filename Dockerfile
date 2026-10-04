FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
	git \
	unzip \
	libzip-dev \
	default-mysql-client \
	&& rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql zip \
	&& pecl install xdebug \
	&& docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini


# Xdebug stays off unless XDEBUG_MODE is set at run time; the env var wins over the ini file.
ENV XDEBUG_MODE=off

# The container runs as the host user's numeric id (see `user:` in docker-compose.yml).
# That id has no /etc/passwd entry, so HOME would resolve to the unwritable root
# directory; Composer needs a writable home of its own.
ENV HOME=/tmp
ENV COMPOSER_HOME=/tmp/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/app

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
