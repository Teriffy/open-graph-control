#!/usr/bin/env bash
#
# Regenerates the six screenshot PNGs in .wordpress-org/ by driving a
# running wp-env instance through Playwright. The release workflow uploads
# these to the wp.org SVN assets/ subtree.
#
# Prereqs:
#   npx wp-env start          # http://localhost:8888 with the plugin active
#   npm install               # Playwright + helpers
#
# Output: .wordpress-org/screenshot-{1,2,3,4,5,6}.png
#

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! curl -sf http://localhost:8888/ > /dev/null; then
  echo "wp-env isn't running on :8888. Start it with: npx wp-env start" >&2
  exit 1
fi

OGC_E2E_WP=1 OGC_WPORG_SCREENSHOTS=1 npx playwright test \
  tests/e2e/playwright/wporg-screenshots.spec.ts \
  --reporter=line

echo "Screenshots 1-6 regenerated in .wordpress-org/"
ls -lh .wordpress-org/screenshot-*.png
