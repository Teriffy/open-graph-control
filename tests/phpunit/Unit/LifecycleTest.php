<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Lifecycle;
use EvzenLeonenko\OpenGraphControl\Options\DefaultSettings;
use Mockery;
use PHPUnit\Framework\TestCase;

final class LifecycleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_activate_seeds_option_when_missing(): void {
		Functions\expect( 'get_option' )->once()->with( 'ogc_settings' )->andReturn( false );
		Functions\expect( 'add_option' )->once()->with( 'ogc_settings', DefaultSettings::all() );
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'wp_next_scheduled' )->twice()->andReturn( false );
		Functions\expect( 'wp_schedule_event' )->twice();

		Lifecycle::activate();
		self::assertTrue( true );
	}

	public function test_activate_skips_add_option_when_already_exists(): void {
		Functions\expect( 'get_option' )->once()->with( 'ogc_settings' )->andReturn( [ 'version' => 1 ] );
		Functions\expect( 'add_option' )->never();
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'wp_next_scheduled' )->twice()->andReturn( false );
		Functions\expect( 'wp_schedule_event' )->twice();

		Lifecycle::activate();
		self::assertTrue( true );
	}

	public function test_deactivate_flushes_rewrites(): void {
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'wp_clear_scheduled_hook' )->twice();
		Lifecycle::deactivate();
		self::assertTrue( true );
	}

	public function test_uninstall_removes_option_and_postmeta(): void {
		Functions\expect( 'delete_option' )->once()->with( 'ogc_settings' );
		Functions\expect( 'delete_metadata' )->once()->with( 'post', 0, '_ogc_meta', '', true );

		Lifecycle::uninstall();
		self::assertTrue( true );
	}

	public function test_activate_schedules_backfill_cron_event(): void {
		Functions\expect( 'get_option' )->once()->with( 'ogc_settings' )->andReturn( false );
		Functions\expect( 'add_option' )->once();
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'wp_next_scheduled' )->once()->with( 'ogc_og_card_backfill' )->andReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->with(
			Mockery::type( 'int' ),
			'daily',
			'ogc_og_card_backfill'
		);

		Lifecycle::activate();
		self::assertTrue( true );
	}

	public function test_activate_schedules_gc_cron_event(): void {
		Functions\expect( 'get_option' )->once()->with( 'ogc_settings' )->andReturn( false );
		Functions\expect( 'add_option' )->once();
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'wp_next_scheduled' )->twice()->andReturnValues( [ false, false ] );
		Functions\expect( 'wp_schedule_event' )->twice()->with(
			Mockery::type( 'int' ),
			'daily',
			Mockery::anyOf( 'ogc_og_card_backfill', 'ogc_og_card_gc' )
		);

		Lifecycle::activate();
		self::assertTrue( true );
	}

	public function test_deactivate_clears_backfill_cron(): void {
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( 'ogc_og_card_backfill' );
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( 'ogc_og_card_gc' );

		Lifecycle::deactivate();
		self::assertTrue( true );
	}
}
