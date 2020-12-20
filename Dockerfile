ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json /app
RUN composer install

COPY . /app
CMD [ "/bin/bash" ]

# Usage:
# export PHP_VERSION=7.2
# docker build -t dmp:${PHP_VERSION} --build-arg PHP_VERSION=${PHP_VERSION} .
# docker run -it --rm dmp:${PHP_VERSION} ./vendor/bin/phpunit
# docker run -it --rm -v `pwd`/src:/app/src -v `pwd`/tests:/app/tests dmp:${PHP_VERSION}
