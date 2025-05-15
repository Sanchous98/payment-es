FROM php:8.4-cli-alpine

RUN --mount=type=bind,from=mlocati/php-extension-installer:latest,target=/usr/bin/install-php-extensions,src=/usr/bin/install-php-extensions \
    install-php-extensions @composer bcmath intl xdebug