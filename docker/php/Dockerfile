# PHP 8.5 + FPM для локальной разработки (на проде BeGet — тоже PHP-FPM).
FROM php:8.5-fpm

# Готовый установщик расширений — надёжнее ручной возни с docker-php-ext-install.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/bin/

# Расширения, нужные Symfony 7.4 / Doctrine / тестам:
#  pdo_mysql — БД, intl/mbstring — строки/локали, xml — Serializer и PHPUnit,
#  zip — composer, exif/gd — картинки, opcache — скорость. @composer — сам Composer.
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        intl \
        xml \
        zip \
        exif \
        gd \
        bcmath \
        opcache \
        @composer

# Пользователь под UID хоста, чтобы файлы из контейнера не были root на хосте.
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} dev \
 && useradd -u ${UID} -g ${GID} -m dev \
 && sed -i "s/^user = www-data/user = dev/; s/^group = www-data/group = dev/" \
        /usr/local/etc/php-fpm.d/www.conf

# WORKDIR /app, а не /app/backend: иначе Docker создаёт backend/ от root до
# переключения на пользователя dev, и composer потом не может в неё писать.
WORKDIR /app
