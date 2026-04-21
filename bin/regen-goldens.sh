#!/usr/bin/env bash
# Regenerate golden PNG fixtures from current GdRenderer output.
# Run after intentional renderer changes — review diff before committing.

set -euo pipefail
cd "$(dirname "$0")/.."
php tests/scripts/generate-goldens.php
echo "Goldens regenerated. Review diff: git diff tests/fixtures/expected-cards/"
