#!/bin/bash
#
# Runs the varnishtest suite for the Platform.sh / Ibexa Cloud VCL.
#
# That VCL is not loadable as it stands: Platform.sh supplies the VCL version declaration, the std
# import and the app.backend() director. This script prepends the first two and swaps the director
# for a stub pointing at the test backend, then loads the resulting file into a real Varnish, so
# what is tested is the shipped file rather than a copy of it.
#
# Usage:
#   docker build -t ibexa-varnishtest:7 tests/varnish
#   IMAGE=ibexa-varnishtest:7 tests/varnish/run.sh

set -euo pipefail

TESTDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOTDIR="$(cd "$TESTDIR/../.." && pwd)"
VCL="${VCL:-$ROOTDIR/resources/platformsh/common/4.6/.platform/varnish.vcl}"
IMAGE="${IMAGE:-ibexa-varnishtest:7}"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

{
    echo "// Prologue supplied by Platform.sh at runtime, added here so the file can be loaded."
    echo "vcl 4.1;"
    echo "import std;"
    echo "backend stub { .host = \"127.0.0.1\"; .port = \"9081\"; }"
    sed 's/app\.backend()/stub/' "$VCL"
} > "$WORKDIR/default.vcl"

echo "==> $(basename "$(dirname "$(dirname "$VCL")")")/$(basename "$VCL") (${IMAGE})"

for vtc in "$TESTDIR"/*.vtc; do
    echo "--> $(basename "$vtc")"
    docker run --rm \
        -v "$WORKDIR/default.vcl:/etc/varnish/default.vcl:ro" \
        -v "$vtc:/case.vtc:ro" \
        --entrypoint varnishtest "$IMAGE" /case.vtc
done

echo "All varnishtest cases passed."
