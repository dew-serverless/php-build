# Build upon Function Compute custom runtime
# See: https://help.aliyun.com/document_detail/132044.html
FROM aliyunfc/runtime-custom:latest

# Define a directory for building
ENV BUILD_DIR=/tmp/build

# Define a directory for installation
ENV INSTALL_DIR=/opt/propeller

# Configure environment variables
ENV PKG_CONFIG_PATH=${INSTALL_DIR}/lib/pkgconfig \
    LD_LIBRARY_PATH=${INSTALL_DIR}/lib \
    PATH=${INSTALL_DIR}/bin:$PATH


#
# Build oniguruma
#
# regular expression library
#
# Needed by:
#  - php --enable-mbstring
#

ENV ONIG_VERSION=6.9.8
ENV ONIG_BUILD_DIR=${BUILD_DIR}/oniguruma

WORKDIR ${ONIG_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/kkos/oniguruma/releases/download/v${ONIG_VERSION}/onig-${ONIG_VERSION}.tar.gz \
        | tar xzC ${ONIG_BUILD_DIR} --strip-components 1 \
    && ./configure --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


#
# Build libxml2
#

ENV LIBXML2_VERSION=2.10.3
ENV LIBXML2_BUILD_DIR=${BUILD_DIR}/libxml2

WORKDIR ${LIBXML2_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://download.gnome.org/sources/libxml2/$(echo $LIBXML2_VERSION | cut -d'.' -f-2)/libxml2-${LIBXML2_VERSION}.tar.xz \
        | tar xJC ${LIBXML2_BUILD_DIR} --strip-components 1 \
    && CFLAGS='-O2 -fno-semantic-interposition' \
        ./configure --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


#
# Build PHP
#

ENV PHP_VERSION=8.2.3
ENV PHP_BUILD_DIR=$BUILD_DIR/php

WORKDIR $PHP_BUILD_DIR

RUN set -xe; \
    curl -sL https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz \
        | tar xzC $PHP_BUILD_DIR --strip-components 1 \
    && ./configure \
        --prefix=${INSTALL_DIR} \
        --enable-bcmath \
        --enable-mbstring \
        --with-curl=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


# Change directory to where the source code placed
WORKDIR /code
