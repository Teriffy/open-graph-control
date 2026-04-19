#!/usr/bin/env bash
#
# Measures the end-to-end overhead the plugin adds to a page render.
#
# Runs 20 warmup requests + 30 measured requests in three configurations:
#   1. Plugin deactivated (baseline WP)
#   2. Plugin active, output cache OFF
#   3. Plugin active, output cache ON (after a warm-up request)
#
# Reports median time_total per config + delta vs baseline.
#
# Prereqs: wp-env running on :8888 with the plugin installed but not
# necessarily active. Idempotent — restores state (active, no cache) at end.
#

set -euo pipefail

URL="http://localhost:8888/"
RUNS=60
WARMUP=30
WPENV="npx @wordpress/env run cli"

bench() {
	# $1 label
	# Emit one number per request (seconds, fractional).
	for _ in $(seq 1 "$WARMUP"); do
		curl -s -o /dev/null "$URL"
	done
	for _ in $(seq 1 "$RUNS"); do
		curl -s -o /dev/null -w "%{time_total}\n" "$URL"
	done
}

median() {
	# stdin → median
	sort -n | awk '
		{ a[NR] = $1 }
		END {
			n = NR
			if (n % 2) { print a[(n+1)/2] }
			else { print (a[n/2] + a[n/2+1]) / 2 }
		}
	'
}

trimmed_mean() {
	# stdin → 10%-trimmed mean (drops top 10% + bottom 10%)
	sort -n | awk '
		{ a[NR] = $1 }
		END {
			n   = NR
			lo  = int(n * 0.1) + 1
			hi  = n - int(n * 0.1)
			sum = 0; cnt = 0
			for (i = lo; i <= hi; i++) { sum += a[i]; cnt++ }
			printf "%.6f\n", sum / cnt
		}
	'
}

ms() {
	awk '{ printf "%.1f", $1 * 1000 }'
}

echo "==> 1/3 baseline (plugin deactivated)"
$WPENV wp plugin deactivate open-graph-control --quiet 2>/dev/null || true
BASE=$( bench | trimmed_mean )

echo "==> 2/3 plugin active, cache OFF"
$WPENV wp plugin activate open-graph-control --quiet
$WPENV wp option delete ogc_settings --quiet 2>/dev/null || true
NOCACHE=$( bench | trimmed_mean )

echo "==> 3/3 plugin active, cache ON (ttl=300)"
$WPENV wp option update ogc_settings '{"output":{"cache_ttl":300}}' --format=json --quiet
# First request primes the cache; bench() then measures cache-hit path.
curl -s -o /dev/null "$URL"
CACHED=$( bench | trimmed_mean )

# Restore: clean cache, keep plugin active.
$WPENV wp option delete ogc_settings --quiet 2>/dev/null || true

printf '\nResults (10%%-trimmed mean of %d requests after %d warmup):\n' "$RUNS" "$WARMUP"
printf '  Plugin off:              %s ms\n' "$( echo "$BASE"    | ms )"
printf '  Plugin on, cache off:    %s ms\n' "$( echo "$NOCACHE" | ms )"
printf '  Plugin on, cache on:     %s ms\n' "$( echo "$CACHED"  | ms )"
printf '\nOverhead:\n'
printf '  Cold (no cache):         %s ms\n' "$( awk "BEGIN { printf \"%.1f\", ($NOCACHE - $BASE) * 1000 }" )"
printf '  Hot  (cache hit):        %s ms\n' "$( awk "BEGIN { printf \"%.1f\", ($CACHED  - $BASE) * 1000 }" )"
