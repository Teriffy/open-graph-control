<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Options;

use EvzenLeonenko\OpenGraphControl\Options\DefaultSettings;
use EvzenLeonenko\OpenGraphControl\Options\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase {

	public function test_returns_input_unchanged_when_already_current(): void {
		$input  = [
			'version' => DefaultSettings::SCHEMA_VERSION,
			'foo'     => 'bar',
		];
		$output = ( new Migrator() )->migrate( $input );
		self::assertSame( $input, $output );
	}

	public function test_applies_migrations_in_order(): void {
		$migrations = [
			2 => static function ( array $s ): array {
				$s['trace'] = ( $s['trace'] ?? '' ) . '>2';
				return $s;
			},
			3 => static function ( array $s ): array {
				$s['trace'] = ( $s['trace'] ?? '' ) . '>3';
				return $s;
			},
		];

		$migrator = new Migrator( $migrations );
		$output   = $migrator->migrate( [ 'version' => 1 ] );

		self::assertSame( '>2>3', $output['trace'] );
		self::assertSame( 3, $output['version'] );
	}

	public function test_skips_earlier_migrations(): void {
		$migrations = [
			2 => static function ( array $s ): array {
				$s['trace'] = ( $s['trace'] ?? '' ) . '>2';
				return $s;
			},
			3 => static function ( array $s ): array {
				$s['trace'] = ( $s['trace'] ?? '' ) . '>3';
				return $s;
			},
		];

		$migrator = new Migrator( $migrations );
		$output   = $migrator->migrate( [ 'version' => 2 ] );

		self::assertSame( '>3', $output['trace'] );
		self::assertSame( 3, $output['version'] );
	}

	public function test_missing_version_treated_as_zero(): void {
		$migrations = [
			1 => static function ( array $s ): array {
				$s['migrated'] = true;
				return $s;
			},
		];
		$output     = ( new Migrator( $migrations ) )->migrate( [ 'site' => [ 'name' => 'X' ] ] );
		self::assertTrue( $output['migrated'] );
		self::assertSame( 1, $output['version'] );
	}
}
