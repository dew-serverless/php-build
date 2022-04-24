# Build upon base image on custom runtime.
# Debian 9 is the current container envionment on Function Compute.
# See: https://help.aliyun.com/document_detail/132044.html
# See: https://github.com/aliyun/fc-docker/blob/master/custom/base/Dockerfile
FROM aliyunfc/fc-runtime:java8-stretch-base

RUN apt-get update

RUN apt-get install -y autoconf libtool libsqlite3-dev tclsh

WORKDIR /tmp

# Directory for package installation
ENV INSTALL_DIR=/opt/propeller

# Directory for building source code
ENV BUILD_DIR=/tmp/build

# Create the directories
RUN mkdir -p ${INSTALL_DIR} \
    ${BUILD_DIR}

ENV PKG_CONFIG_PATH=${INSTALL_DIR}/lib/pkgconfig

# Build libxml2
# https://gitlab.gnome.org/GNOME/libxml2
# Needed by: php

ENV VERSION_LIBXML2=2.9.13
ENV LIBXML2_BUILD_DIR=${BUILD_DIR}/libxml2

RUN mkdir ${LIBXML2_BUILD_DIR}

RUN curl -s -L https://gitlab.gnome.org/GNOME/libxml2/-/archive/v${VERSION_LIBXML2}/libxml2-v${VERSION_LIBXML2}.tar.gz \
    | tar xzC ${LIBXML_BUILD_DIR} --strip 1

WORKDIR ${LIBXML_BUILD_DIR}

RUN set -xe; \
    CFLAGS='-O2 -fno-semantic-interposition' \
    ./autogen.sh \
        --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


# Build SQLite
# https://sqlite.org/index.html
# Needed by: PHP

ENV VERSION_SQLITE=3.38.2
ENV SQLITE_BUILD_DIR=${BUILD_DIR}/sqlite

RUN mkdir ${SQLITE_BUILD_DIR}

RUN set -xe; \
    curl -s -L https://github.com/sqlite/sqlite/archive/refs/tags/version-${VERSION_SQLITE}.tar.gz \
    | tar xzC ${SQLITE_BUILD_DIR} --strip 1

WORKDIR ${SQLITE_BUILD_DIR}

RUN set -xe; \
    ./configure \
        --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


# Build libcurl
# https://curl.se
# Needed by: PHP

ENV VERSION_LIBCURL=7.82.0
ENV LIBCURL_BUILD_DIR=${BUILD_DIR}/libcurl

RUN mkdir ${LIBCURL_BUILD_DIR}

RUN set -xe; \
    curl -s -L https://curl.se/download/curl-${VERSION_LIBCURL}.tar.gz \
    | tar xzC ${LIBCURL_BUILD_DIR} --strip 1

WORKDIR ${LIBCURL_BUILD_DIR}

RUN set -xe; \
    ./configure \
        --prefix=${INSTALL_DIR} \
        --with-openssl \
    && make -j $(nproc) \
    && make install


# Build oniguruma
# https://github.com/kkos/oniguruma
# Needed by: PHP --enable-mbstring

ENV VERSION_ONIGURUMA=6.9.7.1
ENV ONIGURUMA_BUILD_DIR=${BUILD_DIR}/oniguruma

RUN mkdir ${ONIGURUMA_BUILD_DIR}

RUN set -xe; \
    curl -s -L https://github.com/kkos/oniguruma/releases/download/v${VERSION_ONIGURUMA}/onig-${VERSION_ONIGURUMA}.tar.gz \
    | tar xzC ${ONIGURUMA_BUILD_DIR} --strip 1

WORKDIR ${ONIGURUMA_BUILD_DIR}

RUN set -xe; \
    ./configure \
        --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


# Build readline
# https://tiswww.case.edu/php/chet/readline/rltop.html
# Needed by: PHP --with-readline

ENV VERSION_READLINE=8.1
ENV READLINE_BUILD_DIR=${BUILD_DIR}/readline

RUN mkdir ${READLINE_BUILD_DIR}

RUN set -xe; \
    curl -s -L ftp://ftp.cwru.edu/pub/bash/readline-${VERSION_READLINE}.tar.gz \
    | tar xzC ${READLINE_BUILD_DIR} --strip 1

WORKDIR ${READLINE_BUILD_DIR}

RUN set -xe; \
    ./configure \
        --prefix ${INSTALL_DIR} \
        --disable-shared \
    && make -j $(nproc) \
    && make install


# Build PHP
# https://www.php.net

ENV VERSION_PHP=8.1.5
ENV PHP_BUILD_DIR=${BUILD_DIR}/php

RUN mkdir ${PHP_BUILD_DIR}

RUN set -xe; \
    curl -s -L https://www.php.net/distributions/php-${VERSION_PHP}.tar.gz \
    | tar xzC ${PHP_BUILD_DIR} --strip 1

WORKDIR ${PHP_BUILD_DIR}

RUN set -xe; \
    ./buildconf \
    && ./configure \
        --prefix=${INSTALL_DIR} \
        --enable-bcmath \
        --enable-mbstring \
        --with-curl \
        --with-openssl \
        --with-pdo-mysql \
        --with-readline=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install
