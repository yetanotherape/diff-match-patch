FROM php:7.1-cli

WORKDIR /usr/src/myapp

RUN apt-get update && apt-get install -y \
    git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json /usr/src/myapp
RUN composer install

COPY . /usr/src/myapp
CMD [ "/bin/bash" ]

# docker build -t diff-match-patch:7.1 -f ./Dockerfile_7.1 .
# docker run -it --rm diff-match-patch:7.1 ./vendor/bin/phpunit
# docker run -it -v `pwd`/src:/usr/src/myapp/src -v `pwd`/tests:/usr/src/myapp/tests  --rm  diff-match-patch:7.1
