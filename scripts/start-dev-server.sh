#!/usr/bin/env bash
set -euo pipefail

# Helper to start PHP built-in server serving the project root
# Creates compatibility symlinks under `public/` so requests for /admin, /impact, /portal
# resolve the repo-root copies (same approach used in CI).

mkdir -p public
for d in admin impact portal; do
  if [ -d "$d" ]; then
    ln -sfn "$PWD/$d" "public/$d"
    echo "linked $d -> public/$d"
  fi
done

echo "Starting PHP built-in server on 0.0.0.0:8080 (docroot = project root)"
php -S 0.0.0.0:8080 -t .
