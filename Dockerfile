# PHP is pinned deliberately. The code predates 8.5, which turns its implicit-nullable
# params and curl_close()/imagedestroy() calls into deprecations — and into hard errors
# on 9.x. "latest" would break this app on a Tuesday.
FROM php:8.3-apache

# gd with webp: every avatar upload is re-encoded to .webp by storeAvatarUpload(),
# and seed.php does the same for the photos it downloads. pdo_sqlite and sqlite3
# already ship enabled in the official image.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libwebp-dev libjpeg62-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd \
 && rm -rf /var/lib/apt/lists/*

# The app's own limit is 5 MB per avatar, checked in storeAvatarUpload(). PHP's
# default upload_max_filesize is 2 MB, which would reject a 4 MB photo before the
# app ever sees it — the user gets a blank failure instead of the app's message.
RUN { \
      echo 'expose_php=0'; \
      echo 'display_errors=0'; \
      echo 'session.cookie_httponly=1'; \
      echo 'session.use_strict_mode=1'; \
      echo 'upload_max_filesize=6M'; \
      echo 'post_max_size=8M'; \
    } > /usr/local/etc/php/conf.d/org-tree.ini

# Serve public/ and nothing above it: src/, data/, uploads/ and .env are its
# siblings, so they are unreachable over HTTP by construction rather than by a
# deny-list. Widening this document root silently un-protects the whole dataset.
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite \
 && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

WORKDIR /var/www/html

# Copy the tree wholesale rather than naming files. A hand-written file list is a
# trap: the next new top-level PHP file gets left out and the container dies on the
# include, while the build stays green. .dockerignore holds the exclusions.
COPY . /var/www/html/

# data/ becomes a volume at runtime, so the seed CSV has to survive outside it —
# an empty bind mount would otherwise hide it and first-boot seeding would fail.
RUN mkdir -p /usr/local/share/org-tree \
 && cp data/org_struct_code_store.csv /usr/local/share/org-tree/ \
 && chmod +x docker/entrypoint.sh

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
