# Build upon Function Compute custom runtime build environment
# See: https://help.aliyun.com/document_detail/132044.html
ARG RUNTIME_VERSION=latest
FROM aliyunfc/runtime-custom.debian10:build-${RUNTIME_VERSION} AS builder

# Define a directory for building
ENV BUILD_DIR=/tmp/build

# Define a directory for installation
ENV INSTALL_DIR=/opt

# Configure environment variables
ENV PKG_CONFIG_PATH=${INSTALL_DIR}/lib64/pkgconfig:${INSTALL_DIR}/lib/pkgconfig \
    LD_LIBRARY_PATH=${INSTALL_DIR}/lib64:${INSTALL_DIR}/lib \
    PATH=${INSTALL_DIR}/bin:$PATH

# Install dependencies
# camke: libzip uses cmake to build
# libicu-dev: PHP intl extension requires ICU library
# libunistring-dev: needed by libpsl to handle Unicode manipulation
RUN apt-get update && \
    apt-get install -y cmake libicu-dev libunistring-dev && \
    rm -rf /var/lib/apt/lists/*

# Prepare directories
RUN mkdir -p ${INSTALL_DIR} \
    ${INSTALL_DIR}/etc/php \
    ${INSTALL_DIR}/etc/php/conf.d


# =============================================================================
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
        OPENSSL_CONFIG="./config"; \
    else \
        OPENSSL_URL="https://github.com/openssl/openssl/releases/download/openssl-${OPENSSL_VERSION}/openssl-${OPENSSL_VERSION}.tar.gz"; \
        OPENSSL_CONFIG="./Configure"; \
    fi; \
    curl -sL $OPENSSL_URL | tar xzC ${OPENSSL_BUILD_DIR} --strip-components 1; \
    $OPENSSL_CONFIG \
        CFLAGS="-O2 -pipe" \
        --prefix=${INSTALL_DIR} \
        --openssldir=${INSTALL_DIR}/dew/ssl \
        --release

RUN make -j$(nproc) && make install_sw install_ssldirs

RUN curl -sL -o ${CA_BUNDLE} ${CA_BUNDLE_SOURCE}


# =============================================================================
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


# =============================================================================
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

RUN CFLAGS="-I${INSTALL_DIR}/include -O2 -pipe" \
    LDFLAGS="-L${INSTALL_DIR}/lib" \
    ./configure \
        --prefix=${INSTALL_DIR} \
        # Build libnghttp2.so only
        --enable-lib-only

RUN make -j$(nproc) install


# =============================================================================
# Build libpsl
# https://github.com/rockdaboot/libpsl
#
# Needed by
#   - curl

ARG LIBPSL_VERSION
ENV LIBPSL_BUILD_DIR=${BUILD_DIR}/libpsl

WORKDIR ${LIBPSL_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/rockdaboot/libpsl/releases/download/${LIBPSL_VERSION}/libpsl-${LIBPSL_VERSION}.tar.gz \
    | tar xzC ${LIBPSL_BUILD_DIR} --strip-components 1

RUN CFLAGS="-I${INSTALL_DIR}/include -O2 -pipe" \
    LDFLAGS="-L${INSTALL_DIR}/lib" \
    ./configure \
        --prefix=${INSTALL_DIR}

RUN make -j$(nproc) && make install


# =============================================================================
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

RUN CFLAGS="-I${INSTALL_DIR}/include -O2 -pipe" \
    LDFLAGS="-L${INSTALL_DIR}/lib64 -L${INSTALL_DIR}/lib" \
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


# =============================================================================
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


# =============================================================================
# Build libargon2
# https://github.com/P-H-C/phc-winner-argon2
#
# Needed by:
#   - php

ARG LIBARGON2_VERSION
ENV LIBARGON2_BUILD_DIR=${BUILD_DIR}/libargon2

WORKDIR ${LIBARGON2_BUILD_DIR}/

RUN set -xe; \
    curl -sL https://github.com/P-H-C/phc-winner-argon2/archive/refs/tags/${LIBARGON2_VERSION}.tar.gz \
    | tar xzC ${LIBARGON2_BUILD_DIR} --strip-components 1

RUN make install PREFIX=${INSTALL_DIR} LIBRARY_REL=lib


# =============================================================================
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

RUN CFLAGS="-I${INSTALL_DIR}/include -O2 -pipe" \
    LDFLAGS="-L${INSTALL_DIR}/lib" \
    cmake \
    -DCMAKE_INSTALL_PREFIX=${INSTALL_DIR} \
    -DCMAKE_BUILD_TYPE=Release \
    # Build a shared library (.so) instead of a static one
    -DBUILD_SHARED_LIBS=ON \
    ..

RUN make -j$(nproc) && make install
