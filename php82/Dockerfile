# Build upon Function Compute custom runtime build environment
# See: https://help.aliyun.com/document_detail/132044.html
FROM aliyunfc/runtime-custom:build-1.10.8 as builder

# Define a directory for building
ENV BUILD_DIR=/tmp/build

# Define a directory for installation
ENV INSTALL_DIR=/opt/dew

# Configure environment variables
ENV PKG_CONFIG_PATH=${INSTALL_DIR}/lib64/pkgconfig:${INSTALL_DIR}/lib/pkgconfig:${INSTALL_DIR}/lib/x86_64-linux-gnu/pkgconfig \
    LD_LIBRARY_PATH=${INSTALL_DIR}/lib64:${INSTALL_DIR}/lib:${INSTALL_DIR}/lib/x86_64-linux-gnu \
    PATH=${INSTALL_DIR}/bin:$PATH

# Install dependencies
# libicu-dev: PHP intl extension requires ICU library
RUN apt-get update && \
    apt-get install -y libicu-dev && \
    rm -rf /var/lib/apt/lists/*

# Prepare directories
RUN mkdir -p ${INSTALL_DIR} \
    ${INSTALL_DIR}/etc/php \
    ${INSTALL_DIR}/etc/php/conf.d


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
# https://www.openssl.org
#
# Needed by:
#  - curl
#

ENV OPENSSL_VERSION=3.0.8
ENV OPENSSL_BUILD_DIR=${BUILD_DIR}/openssl
ENV CA_BUNDLE_SOURCE="https://curl.se/ca/cacert.pem"
ENV CA_BUNDLE=${INSTALL_DIR}/dew/ssl/cert.pem

WORKDIR ${OPENSSL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/openssl/openssl/releases/download/openssl-${OPENSSL_VERSION}/openssl-${OPENSSL_VERSION}.tar.gz \
    | tar xzC ${OPENSSL_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    ./Configure \
        --prefix=${INSTALL_DIR} \
        --openssldir=${INSTALL_DIR}/dew/ssl \
        --release

RUN make -j$(nproc) && make install_sw install_ssldirs

RUN curl -sL -o ${CA_BUNDLE} ${CA_BUNDLE_SOURCE}


#
# Build libssh2
# https://www.libssh2.org
#
# Requires:
#   - OpenSSL
#   - zlib
#

ENV LIBSSH2_VERSION=1.10.0
ENV LIBSSH2_BUILD_DIR=${BUILD_DIR}/libssh2

WORKDIR ${LIBSSH2_BUILD_DIR}/bin

RUN set -xe; \
    curl -sL https://libssh2.org/download/libssh2-${LIBSSH2_VERSION}.tar.gz \
        | tar xzC ${LIBSSH2_BUILD_DIR} --strip-components 1

RUN cmake \
    -DCMAKE_BUILD_TYPE=Release \
    -DCMAKE_INSTALL_PREFIX=${INSTALL_DIR} \
    # Using OpenSSL for cryptographic operations
    -DCRYPTO_BACKEND=OpenSSL \
    # Build a shared library (.so) instead of a static one
    -DBUILD_SHARED_LIBS=ON \
    # Supports data compression
    -DENABLE_ZLIB_COMPRESSION=ON \
    ..

RUN cmake --build . --target install


#
# Build nghttp2
# https://nghttp2.org
#
# Needed by:
#   - curl
#

ENV NGHTTP2_VERSION=1.52.0
ENV NGHTTP2_BUILD_DIR=${BUILD_DIR}/nghttp2

WORKDIR ${NGHTTP2_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/nghttp2/nghttp2/releases/download/v${NGHTTP2_VERSION}/nghttp2-${NGHTTP2_VERSION}.tar.gz \
    | tar xzC ${NGHTTP2_BUILD_DIR} --strip-components 1

RUN CFLAGS="-I${INSTALL_DIR}/include" \
    LDFLAGS="-L${INSTALL_DIR}/lib" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        # Build libnghttp2.so only
        --enable-lib-only

RUN make -j$(nproc) install-strip


#
# Build curl
#
# Requires:
#   - OpenSSL
#   - libssh2
#   - nghttp2
# Needed by:
#   - php (--with-curl)
#

ENV CURL_VERSION=7.88.1
ENV CURL_BUILD_DIR=${BUILD_DIR}/curl

WORKDIR ${CURL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://curl.se/download/curl-${CURL_VERSION}.tar.gz \
    | tar xzC ${CURL_BUILD_DIR} --strip-components 1

RUN CFLAGS="-I${INSTALL_DIR}/include -O2 -pipe" \
    LDFLAGS="-L${INSTALL_DIR}/lib" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        --with-ca-bundle=${CA_BUNDLE} \
        --with-openssl \
        --with-libssh2 \
        --with-nghttp2 \
        # Eliminate unneeded symbols in the shared library
        --enable-symbol-hiding \
        # Without built-in documentation
        --disable-manual \
        # Eliminate debugging strings and error code strings
        --disable-verbose

RUN make -j$(nproc) && make install


#
# Build onig
# https://github.com/kkos/oniguruma
#
# regular expression library
#
# Needed by:
#  - php (--enable-mbstring)
#

ENV ONIG_VERSION=6.9.8
ENV ONIG_BUILD_DIR=${BUILD_DIR}/onig

WORKDIR ${ONIG_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/kkos/oniguruma/releases/download/v${ONIG_VERSION}/onig-${ONIG_VERSION}.tar.gz \
        | tar xzC ${ONIG_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    ./configure \
        --prefix=${INSTALL_DIR}

RUN make -j$(nproc) && make install


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
# https://www.sqlite.org
#
# Needed by:
#   - php
#

ENV LIBSQLITE_BUILD_DIR=${BUILD_DIR}/libsqlite

WORKDIR ${LIBSQLITE_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://www.sqlite.org/2023/sqlite-autoconf-3410200.tar.gz \
    | tar xzC ${LIBSQLITE_BUILD_DIR} --strip-components 1

RUN CFLAGS="-Os" \
    ./configure \
        --prefix=${INSTALL_DIR}

RUN make -j$(nproc) && make install


#
# Build libargon2
# https://github.com/P-H-C/phc-winner-argon2
#
# Needed by:
#   - php
#

ENV LIBARGON2_VERSION=20190702
ENV LIBARGON2_BUILD_DIR=${BUILD_DIR}/libargon2

WORKDIR ${LIBARGON2_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/P-H-C/phc-winner-argon2/archive/refs/tags/${LIBARGON2_VERSION}.tar.gz \
    | tar xzC ${LIBARGON2_BUILD_DIR} --strip-components 1

RUN make install PREFIX=${INSTALL_DIR}


#
# Build libzip
# https://libzip.org
#
# Requires:
#   - openssl
#   - zlib
# Needed by:
#   - php

ENV LIBZIP_VERSION=1.9.2
ENV LIBZIP_BUILD_DIR=${BUILD_DIR}/libzip

WORKDIR ${LIBZIP_BUILD_DIR}/build

RUN set -xe; \
    curl -sL https://libzip.org/download/libzip-${LIBZIP_VERSION}.tar.gz \
    | tar xzC ${LIBZIP_BUILD_DIR} --strip-components 1

RUN cmake \
    -DCMAKE_INSTALL_PREFIX=${INSTALL_DIR} \
    -DCMAKE_BUILD_TYPE=Release \
    # Build a shared library (.so) instead of a static one
    -DBUILD_SHARED_LIBS=ON \
    ..

RUN make -j$(nproc) && make install


#
# Build PHP
# https://www.php.net
#
# Requires:
#   - curl
#   - libargon2
#   - libzip
#   - oniguruma
#   - openssl
#   - pgsql
#   - zlib

ENV PHP_VERSION=8.2.3
ENV PHP_BUILD_DIR=${BUILD_DIR}/php
ENV PHP_INI_DIR=${INSTALL_DIR}/etc/php

WORKDIR ${PHP_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz \
    | tar xzC ${PHP_BUILD_DIR} --strip-components 1

RUN CFLAGS="-march=x86-64 -O3 -pipe -fpie" \
    LDFLAGS="-Wl,-pie" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        --disable-cgi \
        --disable-phpdbg \
        --enable-bcmath \
        --enable-exif \
        --enable-fpm \
        --enable-ftp \
        --enable-intl \
        --enable-mbstring \
        --enable-option-checking=fatal \
        --enable-sockets \
        --with-config-file-path=${PHP_INI_DIR} \
        --with-config-file-scan-dir=${PHP_INI_DIR}/conf.d:/code/php/conf.d \
        --with-curl=${INSTALL_DIR} \
        --with-iconv \
        --with-openssl \
        --with-password-argon2 \
        --with-pdo-mysql \
        --with-pdo-pgsql=${INSTALL_DIR} \
        --with-pear \
        --with-readline \
        --with-zip \
        --with-zlib

RUN set xe; \
    make -j$(nproc); \
    make install; \
    cp php.ini-production ${INSTALL_DIR}/etc/php/php.ini


# Constrct everything we need into dedicated location before moving to next stage

RUN mkdir -p /layer \
    /layer/bin \
    /layer/lib \
    /layer/lib/php/extensions \
    /layer/etc/php \
    /layer/etc/php/conf.d \
    /layer/dew/ssl

RUN set -xe; \
    # Copy executables
    cp ${INSTALL_DIR}/bin/php  /layer/bin/; \
    cp ${INSTALL_DIR}/sbin/php-fpm /layer/bin/; \
    # Copy OpenSSL configuration and CA bundle
    cp ${INSTALL_DIR}/dew/ssl/openssl.cnf /layer/dew/ssl/; \
    cp ${CA_BUNDLE} /layer/dew/ssl/; \
    # Copy shared library (.so)
    ldd ${INSTALL_DIR}/bin/php \
        | grep ${INSTALL_DIR} \
        | cut -d' ' -f3 \
        | xargs -I % cp % /layer/lib/; \
    # Copy PHP extensions
    cp $(php -r "echo ini_get('extension_dir');")/* /layer/lib/php/extensions/; \
    # Copy PHP Configuration file
    cp ${INSTALL_DIR}/etc/php/php.ini /layer/etc/php/; \
    # Strip all symbols and debugging information for executables
    find /layer -type f -executable -exec strip --strip-all '{}' + || true

COPY stubs/php.ini /layer/etc/php/conf.d


# Leave build environment behind and start with a clean image
FROM aliyunfc/runtime-custom:1.10.8

COPY --from=builder /layer /opt/dew


# Change directory to where the source code placed
WORKDIR /code
