<?php
/**
 * Options repository.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Options;

/**
 * Reads and writes the ogc_settings option.
 *
 * The stored option is deep-merged over DefaultSettings so old installs pick
 * up new keys when the plugin adds them. Numeric (list-style) arrays are
 * replaced wholesale — users must be able to shorten fallback chains.
 */
class Repository {

	public const OPTION_KEY = 'ogc_settings';

	/** @var array<string, mixed>|null */
	private ?array $cache = null;

	/**
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		/** @var array<string, mixed> $stored */
		$stored = get_option( self::OPTION_KEY, [] );
		if ( [] !== $stored ) {
			$stored = ( new Migrator() )->migrate( $stored );
		}
		$this->cache = $this->deep_merge( DefaultSettings::all(), $stored );

		return $this->cache;
	}

	/**
	 * @param array<string, mixed> $patch
	 */
	public function update( array $patch ): bool {
		$merged      = $this->deep_merge( $this->get(), $patch );
		$this->cache = $merged;

		return (bool) update_option( self::OPTION_KEY, $merged, true );
	}

	public function get_path( string $dot_path ): mixed {
		$parts   = explode( '.', $dot_path );
		$current = $this->get();
		foreach ( $parts as $part ) {
			if ( ! is_array( $current ) || ! array_key_exists( $part, $current ) ) {
				return null;
			}
			$current = $current[ $part ];
		}
		return $current;
	}

	public function flush_cache(): void {
		$this->cache = null;
	}

	/**
	 * Recursive merge that treats associative arrays as mergeable and numeric
	 * (list) arrays as replaceable.
	 *
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $over
	 * @return array<string, mixed>
	 */
	private function deep_merge( array $base, array $over ): array {
		foreach ( $over as $key => $value ) {
			if (
				is_array( $value )
				&& isset( $base[ $key ] )
				&& is_array( $base[ $key ] )
				&& $this->is_assoc( $base[ $key ] )
				&& $this->is_assoc( $value )
			) {
				$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * @param array<mixed> $arr
	 */
	private function is_assoc( array $arr ): bool {
		if ( [] === $arr ) {
			return true;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
