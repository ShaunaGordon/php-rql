FROM dunglas/frankenphp

RUN install-php-extensions \
    xdebug


# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
