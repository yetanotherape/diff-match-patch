FROM diegomarangoni/hhvm:cli

WORKDIR /usr/src/myapp

RUN apt-get update && apt-get install -y \
    git \
    curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json /usr/src/myapp
RUN composer install

COPY . /usr/src/myapp
CMD ["./vendor/bin/phpunit" ]

# docker build -t diff-match-patch:hhvm -f ./Dockerfile_hhvm .
# docker run -it --rm diff-match-patch:hhvm
