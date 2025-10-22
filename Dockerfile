# 1. Base image
FROM php:8.4-fpm-alpine

ENV TERM=linux
WORKDIR /var/www/html

# 2. Install system dependencies
RUN apk add --no-cache \
      bash \
      libzip \
      oniguruma-dev \
      icu-dev \
      zlib-dev \
      wget \
      curl \
  && apk add --no-cache --virtual .build-deps \
      curl-dev \
      libxml2-dev \
      libedit-dev \
      libzip-dev \
  \
  # 3. Compile & install PHP extensions
  && docker-php-ext-install intl pdo_mysql curl xml zip \
  \
  # 4. Clean up only the build-time deps (keep bash & runtime libs)
  && apk del .build-deps \
  && rm -rf /var/cache/apk/* /tmp/*

# 5. Install Composer
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/bin --filename=composer

# 6. Dependencies: copy only composer files first for better caching
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --prefer-dist --optimize-autoloader --no-plugins

# 7. Copy the rest of your application
COPY . .

# 8. Add xdebug
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS
RUN apk add --update linux-headers
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN apk del -f .build-deps

# Optionally configure Xdebug for coverage
ENV XDEBUG_MODE=coverage \
    XDEBUG_CONFIG="client_host=host.docker.internal"

# 9. Expose & run
EXPOSE 9000
CMD ["php-fpm"]

# 10. Install Symfony CLI
RUN wget https://get.symfony.com/cli/installer -O - | bash \
	&& mv /root/.symfony*/bin/symfony /usr/local/bin/symfony
