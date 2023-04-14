# Build upon Debian 9 (stretch)
# which is the host OS of Function Compute runtime environment.
# See: https://help.aliyun.com/document_detail/132044.html
FROM debian:stretch-slim

# Define a directory for building
ENV BUILD_DIR=/tmp/build

# Define a directory for installation
ENV INSTALL_DIR=/opt/dew

# Configure environment variables
ENV PKG_CONFIG_PATH=${INSTALL_DIR}/lib64/pkgconfig:${INSTALL_DIR}/lib/pkgconfig \
    LD_LIBRARY_PATH=${INSTALL_DIR}/lib64:${INSTALL_DIR}/lib \
    PATH=${INSTALL_DIR}/bin:$PATH

# Install dependencies
RUN apt-get update && apt-get install -y \
        autoconf \
        curl \
        g++ \
        gcc \
        make \
        perl \
        pkg-config \
        tclsh \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Prepare directories
RUN mkdir -p ${INSTALL_DIR} \
    ${INSTALL_DIR}/etc/php \
    ${INSTALL_DIR}/etc/php/conf.d


#
# Build zlib
#
# Needed by:
#   - php (--with-zlib)
#

ENV ZLIB_VERSION=1.2.13
ENV ZLIB_BUILD_DIR=${BUILD_DIR}/zlib

WORKDIR ${ZLIB_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://www.zlib.net/zlib-${ZLIB_VERSION}.tar.gz \
        | tar xzC ${ZLIB_BUILD_DIR} --strip-components 1 \
    && ./configure --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


#
# Build PostgreSQL
#
# Needed by:
#   - php (--with-pdo-pgsql)
#

ENV PGSQL_VERSION=15.2
ENV PGSQL_BUILD_DIR=${BUILD_DIR}/pgsql

WORKDIR ${PGSQL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://ftp.postgresql.org/pub/source/v${PGSQL_VERSION}/postgresql-${PGSQL_VERSION}.tar.gz \
        | tar xzC ${PGSQL_BUILD_DIR} --strip-components 1 \
    && CFLAGS="-I${INSTALL_DIR}/include" \
       LDFLAGS="-L${INSTALL_DIR}/lib" \
       ./configure \
           --prefix=${INSTALL_DIR} \
           --without-readline \
    && make -j $(nproc) \
    && make install


#
# Build OpenSSL
#
# Needed by:
#  - libcurl
#

ENV OPENSSL_VERSION=3.0.8
ENV OPENSSL_BUILD_DIR=${BUILD_DIR}/openssl
ENV CA_BUNDLE_SOURCE="https://curl.se/ca/cacert.pem"
ENV CA_BUNDLE=${INSTALL_DIR}/dew/ssl/cert.pem

WORKDIR ${OPENSSL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/openssl/openssl/releases/download/openssl-${OPENSSL_VERSION}/openssl-${OPENSSL_VERSION}.tar.gz \
        | tar xzC ${OPENSSL_BUILD_DIR} --strip-components 1 \
    && ./Configure \
        --prefix=${INSTALL_DIR} \
        --openssldir=${INSTALL_DIR}/dew/ssl \
    && make -j $(nproc) \
    && make install

RUN curl -sL -o ${CA_BUNDLE} ${CA_BUNDLE_SOURCE}

#
# Build libcurl
#
# Needed by:
#   - php (--with-curl)
#

ENV LIBCURL_VERSION=7.88.1
ENV LIBCURL_BUILD_DIR=${BUILD_DIR}/libcurl

WORKDIR ${LIBCURL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://curl.se/download/curl-${LIBCURL_VERSION}.tar.gz \
        | tar xzC ${LIBCURL_BUILD_DIR} --strip-components 1 \
    && ./configure \
        --prefix=${INSTALL_DIR} \
        --with-ca-bundle=${CA_BUNDLE} \
        --with-openssl \
    && make -j $(nproc) \
    && make install


#
# Build oniguruma
#
# regular expression library
#
# Needed by:
#  - php (--enable-mbstring)
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
       ./configure \
           --prefix=${INSTALL_DIR} \
           --without-python \
    && make -j $(nproc) \
    && make install


#
# Build libsqlite
#

ENV LIBSQLITE_VERSION=3.41.0
ENV LIBSQLITE_BUILD_DIR=${BUILD_DIR}/libsqlite

WORKDIR ${LIBSQLITE_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/sqlite/sqlite/archive/refs/tags/version-${LIBSQLITE_VERSION}.tar.gz \
        | tar xzC ${LIBSQLITE_BUILD_DIR} --strip-components 1 \
    && ./configure \
        --prefix=${INSTALL_DIR} \
    && make -j $(nproc) \
    && make install


#
# Build PHP
#

ENV PHP_VERSION=8.2.3
ENV PHP_BUILD_DIR=${BUILD_DIR}/php

WORKDIR ${PHP_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz \
        | tar xzC ${PHP_BUILD_DIR} --strip-components 1 \
    && CFLAGS="-march=x86-64 -O3 -pipe -fpie" \
       LDFLAGS="-Wl,-pie" \
       ./configure \
           --prefix=${INSTALL_DIR} \
           --enable-bcmath \
           --enable-fpm \
           --enable-mbstring \
           --enable-option-checking=fatal \
           --enable-sockets \
           --with-config-file-path=${INSTALL_DIR}/etc/php \
           --with-config-file-scan-dir=${INSTALL_DIR}/etc/php/conf.d:/code/php/conf.d \
           --with-curl=${INSTALL_DIR} \
           --with-openssl \
           --with-pdo-mysql \
           --with-pdo-pgsql=${INSTALL_DIR} \
           --with-pear \
           --with-zlib \
    && make -j $(nproc) \
    && make install \
    && cp php.ini-production ${INSTALL_DIR}/etc/php/php.ini


# Change directory to where the source code placed
WORKDIR /code
