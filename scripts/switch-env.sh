#!/usr/bin/env bash
set -euo pipefail

# Usage: scripts/switch-env.sh staging|production

ENV_NAME=${1:-}
if [[ -z "$ENV_NAME" || ("$ENV_NAME" != "staging" && "$ENV_NAME" != "production") ]]; then
  echo "Usage: $0 staging|production" >&2
  exit 1
fi

SRC_FILE=".env.${ENV_NAME}.example"
if [[ ! -f "$SRC_FILE" ]]; then
  echo "Missing template $SRC_FILE" >&2
  exit 1
fi

cp -f "$SRC_FILE" .env
echo "Switched .env to $SRC_FILE"

echo "Hint: docker compose -f docker-compose.yaml -f docker-compose.${ENV_NAME}.yaml up -d --build"

