ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json /app
RUN composer install --no-progress --no-interaction --prefer-dist

COPY . /app
CMD [ "/bin/bash" ]

# Usage:
# export PHP_VERSION=7.3
# docker build --build-arg PHP_VERSION=${PHP_VERSION} -t dmp:${PHP_VERSION} .
# docker run --rm -v ./src:/app/src -v ./tests:/app/tests dmp:${PHP_VERSION} ./vendor/bin/phpunit
# docker run -it --rm -v ./src:/app/src -v ./tests:/app/tests dmp:${PHP_VERSION}
