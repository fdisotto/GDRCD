#!/bin/bash
# Watch project files and rebuild Tailwind output.css on change.
# Used by the `tailwind` dev service in docker-compose.override.yml.
# The standalone Tailwind v3 CLI's --watch is unreliable inside this
# container, so we drive rebuilds with inotifywait instead.

set -euo pipefail

ROOT="${1:-/build}"
CONFIG="${ROOT}/tailwind.config.js"
INPUT="${ROOT}/themes/tailwind/input.css"
OUTPUT="${ROOT}/themes/tailwind/output.css"

build() {
    echo "[tailwind] rebuild $(date -u +%H:%M:%S)"
    tailwindcss -c "$CONFIG" -i "$INPUT" -o "$OUTPUT" --minify 2>&1 \
        | grep -v -E '(Browserslist|update-browserslist|Why you should)' || true
}

build

# Watch only source files. Skip the output, vendor dirs, assets we don't style with.
# We rely on a single --exclude (regex) and post-filter by extension in the loop,
# because inotifywait does not allow --include and --exclude together.
inotifywait -m -r -q \
    -e modify,create,delete,move \
    --exclude '(/\.git/|/node_modules/|/giocate/|/imgs/|/sounds/|themes/tailwind/output\.css$)' \
    "$ROOT" \
    | while read -r dir events file; do
        case "$file" in
            *.php|*.js|*.html|*.css|tailwind.config.js)
                build
                ;;
        esac
    done
