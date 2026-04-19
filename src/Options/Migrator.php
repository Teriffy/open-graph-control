<?php
/**
 * Schema migration runner.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Applies per-version migration callbacks to the stored ogc_settings option
 * when the user upgrades from an older plugin release.
 *
 * Each migration takes the stored option as-is and returns the mutated
 * shape. Runs sequentially from the stored `version` up to the current
 * DefaultSettings::SCHEMA_VERSION.
 *
 * Add new migrations to the $migrations map keyed by the TARGET version.
 */
final class Migrator {

	/**
	 * @var array<int, callable(array<string, mixed>): array<string, mixed>>
	 */
	private array $migrations;

	private int $target;

	/**
	 * @param array<int, callable(array<string, mixed>): array<string, mixed>>|null $migrations
	 */
	public function __construct( ?array $migrations = null, ?int $target = null ) {
		$this->migrations = $migrations ?? self::default_migrations();
		$this->target     = $target
			?? (
				[] === $this->migrations
					? DefaultSettings::SCHEMA_VERSION
					: max( DefaultSettings::SCHEMA_VERSION, max( array_keys( $this->migrations ) ) )
			);
	}

	/**
	 * @param array<string, mixed> $stored
	 * @return array<string, mixed>
	 */
	public function migrate( array $stored ): array {
		$from = isset( $stored['version'] ) && is_int( $stored['version'] )
			? $stored['version']
			: 0;

		if ( $from >= $this->target ) {
			return $stored;
		}

		for ( $t = $from + 1; $t <= $this->target; $t++ ) {
			if ( isset( $this->migrations[ $t ] ) ) {
				$stored = ( $this->migrations[ $t ] )( $stored );
			}
			$stored['version'] = $t;
		}

		return $stored;
	}

	/**
	 * @return array<int, callable(array<string, mixed>): array<string, mixed>>
	 */
	private static function default_migrations(): array {
		// No migrations yet — v1 is the initial schema. Future targets go here
		// keyed by the target version (e.g. 2 => fn(array $s) => $s with renamed keys).
		return [];
	}
}
