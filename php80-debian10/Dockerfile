FROM dew/custom-debian10-builder:latest AS builder

# =============================================================================
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
        --with-password-argon2=${INSTALL_DIR} \
        --with-pdo-mysql=shared,mysqlnd \
        --with-pdo-pgsql=shared,${INSTALL_DIR} \
        --with-pgsql=shared,${INSTALL_DIR} \
        --with-pear \
        --with-readline=shared \
        --with-zip \
        --with-zlib

RUN set -xe; \
    make -j$(nproc); \
    make install; \
    cp php.ini-production ${INSTALL_DIR}/etc/php/php.ini


# =============================================================================
# Constrct everything we need before moving to next stage

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


# =============================================================================
# Leave build environment behind and start with a clean image
FROM ghcr.io/roadrunner-server/roadrunner:2023.1.4 AS roadrunner
FROM debian:10 AS clean

COPY --from=builder /layer /opt
COPY --from=roadrunner /usr/bin/rr /opt/bin/rr

ENTRYPOINT ["/opt/bin/php"]
