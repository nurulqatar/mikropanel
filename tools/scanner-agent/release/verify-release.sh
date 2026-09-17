#!/usr/bin/env bash
set -Eeuo pipefail

FILE="${1:-}"

if [ -z "$FILE" ] || [ ! -s "$FILE" ]
then
    echo "USAGE: $0 <signed-windows-exe>"
    exit 2
fi

echo "============================================================"
echo " MIKROPANEL SCANNER RELEASE VERIFICATION"
echo "============================================================"

echo
echo "=== FILE ==="
file "$FILE"

file "$FILE" \
    | grep -qE 'PE32|PE32\+'

echo "WINDOWS_PE=PASS"

echo
echo "=== SHA256 ==="
sha256sum "$FILE"

echo
echo "=== AUTHENTICODE ==="

set +e
VERIFY="$(
    osslsigncode verify \
        -in "$FILE" \
        2>&1
)"
RC=$?
set -e

echo "$VERIFY"

if [ "$RC" -ne 0 ]
then
    echo
    echo "AUTHENTICODE=NOT_TRUSTED_OR_UNSIGNED"
    exit 1
fi

echo "$VERIFY" \
    | grep -qi \
        'Succeeded'

echo
echo "AUTHENTICODE_VERIFY=PASS"
echo "RELEASE_GATE=PASS"
echo "============================================================"
