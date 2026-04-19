<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Integrations;

use Brain\Monkey;
use Brain\Monkey\Filters;
use EvzenLeonenko\OpenGraphControl\Integrations\Detector;
use EvzenLeonenko\OpenGraphControl\Integrations\IntegrationInterface;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use PHPUnit\Framework\TestCase;

final class DetectorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function integration( string $slug, bool $active, bool &$takeover_called, bool &$bridge_called ): IntegrationInterface {
		return new class($slug, $active, $takeover_called, $bridge_called) implements IntegrationInterface {

			/**
			 * @param bool $takeover_called
			 * @param bool $bridge_called
			 */
			public function __construct(
				private string $slug,
				private bool $active,
				private bool &$takeover_called,
				private bool &$bridge_called
			) {}

			public function slug(): string {
				return $this->slug;
			}

			public function label(): string {
				return ucfirst( $this->slug );
			}

			public function is_active(): bool {
				return $this->active;
			}

			public function apply_takeover(): void {
				$this->takeover_called = true;
			}

			public function register_value_bridge(): void {
				$this->bridge_called = true;
			}
		};
	}

	private function options( array $stored = [] ): OptionsRepository {
		$repo = $this->createMock( OptionsRepository::class );
		$repo->method( 'get_path' )->willReturnCallback(
			static fn ( string $path ) => $stored[ $path ] ?? null
		);
		$repo->method( 'update' )->willReturn( true );
		return $repo;
	}

	public function test_active_filters_by_is_active(): void {
		$taken_a  = false;
		$taken_b  = false;
		$bridge_a = false;
		$bridge_b = false;
		$detector = new Detector( $this->options() );
		$detector->register( $this->integration( 'yoast', true, $taken_a, $bridge_a ) );
		$detector->register( $this->integration( 'tsf', false, $taken_b, $bridge_b ) );

		$active = $detector->active();
		self::assertCount( 1, $active );
		self::assertSame( 'yoast', $active[0]->slug() );
	}

	public function test_run_stores_detected_slugs_and_triggers_bridges(): void {
		$taken   = false;
		$bridge  = false;
		$options = $this->options(
			[
				'integrations.detected' => [],
				'integrations.takeover' => [],
			]
		);
		$options->expects( self::once() )
			->method( 'update' )
			->with(
				self::callback(
					static fn ( $payload ) => isset( $payload['integrations']['detected'] )
						&& [ 'yoast' ] === $payload['integrations']['detected']
				)
			);

		Filters\expectApplied( 'ogc_detected_plugins' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_apply_takeover_yoast' )->andReturnFirstArg();

		$detector = new Detector( $options );
		$detector->register( $this->integration( 'yoast', true, $taken, $bridge ) );
		$detector->run();

		self::assertTrue( $bridge, 'Value bridge must be registered even when takeover is off' );
		self::assertFalse( $taken, 'Takeover not applied unless user opted in' );
	}

	public function test_run_applies_takeover_when_user_opted_in(): void {
		$taken   = false;
		$bridge  = false;
		$options = $this->options(
			[
				'integrations.detected' => [ 'yoast' ],
				'integrations.takeover' => [ 'yoast' => true ],
			]
		);
		// Detected list already matches → no update call.
		$options->expects( self::never() )->method( 'update' );

		Filters\expectApplied( 'ogc_detected_plugins' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_apply_takeover_yoast' )->andReturnFirstArg();

		$detector = new Detector( $options );
		$detector->register( $this->integration( 'yoast', true, $taken, $bridge ) );
		$detector->run();

		self::assertTrue( $taken );
	}

	public function test_run_honors_filter_override_to_force_takeover(): void {
		$taken   = false;
		$bridge  = false;
		$options = $this->options(
			[
				'integrations.detected' => [ 'yoast' ],
				'integrations.takeover' => [ 'yoast' => false ],
			]
		);

		Filters\expectApplied( 'ogc_detected_plugins' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_apply_takeover_yoast' )->once()->andReturn( true );

		$detector = new Detector( $options );
		$detector->register( $this->integration( 'yoast', true, $taken, $bridge ) );
		$detector->run();

		self::assertTrue( $taken );
	}
}
