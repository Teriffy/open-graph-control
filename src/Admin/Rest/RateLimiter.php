<?php
/**
 * Per-user sliding-window rate limiter for REST endpoints.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Admin\Rest;

/**
 * Counts requests per user per key in a transient and rejects traffic that
 * exceeds the configured limit within the window. Cheap protection to keep
 * the preview endpoint from being used as an ad-hoc heavy tag renderer.
 *
 * Storage is a transient so it's best-effort (cleared on cache flush) —
 * deliberate; we don't need perfect accounting, just a UX brake.
 */
class RateLimiter {

	private const TRANSIENT_PREFIX = 'ogc_rl_';

	public function __construct(
		private int $max_hits = 20,
		private int $window_seconds = 60
	) {}

	public function check( string $key ): bool {
		$user_id   = get_current_user_id();
		$transient = self::TRANSIENT_PREFIX . md5( $key . ':' . $user_id );

		$count = (int) get_transient( $transient );
		if ( $count >= $this->max_hits ) {
			return false;
		}

		set_transient( $transient, $count + 1, $this->window_seconds );
		return true;
	}
}
