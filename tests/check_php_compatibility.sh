#!/bin/sh
# Check declarations in a fresh PHP process: bootstrap may already have loaded them.
# php -l exits successfully even when it reports deprecations.
set -eu
cd "$(dirname "$0")/.."
for file in lib/classes/doc.inc.php \
    lib/classes/node.inc.php \
    lib/common.php \
    lib/lib.php \
    views_mod.php; do
    if output=$(php -d error_reporting=-1 -d display_errors=1 -d log_errors=0 -l "$file" 2>&1); then
        if [ "$output" = "No syntax errors detected in $file" ]; then
            continue
        fi
    fi
    printf '%s\n' "$output" >&2
    exit 1
done
printf '%s\n' 'Changed PHP files compile without warnings or deprecations.'
