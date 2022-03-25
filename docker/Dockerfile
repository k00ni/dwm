FROM php:8.0-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl git libzip-dev nano net-tools openjdk-17-jre sudo unzip wget zlib1g-dev

RUN docker-php-ext-install pdo pdo_mysql zip && docker-php-ext-enable zip

# uncomment if code coverage is required
# RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY ./includes/custom-php.ini "$PHP_INI_DIR/conf.d/custom.ini"

# adds user "dwm", adds him to group "www-data" and sets his home folder
# for more background information see:
# https://medium.com/@mccode/understanding-how-uid-and-gid-work-in-docker-containers-c37a01d01cf
RUN useradd -r --home /home/dwm -u 1000 dwm
RUN usermod -a -G www-data dwm
RUN mkdir /home/dwm
RUN mkdir /home/dwm/bin
RUN chown dwm:www-data /home/dwm
RUN adduser dwm sudo

COPY ./includes/dwm.conf /etc/apache2/sites-enabled/000-default.conf
RUN rm -rf /var/www/html/*
WORKDIR /var/www/html/
RUN echo "html/index" >> /var/www/html/index.html

# Download and setup Apache Jena
RUN cd /home/dwm && wget -c https://dlcdn.apache.org/jena/binaries/apache-jena-4.4.0.tar.gz -O - | tar -xz
RUN mv /home/dwm/apache-jena-4.4.0 /home/dwm/apache-jena

COPY ./includes/run.sh /run.sh
RUN chmod +x /run.sh

CMD ["/run.sh"]
