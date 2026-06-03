#!/usr/bin/env bash
set -euo pipefail

# Simple rollback helper for servers that deploy from git
# Usage: ./scripts/rollback.sh <commit-ish> [--dry-run]

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <commit-ish> [--dry-run]" >&2
  exit 1
fi

TARGET_COMMIT="$1"; shift || true
DRY_RUN=""
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

echo "[Rollback] Target commit: $TARGET_COMMIT"

if [[ -z "$DRY_RUN" ]]; then
  echo "[Rollback] Stashing local changes (if any)"
  git stash push -u -m "pre-rollback-$(date +%Y%m%d-%H%M%S)" || true

  echo "[Rollback] Checking out $TARGET_COMMIT"
  git fetch --all --prune || true
  git checkout "$TARGET_COMMIT"
else
  echo "[Dry Run] Would: git stash; git checkout $TARGET_COMMIT"
fi

echo "[Rollback] Rebuilding and restarting services"
if command -v docker compose >/dev/null 2>&1; then
  CMD="docker compose"
else
  CMD="docker-compose"
fi

if [[ -z "$DRY_RUN" ]]; then
  $CMD down || true
  $CMD up -d --build
  echo "[Rollback] Complete"
else
  echo "[Dry Run] Would: $CMD down; $CMD up -d --build"
fi

