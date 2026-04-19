#!/usr/bin/env bash
#
# Manual WordPress.org SVN publish helper. Use when you need to push a
# release without going through GitHub Actions (e.g. first submission,
# asset-only update, or local debugging of the build).
#
# Requires: svn, make-dist.sh output at dist/open-graph-control/.
#
# Usage:
#   WP_SVN_USERNAME=me WP_SVN_PASSWORD=pass bin/publish.sh 0.2.0
#

set -euo pipefail

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
  echo "Usage: $0 <version>" >&2
  exit 1
fi

: "${WP_SVN_USERNAME:?export WP_SVN_USERNAME first}"
: "${WP_SVN_PASSWORD:?export WP_SVN_PASSWORD first}"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SLUG="open-graph-control"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"
SVN_DIR=".svn-workspace"

echo "Building dist/…"
bash bin/make-dist.sh

if [[ ! -d "dist/${SLUG}" ]]; then
  echo "dist/${SLUG} missing after make-dist.sh" >&2
  exit 1
fi

echo "Checking out SVN…"
rm -rf "$SVN_DIR"
svn checkout "$SVN_URL" "$SVN_DIR" --quiet

echo "Syncing trunk…"
rsync -a --delete --exclude='.svn' "dist/${SLUG}/" "$SVN_DIR/trunk/"

echo "Syncing assets (.wordpress-org)…"
if [[ -d .wordpress-org ]]; then
  mkdir -p "$SVN_DIR/assets"
  rsync -a --delete --exclude='.svn' --exclude='README.md' --exclude='*.svg' \
    .wordpress-org/ "$SVN_DIR/assets/"
fi

cd "$SVN_DIR"
svn add --force . --quiet
svn status | awk '/^!/{print $2}' | xargs -I{} svn delete "{}" --quiet || true

echo "Tagging ${VERSION}…"
svn copy "trunk" "tags/${VERSION}" --quiet || true

svn commit \
  --username "$WP_SVN_USERNAME" \
  --password "$WP_SVN_PASSWORD" \
  --message "Release ${VERSION}" \
  --non-interactive

cd "$ROOT"
rm -rf "$SVN_DIR"
echo "Published ${VERSION} to https://wordpress.org/plugins/${SLUG}/"
