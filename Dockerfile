# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
# Compile all C-based dependencies (libcurl, openssl, etc.)
# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

ARG DEBIAN_VERSION=latest
ARG ROADRUNNER_VERSION=latest

# Build upon Function Compute runtime
# See: https://www.alibabacloud.com/help/en/functioncompute/fc-3-0/user-guide/overview-10-2
FROM debian:${DEBIAN_VERSION} AS builder

# Define a directory for building
ENV BUILD_DIR=/tmp/build

# Define a directory for installation
ENV INSTALL_DIR=/opt

# Configure environment variables
ENV PKG_CONFIG_PATH=${INSTALL_DIR}/lib64/pkgconfig:${INSTALL_DIR}/lib/pkgconfig \
    LD_LIBRARY_PATH=${INSTALL_DIR}/lib64:${INSTALL_DIR}/lib \
    PATH=${INSTALL_DIR}/bin:$PATH

# Install dependencies
# build-essential: required for building packages, gcc, make, etc.
# pkg-config: helps to configure compiler and linker flags for libraries
# cmake: libssh2 and libzip use cmake to build
# curl: needed to download source code
# libbison-dev: needed by libpg to build parser files (gram.c and gram.h)
# libfl-dev: needed by libpg to build parser files (gram.c and gram.h)
# tclsh: needed by libsqlite3 to build the amalgamation from canonical source code
RUN apt-get update && apt-get install -y \
    build-essential \
    pkg-config \
    cmake \
    curl \
    libbison-dev \
    libfl-dev \
    tclsh \
    && rm -rf /var/lib/apt/lists/*

# Prepare directories
RUN mkdir -p ${INSTALL_DIR} \
    ${INSTALL_DIR}/etc/php \
    ${INSTALL_DIR}/etc/php/conf.d


# Build icu4c
# https://github.com/unicode-org/icu
#
# Needed by:
#   - php (--enable-intl)

ARG ICU4C_VERSION
ENV ICU4C_BUILD_DIR=${BUILD_DIR}/icu4c

WORKDIR ${ICU4C_BUILD_DIR}/source

RUN set -xe; \
    curl -sL https://github.com/unicode-org/icu/releases/download/release-$(echo $ICU4C_VERSION | sed 's/\./-/g')/icu4c-$(echo $ICU4C_VERSION | sed 's/\./_/g')-src.tgz \
    | tar xzC ${ICU4C_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O3 -pipe" \
    LDFLAGS="-Wl,-rpath,${INSTALL_DIR}/lib,--enable-new-dtags" \
    ./runConfigureICU Linux \
        --prefix=${INSTALL_DIR}

RUN set -xe; \
    make -j$(nproc); \
    make install


# Build libsqlite3
# https://www.sqlite.org
#
# Needed by:
#   - php

ARG LIBSQLITE3_VERSION
ENV LIBSQLITE3_BUILD_DIR=${BUILD_DIR}/libsqlite3

WORKDIR ${LIBSQLITE3_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/sqlite/sqlite/archive/refs/tags/version-${LIBSQLITE3_VERSION}.tar.gz \
    | tar xzC ${LIBSQLITE3_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    ./configure \
        --prefix=${INSTALL_DIR}

RUN set -xe; \
    make -j$(nproc) sqlite3.c; \
    make install


# Build zlib
# https://zlib.net
#
# Needed by:
#   - libpq
#   - libssh2
#   - php

ARG ZLIB_VERSION
ENV ZLIB_BUILD_DIR=${BUILD_DIR}/zlib

WORKDIR ${ZLIB_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://zlib.net/zlib-${ZLIB_VERSION}.tar.gz \
        | tar xzC ${ZLIB_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O3 -pipe" \
    ./configure \
        --prefix=${INSTALL_DIR}

RUN make -j$(nproc) install


# Build libpq
# https://www.postgresql.org
#
# Requires:
#   - libbison-dev
#   - libfl-dev
#   - zlib
#
# Needed by:
#   - php (--with-pdo-pgsql, --with-pgsql)

ARG LIBPQ_VERSION
ENV LIBPQ_BUILD_DIR=${BUILD_DIR}/libpq

WORKDIR ${LIBPQ_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://ftp.postgresql.org/pub/source/v${LIBPQ_VERSION}/postgresql-${LIBPQ_VERSION}.tar.gz \
    | tar xzC ${LIBPQ_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        --with-includes=${INSTALL_DIR}/include \
        --with-libraries=${INSTALL_DIR}/lib64:${INSTALL_DIR}/lib \
        --without-readline

RUN set -xe; \
    make -j$(nproc); \
    make -C src/include install; \
    make -C src/interfaces/libpq install


# Build OpenSSL
# https://www.openssl.org
#
# Needed by:
#  - curl

ARG OPENSSL_VERSION
ENV OPENSSL_BUILD_DIR=${BUILD_DIR}/openssl
ENV CA_BUNDLE_SOURCE="https://curl.se/ca/cacert.pem"
ENV CA_BUNDLE=${INSTALL_DIR}/dew/ssl/cert.pem

WORKDIR ${OPENSSL_BUILD_DIR}/

RUN set -xe; \
    # Determine if the major version of OpenSSL is v1
    if [ "${OPENSSL_VERSION}" != "${OPENSSL_VERSION#1*}" ]; then \
        OPENSSL_URL="https://github.com/openssl/openssl/releases/download/OpenSSL_$(echo $OPENSSL_VERSION | tr '.' '_')/openssl-${OPENSSL_VERSION}.tar.gz"; \
    else \
        OPENSSL_URL="https://github.com/openssl/openssl/releases/download/openssl-${OPENSSL_VERSION}/openssl-${OPENSSL_VERSION}.tar.gz"; \
    fi; \
    curl -sL $OPENSSL_URL \
    | tar xzC ${OPENSSL_BUILD_DIR} --strip-components 1

RUN set -xe; \
    # Determine if the major version of OpenSSL is v1
    if [ "${OPENSSL_VERSION}" != "${OPENSSL_VERSION#1*}" ]; then \
        OPENSSL_CONFIG="./config"; \
    else \
        OPENSSL_CONFIG="./Configure"; \
    fi; \
    CFLAGS="-O2 -pipe" \
    $OPENSSL_CONFIG \
        --prefix=${INSTALL_DIR} \
        --openssldir=${INSTALL_DIR}/dew/ssl \
        --release

RUN make -j$(nproc) && make install_sw install_ssldirs

RUN curl -sL -o ${CA_BUNDLE} ${CA_BUNDLE_SOURCE}


# Build libssh2
# https://www.libssh2.org
#
# Requires:
#   - OpenSSL
#   - zlib

ARG LIBSSH2_VERSION
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


# Build nghttp2
# https://nghttp2.org
#
# Needed by:
#   - curl

ARG NGHTTP2_VERSION
ENV NGHTTP2_BUILD_DIR=${BUILD_DIR}/nghttp2

WORKDIR ${NGHTTP2_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/nghttp2/nghttp2/releases/download/v${NGHTTP2_VERSION}/nghttp2-${NGHTTP2_VERSION}.tar.gz \
    | tar xzC ${NGHTTP2_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        # Build libnghttp2 only
        --enable-lib-only

RUN make -j$(nproc) install


# Build curl
# https://curl.se
#
# Requires:
#   - OpenSSL
#   - libssh2
#   - nghttp2
#
# Needed by:
#   - php (--with-curl)

ARG CURL_VERSION
ENV CURL_BUILD_DIR=${BUILD_DIR}/curl

WORKDIR ${CURL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://curl.se/download/curl-${CURL_VERSION}.tar.gz \
    | tar xzC ${CURL_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
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
        --disable-verbose \
        # Disable Public Suffix list support in cookies
        --without-libpsl

RUN make -j$(nproc) && make install


# Build onig
# https://github.com/kkos/oniguruma
#
# regular expression library
#
# Needed by:
#  - php (--enable-mbstring)

ARG ONIG_VERSION
ENV ONIG_BUILD_DIR=${BUILD_DIR}/onig

WORKDIR ${ONIG_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/kkos/oniguruma/releases/download/v${ONIG_VERSION}/onig-${ONIG_VERSION}.tar.gz \
    | tar xzC ${ONIG_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    ./configure \
        --prefix=${INSTALL_DIR}

RUN make -j$(nproc) && make install


# Build libxml2
# https://gitlab.gnome.org/GNOME/libxml2
#
# Needed by:
#   - php

ARG LIBXML2_VERSION
ENV LIBXML2_BUILD_DIR=${BUILD_DIR}/libxml2

WORKDIR ${LIBXML2_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://download.gnome.org/sources/libxml2/$(echo $LIBXML2_VERSION | cut -d'.' -f-2)/libxml2-${LIBXML2_VERSION}.tar.xz \
    | tar xJC ${LIBXML2_BUILD_DIR} --strip-components 1

RUN CFLAGS='-O2 -pipe -fno-semantic-interposition' \
   ./configure \
       --prefix=${INSTALL_DIR} \
       --without-debug \
       --without-python

RUN make -j$(nproc) && make install


# Build libargon2
# https://github.com/P-H-C/phc-winner-argon2
#
# Needed by:
#   - php (for versions < 8.4)

ARG LIBARGON2_VERSION
ENV LIBARGON2_BUILD_DIR=${BUILD_DIR}/libargon2

RUN if [ -n "$LIBARGON2_VERSION" ]; then \
        mkdir -p ${LIBARGON2_BUILD_DIR} && \
        cd ${LIBARGON2_BUILD_DIR} && \
        curl -sL https://github.com/P-H-C/phc-winner-argon2/archive/refs/tags/${LIBARGON2_VERSION}.tar.gz \
        | tar xzC ${LIBARGON2_BUILD_DIR} --strip-components 1 && \
        make install PREFIX=${INSTALL_DIR} LIBRARY_REL=lib; \
    fi


# Build libzip
# https://libzip.org
#
# Requires:
#   - openssl
#   - zlib
#
# Needed by:
#   - php

ARG LIBZIP_VERSION
ENV LIBZIP_BUILD_DIR=${BUILD_DIR}/libzip

WORKDIR ${LIBZIP_BUILD_DIR}/build

RUN set -xe; \
    curl -sL https://libzip.org/download/libzip-${LIBZIP_VERSION}.tar.gz \
    | tar xzC ${LIBZIP_BUILD_DIR} --strip-components 1

RUN CFLAGS="-O2 -pipe" \
    cmake \
    -DCMAKE_INSTALL_PREFIX=${INSTALL_DIR} \
    -DCMAKE_BUILD_TYPE=Release \
    -DBUILD_DOC=OFF \
    -DBUILD_EXAMPLES=OFF \
    # Build a shared library (.so) instead of a static one
    -DBUILD_SHARED_LIBS=ON \
    # Build without binary tools (zipcmp, zipmerge, ziptool)
    -DBUILD_TOOLS=OFF \
    ..

RUN make -j$(nproc) && make install


# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
# PHP Builder
# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

# Build PHP
# https://www.php.net
#
# Requires:
#   - curl
#   - libargon2
#   - libzip
#   - oniguruma
#   - openssl
#   - libpq
#   - zlib

FROM builder AS php-builder

ARG PHP_VERSION
ENV PHP_BUILD_DIR=${BUILD_DIR}/php
ENV PHP_INI_DIR=${INSTALL_DIR}/etc/php

WORKDIR ${PHP_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz \
    | tar xzC ${PHP_BUILD_DIR} --strip-components 1

# -fstack-protector-strong: Buffer overflow protection
# -O3: Highest level of optimization
# -pipe: Use pipes instead of temporary files
# -fpie: Position-independent executable
# -ffunction-sections: Discard unused functions
# -fdata-sections: Discard unused variables
# --gc-sections: Remove unused sections, conjunction with -ffunction-sections and -fdata-sections
# -D_LARGEFILE_SOURCE and -D_FILE_OFFSET_BITS=64: Support large files (https://www.php.net/manual/en/intro.filesystem.php)
RUN CFLAGS="-fstack-protector-strong -O3 -pipe -fpie -ffunction-sections -fdata-sections -D_LARGEFILE_SOURCE -D_FILE_OFFSET_BITS=64" \
    LDFLAGS="-Wl,-O1 -pie -Wl,--strip-all -Wl,--gc-sections" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        --enable-option-checking=fatal \
        --with-config-file-path=${PHP_INI_DIR} \
        --with-config-file-scan-dir=${PHP_INI_DIR}/conf.d:${FC_FUNC_CODE_PATH}/php/conf.d \
        --disable-phpdbg \
        --disable-cgi \
        --enable-fpm \
        --enable-bcmath \
        --enable-exif \
        --enable-ftp \
        --enable-intl \
        --enable-mbstring \
        --enable-opcache \
        --enable-sockets \
        --enable-pcntl \
        --with-curl=${INSTALL_DIR} \
        --with-iconv \
        --with-openssl \
        $(if [ "$(echo "$PHP_VERSION" | cut -d. -f1,2 | awk '{if ($1 >= 8.4) print "yes"; else print "no"}')" = "yes" ]; then \
            echo "--with-openssl-argon2"; \
        else \
            echo "--with-password-argon2=${INSTALL_DIR}"; \
        fi) \
        --with-pdo-mysql=shared,mysqlnd \
        --with-pdo-pgsql=shared,${INSTALL_DIR} \
        --with-pgsql=shared,${INSTALL_DIR} \
        --with-pear \
        --with-zip \
        --with-zlib

RUN set -xe; \
    make -j$(nproc); \
    make install; \
    cp php.ini-production ${INSTALL_DIR}/etc/php/php.ini

# Construct everything we need before moving to next stage
RUN mkdir -p /layer \
    /layer/bin \
    /layer/lib \
    /layer/lib/php/extensions \
    /layer/etc \
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
    # Copy shared library (.so) for PHP
    ldd ${INSTALL_DIR}/bin/php \
        | grep ${INSTALL_DIR} \
        | cut -d' ' -f3 \
        | xargs -I % cp % /layer/lib/; \
    # Copy PHP extensions
    cp $(php -r "echo ini_get('extension_dir');")/* /layer/lib/php/extensions/; \
    # Copy shared library (.so) for PHP extensions
    ldd /layer/lib/php/extensions/* \
        | grep "=> ${INSTALL_DIR}" \
        | cut -d' ' -f3 \
        | uniq \
        | xargs -I % cp % /layer/lib/; \
    # Copy PHP Configuration file
    cp ${INSTALL_DIR}/etc/php/php.ini /layer/etc/php/; \
    # Strip all symbols and debugging information for executables
    find /layer -type f -executable -exec strip --strip-all '{}' + || true

COPY stubs/php.ini /layer/etc/php/conf.d
COPY stubs/php-fpm.conf /layer/etc
COPY stubs/bootstrap /layer
COPY stubs/.rr.yaml /layer


# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
# Roadrunner for the HTTP server handling FC invocation requests
# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

ARG ROADRUNNER_VERSION
FROM ghcr.io/roadrunner-server/roadrunner:${ROADRUNNER_VERSION} AS roadrunner


# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
# Leave build environment behind and start with a clean image
# -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

ARG DEBIAN_VERSION
FROM debian:${DEBIAN_VERSION}

COPY --from=php-builder /layer /opt
COPY --from=roadrunner /usr/bin/rr /opt/bin/rr

ENTRYPOINT ["/opt/bin/php"]
