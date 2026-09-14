# Image for running the varnishtest suite in tests/varnish/.
#
# Mirrors doc/docker/Dockerfile-varnish from ibexa/docker. It has to be Varnish 6.0LTS specifically:
# this VCL returns "miss" from vcl_hit, which 6.5 and later reject. The xkey vmod is not packaged
# for 6.0LTS, so varnish-modules is built from source, as in the image this mirrors.

FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive

ARG PACKAGECLOUD_URL=https://packagecloud.io/install/repositories/varnishcache/varnish60lts/script.deb.sh
ARG VARNISH_MODULES_VERSION=0.15.0

RUN set -xe \
        && buildDeps=" \
            make \
            automake \
            autotools-dev \
            libedit-dev \
            libjemalloc-dev \
            libncurses-dev \
            libpcre3-dev \
            libtool \
            pkg-config \
            python3-docutils \
            varnish-dev=6.0.12-1~bullseye \
        " \
        && apt-get update -q -y \
        && apt-get install -q -y --no-install-recommends ca-certificates curl \
        \
        && curl -s ${PACKAGECLOUD_URL} | bash \
        && apt-get install -q -y --allow-unauthenticated --no-install-recommends varnish=6.0.12-1~bullseye $buildDeps \
        \
        && curl -A "Docker" -o /tmp/varnish-modules.tar.gz -D - -L -s https://github.com/varnish/varnish-modules/archive/refs/tags/${VARNISH_MODULES_VERSION}.tar.gz \
        && tar zxpf /tmp/varnish-modules.tar.gz -C /tmp/ \
        && cd /tmp/varnish-modules-${VARNISH_MODULES_VERSION} \
        && ./bootstrap \
        && ./configure \
        && make \
        && make install \
        && rm -f /tmp/varnish-modules.tar.gz && rm -Rf /tmp/varnish-modules-${VARNISH_MODULES_VERSION} \
        \
        && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $buildDeps \
        && rm -rf /var/lib/apt/lists/*
