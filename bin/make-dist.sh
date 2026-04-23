#!/usr/bin/env bash
#
# Builds a production-ready plugin zip for WordPress.org submission.
#
# Requires: composer, npm, rsync, zip.
#
# Output: dist/open-graph-control-${VERSION}.zip
#

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="$(grep -E 'Stable tag:' readme.txt | awk '{print $3}')"
if [[ -z "$VERSION" ]]; then
  echo "Could not read Stable tag from readme.txt" >&2
  exit 1
fi

STAGE="dist/open-graph-control"
ZIP="dist/open-graph-control-${VERSION}.zip"

echo "Building Open Graph Control ${VERSION}"

# Clean slate.
rm -rf dist
mkdir -p "$STAGE"

# Production composer install (no dev deps).
composer install --no-dev --optimize-autoloader --no-interaction --quiet

# Production JS build.
npm ci --silent >/dev/null
npm run build --silent

# Stage files.
rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.idea/' \
  --exclude '.vscode/' \
  --exclude '.superpowers/' \
  --exclude '.wordpress-org/' \
  --exclude '.worktrees/' \
  --exclude '.phpunit.cache/' \
  --exclude '.phpunit.result.cache' \
  --exclude '.claude/' \
  --exclude 'node_modules/' \
  --exclude 'tests/' \
  --exclude 'docs/' \
  --exclude 'bin/' \
  --exclude 'dist/' \
  --exclude 'assets/' \
  --exclude 'coverage/' \
  --exclude 'test-results/' \
  --exclude 'playwright-report/' \
  --exclude '*.log' \
  --exclude '*.lock' \
  --exclude '.DS_Store' \
  --exclude '.gitignore' \
  --exclude '.wp-env*' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'phpstan.neon' \
  --exclude 'phpstan-bootstrap.php' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'webpack.config.js' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'playwright.config.ts' \
  --exclude 'README.md' \
  --exclude 'ROADMAP.md' \
  --exclude 'SECURITY.md' \
  ./ "$STAGE/"

# Restore dev composer deps for local work.
composer install --no-interaction --quiet

# Zip it.
cd dist
zip -r "../${ZIP}" "open-graph-control" -q
cd "$ROOT"

echo "Built: ${ZIP}"
echo "Staged tree: ${STAGE}"
echo "Size: $(du -h "${ZIP}" | awk '{print $1}')"
