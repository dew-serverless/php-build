# Build upon Function Compute custom runtime
# See: https://help.aliyun.com/document_detail/132044.html
FROM aliyunfc/runtime-custom:latest

# Define a directory for building
ENV BUILD_DIR=/tmp/build

# Define a directory for installation
ENV INSTALL_DIR=/opt/propeller


##############################################################################
# Build PHP
##############################################################################

ENV PHP_VERSION=8.2.3
ENV PHP_BUILD_DIR=$BUILD_DIR/php

WORKDIR $PHP_BUILD_DIR

RUN set -xe; \
    curl -sL https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz \
        | tar xzC $PHP_BUILD_DIR --strip-components 1 \
    && ./configure \
    && make \
    && make install


# Change directory to where the source code placed
WORKDIR /code
