#!/usr/bin/env bash
# Push the current branch (or all branches/tags) to both GitHub remotes:
#   origin   -> gebstechnologies0109a/insa-pos-rewards (GEBS / Forge)
#   personal -> ronaldo82ba/insa-pos
# Never uses --force. See docs/DUAL_GITHUB.md.

set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

REMOTES=(origin personal)
MODE="${1:-}"

push_current() {
  local branch
  branch="$(git rev-parse --abbrev-ref HEAD)"
  if [ "$branch" = "HEAD" ]; then
    echo "error: detached HEAD; checkout a branch first" >&2
    exit 1
  fi
  for remote in "${REMOTES[@]}"; do
    echo "==> $remote: push $branch"
    git push "$remote" "$branch"
  done
}

push_all_refs() {
  for remote in "${REMOTES[@]}"; do
    echo "==> $remote: push --all"
    git push "$remote" --all
    echo "==> $remote: push --tags"
    git push "$remote" --tags
  done
}

case "$MODE" in
  ""|--current)
    push_current
    ;;
  --all)
    push_all_refs
    ;;
  -h|--help)
    echo "Usage: $0 [--current|--all|--help]"
    exit 0
    ;;
  *)
    echo "unknown option: $MODE" >&2
    exit 1
    ;;
esac

echo "Done. Both remotes updated."